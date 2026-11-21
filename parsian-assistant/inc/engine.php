<?php
/**
 * Shared assistant engine: one answer path for website and Bale.
 *
 * Orchestration (conversations, limits, handoff, audit) lives here.
 * Retrieval, model calls, and guards reuse the theme implementation when
 * the Parsian theme is active; standalone fallbacks keep the engine
 * testable without it. Channel adapters must NOT implement AI logic.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge retriever: approved entries + FAQs, never pending feedback.
 */
class PA_Knowledge_Retriever implements PA_KnowledgeRetriever {
	public function retrieve( $query, $limit = 5 ) {
		if ( function_exists( 'pm_ai_search_index' ) ) {
			$entries = pm_ai_search_index( $query, $limit );
			$out = array();
			foreach ( (array) $entries as $entry ) {
				$out[] = array(
					'title'  => isset( $entry['title'] ) ? $entry['title'] : '',
					'text'   => isset( $entry['text'] ) ? $entry['text'] : '',
					'url'    => isset( $entry['url'] ) ? $entry['url'] : home_url( '/' ),
					'origin' => isset( $entry['type'] ) ? $entry['type'] : 'index',
				);
			}
			return $out;
		}
		return array();
	}
}

/**
 * AI provider: OpenAI-compatible endpoint from existing settings.
 */
class PA_AI_Provider implements PA_AIProviderInterface {
	public function configured() {
		$url = $this->setting( 'ai_api_url', '' );
		$key = $this->secret( 'ai_api_key' );
		$model = $this->setting( 'ai_model', '' );
		return '' !== trim( (string) $url ) && '' !== trim( (string) $key ) && '' !== trim( (string) $model );
	}

	public function complete( $system, $user_message, array $context_entries ) {
		$url = trim( (string) $this->setting( 'ai_api_url', '' ) );
		$key = $this->secret( 'ai_api_key' );
		$model = trim( (string) $this->setting( 'ai_model', 'gpt-4o-mini' ) );

		// Check the destination before the key is attached to any request.
		$destination = pa_outbound_validate_endpoint( $url, 'ai' );
		if ( true !== $destination ) {
			return array( 'ok' => false, 'error' => 'endpoint' );
		}
		$context = '';
		foreach ( $context_entries as $entry ) {
			$text = isset( $entry['text'] ) ? wp_strip_all_tags( (string) $entry['text'] ) : '';
			$context .= mb_substr( $text, 0, 900 ) . "\n\n";
		}
		$payload = array(
			'model'       => $model,
			'messages'    => array(
				array( 'role' => 'system', 'content' => (string) $system ),
				array( 'role' => 'user', 'content' => (string) $user_message ),
			),
			'temperature' => 0.2,
			'max_tokens'  => 700,
		);
		if ( '' !== $context ) {
			$payload['messages'][0]['content'] .= "\n\nدانش تأییدشده:\n\n" . trim( $context );
		}
		$response = wp_remote_post(
			$url,
			pa_outbound_safe_request_args(
				array(
					'timeout' => 45,
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $key,
					),
					'body'    => wp_json_encode( $payload ),
				)
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'error' => 'http' );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			return array( 'ok' => false, 'error' => 'api' );
		}
		$reply = '';
		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$reply = trim( wp_strip_all_tags( (string) $body['choices'][0]['message']['content'] ) );
		}
		if ( '' === $reply ) {
			return array( 'ok' => false, 'error' => 'empty' );
		}
		if ( function_exists( 'pm_ai_guard_response' ) ) {
			$reply = pm_ai_guard_response( $reply, $key );
		} else {
			$reply = pa_redact( $reply );
		}
		return array( 'ok' => true, 'reply' => $reply );
	}

	protected function setting( $key, $default = '' ) {
		if ( function_exists( 'pm_ai_get' ) ) {
			return pm_ai_get( $key );
		}
		return get_option( 'pa_' . $key, $default );
	}

	protected function secret( $key ) {
		if ( defined( 'PA_AI_API_KEY' ) && 'ai_api_key' === $key && PA_AI_API_KEY ) {
			return PA_AI_API_KEY;
		}
		return $this->setting( $key, '' );
	}
}

/**
 * Operator handoff via conversation status + audit trail.
 */
class PA_Operator_Handoff implements PA_OperatorHandoffService {
	public function needs_operator( array $answer ) {
		if ( isset( $answer['action'] ) && 'escalate' === $answer['action'] ) {
			return true;
		}
		return isset( $answer['confidence'] ) && (float) $answer['confidence'] < 0.35;
	}

	public function handoff( $conversation_id, $reason ) {
		$repo = new PA_Conversation_Repository();
		$repo->set_status( $conversation_id, 'escalated' );
		$repo->audit( $conversation_id, 'handoff', array( 'reason' => $reason ) );
	}
}

/**
 * The shared answer service.
 */
class PA_Assistant_Service implements PA_AssistantService {
	protected $retriever;
	protected $provider;
	protected $handoff;

	public function __construct(
		?PA_Knowledge_Retriever $retriever = null,
		?PA_AIProviderInterface $provider = null,
		?PA_OperatorHandoffService $handoff = null
	) {
		$this->retriever = $retriever ? $retriever : new PA_Knowledge_Retriever();
		$this->provider  = $provider ? $provider : new PA_AI_Provider();
		$this->handoff   = $handoff ? $handoff : new PA_Operator_Handoff();
	}

	public function answer( array $input ) {
		$channel = isset( $input['channel'] ) ? sanitize_key( $input['channel'] ) : 'web';
		$text = isset( $input['text'] ) ? sanitize_textarea_field( (string) $input['text'] ) : '';
		$user_id = isset( $input['user_id'] ) ? absint( $input['user_id'] ) : 0;
		$reply_to = isset( $input['reply_to_message_id'] ) ? absint( $input['reply_to_message_id'] ) : 0;
		$log_user = ! isset( $input['log_user'] ) || ! empty( $input['log_user'] );
		$external = isset( $input['external_key'] ) ? sanitize_text_field( (string) $input['external_key'] ) : '';
		if ( '' === $external ) {
			$external = 'anon-' . md5( (string) wp_rand( 1, PHP_INT_MAX ) . microtime( true ) );
		}

		$repo = new PA_Conversation_Repository();
		$conversation_id = $repo->find_or_create( $channel, $external, $user_id );
		if ( ! $conversation_id ) {
			return array(
				'reply'           => 'ذخیرهٔ گفتگو انجام نشد؛ لطفاً دوباره تلاش کنید.',
				'sources'         => array(),
				'confidence'      => 0.0,
				'action'          => 'error',
				'conversation_id' => 0,
				'message_id'      => 0,
			);
		}
		$message_id = 0;
		if ( $log_user ) {
			$message_id = $repo->add_message( $conversation_id, 'user', $text );
		}

		// A human handoff is a product state, not a temporary flag. Never
		// resume AI replies automatically; the operator queue owns the
		// conversation until a human resolves it.
		if ( 'escalated' === $repo->status( $conversation_id ) ) {
			$repo->audit( $conversation_id, 'handoff_preserved', array( 'message_id' => (int) $message_id ) );
			return array(
				'reply'           => 'پیام شما در صف بررسی کارشناس قرار گرفت.',
				'sources'         => array(),
				'confidence'      => 0.0,
				'action'          => 'handoff',
				'conversation_id' => $conversation_id,
				'message_id'      => 0,
			);
		}

		if ( mb_strlen( $text ) < 2 ) {
			return $this->finalize( $repo, $conversation_id, 'سوال خود را بنویسید.', array(), 1.0, 'clarify', $reply_to );
		}

		if ( $this->is_injection( $text ) ) {
			$repo->audit( $conversation_id, 'injection_blocked', array() );
			return $this->finalize(
				$repo,
				$conversation_id,
				'من فقط می‌توانم دربارهٔ آکادمی موسیقی پارسیان، اساتید، دوره‌ها و مسیر آموزشی کمک کنم.',
				array(),
				1.0,
				'none',
				$reply_to
			);
		}

		$entries = $this->retriever->retrieve( $text, 5 );
		$sources = array();
		foreach ( $entries as $entry ) {
			$sources[] = array(
				'title' => isset( $entry['title'] ) ? $entry['title'] : '',
				'url'   => isset( $entry['url'] ) ? $entry['url'] : home_url( '/' ),
			);
		}

		if ( ! $this->provider->configured() ) {
			$repo->audit( $conversation_id, 'provider_not_configured', array() );
			if ( $entries ) {
				$top = $entries[0];
				$reply = ( isset( $top['title'] ) ? $top['title'] . "\n" : '' ) . mb_substr( (string) $top['text'], 0, 600 );
				return $this->finalize( $repo, $conversation_id, $reply, array_slice( $sources, 0, 3 ), 0.6, 'none', $reply_to );
			}
			return $this->finalize(
				$repo,
				$conversation_id,
				'دستیار در حال آماده‌سازی است؛ لطفاً بعداً دوباره تلاش کنید یا از فرم مشاوره استفاده کنید.',
				array(),
				0.2,
				'escalate',
				$reply_to
			);
		}

		$system = $this->system_prompt();
		$result = $this->provider->complete( $system, $text, $entries );
		if ( ! $result['ok'] ) {
			$repo->audit( $conversation_id, 'provider_error', array( 'error' => $result['error'] ) );
			return $this->finalize(
				$repo,
				$conversation_id,
				'ارتباط با دستیار برقرار نشد؛ لطفاً کمی بعد دوباره تلاش کنید.',
				array_slice( $sources, 0, 3 ),
				0.2,
				'escalate',
				$reply_to
			);
		}

		$confidence = $entries ? 0.75 : 0.4;
		$action = $entries ? 'none' : 'escalate';
		$answer = $this->finalize( $repo, $conversation_id, $result['reply'], array_slice( $sources, 0, 3 ), $confidence, $action, $reply_to );
		if ( $this->handoff->needs_operator( $answer ) ) {
			$this->handoff->handoff( $conversation_id, 'low_confidence' );
		}
		return $answer;
	}

	protected function finalize( $repo, $conversation_id, $reply, $sources, $confidence, $action, $reply_to = 0 ) {
		$meta = array( 'confidence' => $confidence, 'action' => $action, 'sources' => $sources );
		if ( $reply_to ) {
			$meta['reply_to'] = absint( $reply_to );
		}
		$message_id = $repo->add_message(
			$conversation_id,
			'assistant',
			$reply,
			$meta
		);
		$repo->audit( $conversation_id, 'answered', array( 'confidence' => $confidence, 'action' => $action ) );
		return array(
			'reply'           => $reply,
			'sources'         => $sources,
			'confidence'      => $confidence,
			'action'          => $action,
			'conversation_id' => $conversation_id,
			'message_id'      => (int) $message_id,
		);
	}

	protected function is_injection( $text ) {
		if ( function_exists( 'pm_ai_is_injection_attempt' ) ) {
			return pm_ai_is_injection_attempt( $text );
		}
		return (bool) preg_match( '/ignore\s+previous\s+instructions|system\s*prompt|reveal.*(prompt|key|token)/i', $text );
	}

	protected function system_prompt() {
		if ( function_exists( 'pm_ai_get' ) ) {
			$custom = pm_ai_get( 'ai_system_prompt' );
			if ( $custom ) {
				return $custom;
			}
		}
		return 'تو دستیار آکادمی موسیقی پارسیان هستی. فقط بر اساس دانش تأییدشده پاسخ بده. قیمت، برنامه، ظرفیت یا وضعیت ثبت‌نام را حدس نزن؛ اگر مطمئن نیستی بگو و راه تماس معرفی کن.';
	}
}
