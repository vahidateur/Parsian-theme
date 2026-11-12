<?php
/**
 * REST API: pa/v1 — webhook, linking, conversations, operations.
 *
 * Versioned contract surface for the future Laravel boundary. All routes
 * use explicit permission callbacks; the webhook is protected by the
 * secret path segment.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'pa_register_routes' );

/**
 * Register routes.
 */
function pa_register_routes() {
	register_rest_route(
		'pa/v1',
		'/bale/(?P<secret>[A-Za-z0-9]+)',
		array(
			'methods'             => 'POST',
			'callback'            => 'pa_rest_bale_webhook',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'pa/v1',
		'/chat',
		array(
			'methods'             => 'POST',
			'callback'            => 'pa_rest_chat',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'pa/v1',
		'/link-token',
		array(
			'methods'             => 'POST',
			'callback'            => 'pa_rest_link_token',
			'permission_callback' => 'is_user_logged_in',
		)
	);
	register_rest_route(
		'pa/v1',
		'/conversations',
		array(
			'methods'             => 'GET',
			'callback'            => 'pa_rest_conversations',
			'permission_callback' => 'pa_can_manage_assistant',
		)
	);
	register_rest_route(
		'pa/v1',
		'/status',
		array(
			'methods'             => 'GET',
			'callback'            => 'pa_rest_status',
			'permission_callback' => 'pa_can_manage_assistant',
		)
	);
}

/**
 * Capability gate for assistant management.
 */
function pa_can_manage_assistant() {
	return current_user_can( 'manage_options' );
}

/**
 * Webhook entry: validate secret, persist fast, defer slow AI work.
 * The AI answer and reply run in a cron worker so Bale always gets a
 * prompt acknowledgement after the incoming update is safely stored.
 */
function pa_rest_bale_webhook( $request ) {
	require_once PA_DIR . 'inc/linking.php';
	$secret = (string) $request->get_param( 'secret' );
	if ( '' === $secret || ! hash_equals( (string) pa_webhook_secret(), $secret ) ) {
		return new WP_Error( 'pa_webhook_auth', 'Forbidden.', array( 'status' => 403 ) );
	}
	$raw = $request->get_json_params();
	if ( ! is_array( $raw ) || empty( $raw ) ) {
		$body = (string) $request->get_body();
		$raw = json_decode( $body, true );
	}
	if ( ! is_array( $raw ) ) {
		return new WP_Error( 'pa_webhook_payload', 'Invalid payload.', array( 'status' => 400 ) );
	}
	$adapter = new PA_Bale_Adapter();
	$parsed = $adapter->parse_incoming( $raw );
	if ( ! $parsed ) {
		return rest_ensure_response( array( 'ok' => true, 'ignored' => true ) );
	}
	if ( ! pa_claim_update( $parsed['update_id'] ) ) {
		return rest_ensure_response( array( 'ok' => true, 'duplicate' => true ) );
	}

	$text = $parsed['text'];
	if ( 0 === strpos( $text, '/start' ) ) {
		$token = pa_start_param_token( $text );
		if ( '' !== $token ) {
			$linked = pa_link_token_consume( $token, $parsed['chat_id'] );
			if ( is_wp_error( $linked ) ) {
				pa_defer_bale_reply( $parsed['chat_id'], 'توکن پیوند معتبر نیست یا منقضی شده است.' );
			} else {
				pa_defer_bale_reply( $parsed['chat_id'], 'حساب شما با موفقیت به آکادمی متصل شد.' );
			}
			return rest_ensure_response( array( 'ok' => true ) );
		}
	}
	if ( '/unlink' === trim( $text ) ) {
		pa_link_remove( $parsed['chat_id'] );
		pa_defer_bale_reply( $parsed['chat_id'], 'پیوند حساب شما لغو شد.' );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	$stored = pa_store_bale_update( $parsed );
	if ( is_wp_error( $stored ) ) {
		// The update was claimed before persistence. Release it so the
		// provider's retry is not discarded as a duplicate.
		pa_release_update( $parsed['update_id'] );
		return $stored;
	}
	return rest_ensure_response( array( 'ok' => true ) );
}

/**
 * Website popup entry to the shared engine. Public with WP nonce +
 * per-IP rate limit (mirrors the theme popup: 30 answers/hour).
 * Session continuity via client-supplied sid (opaque, random).
 */
function pa_rest_chat( $request ) {
	$nonce = (string) $request->get_header( 'X-WP-Nonce' );
	if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'pa_chat_nonce', 'نشست منقضی شده است؛ صفحه را دوباره بارگذاری کنید.', array( 'status' => 403 ) );
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$rl_key = 'pa_rl_web_' . md5( $ip );
	$count = (int) get_transient( $rl_key );
	if ( $count >= 30 ) {
		return new WP_Error( 'pa_chat_rate', 'تعداد درخواست‌ها زیاد است؛ لطفاً کمی بعد دوباره تلاش کنید.', array( 'status' => 429 ) );
	}
	$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
	if ( mb_strlen( $message ) < 2 ) {
		return new WP_Error( 'pa_chat_message', 'سوال خود را بنویسید.', array( 'status' => 400 ) );
	}
	if ( mb_strlen( $message ) > 1200 ) {
		$message = mb_substr( $message, 0, 1200 );
	}
	$sid = (string) $request->get_param( 'sid' );
	if ( ! preg_match( '/^[A-Za-z0-9]{8,64}$/', $sid ) ) {
		$sid = wp_generate_password( 24, false, false );
	}
	set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );
	$service = new PA_Assistant_Service();
	$answer = $service->answer(
		array(
			'channel'      => 'web',
			'external_key' => 'web:' . $sid,
			'user_id'      => get_current_user_id(),
			'text'         => $message,
		)
	);
	$answer['sid'] = $sid;
	return rest_ensure_response( $answer );
}

/**
 * Create a one-time link token for the current user.
 */
function pa_rest_link_token( $request ) {
	require_once PA_DIR . 'inc/linking.php';
	$token = pa_link_token_create( get_current_user_id() );
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$bot = pa_bale_bot_username();
	return rest_ensure_response(
		array(
			'token'      => $token,
			'expires_in' => PA_LINK_TTL,
			'bot'        => $bot,
			'start_param' => 'link_' . $token,
		)
	);
}

/**
 * Unlink the current user's Bale identity is not possible without the
 * chat id; this endpoint removes the binding for a chat id the admin
 * provides. Kept admin-gated via conversations UI instead.
 */
function pa_rest_link_remove( $request ) {
	return new WP_Error( 'pa_link_remove', 'Use the admin conversations screen.', array( 'status' => 405 ) );
}

/**
 * Conversation list for admins.
 */
function pa_rest_conversations( $request ) {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT id, channel, external_key, wp_user_id, status, updated_at FROM " . pa_table( 'conversations' ) . " ORDER BY updated_at DESC LIMIT 100",
		ARRAY_A
	);
	return rest_ensure_response( array( 'conversations' => $rows ? $rows : array() ) );
}

/**
 * Connection + queue status for admins. Ordinary reads use the cached
 * summary; pass ?refresh=1 for an explicit getMe/getWebhookInfo check.
 */
function pa_rest_status( $request ) {
	$status = $request && $request->get_param( 'refresh' ) ? pa_bale_status_refresh() : pa_bale_status_cached();
	global $wpdb;
	$counts = $wpdb->get_results(
		"SELECT status, COUNT(*) c FROM " . pa_table( 'conversations' ) . " GROUP BY status",
		ARRAY_A
	);
	return rest_ensure_response(
		array(
			'bale_configured' => ! empty( $status['configured'] ),
			'bale_status'     => $status,
			'bale_getme'      => array(
				'success' => ! empty( $status['getme_ok'] ),
				'message' => isset( $status['getme_message'] ) ? $status['getme_message'] : '',
			),
			'bale_webhook'    => array(
				'success' => ! empty( $status['webhook_ok'] ),
				'message' => isset( $status['webhook_message'] ) ? $status['webhook_message'] : '',
			),
			'webhook_url'     => pa_webhook_url(),
			'conversations'   => $counts ? $counts : array(),
			'operator_queue'  => pa_operator_queue( 20 ),
			'operator_pending' => function_exists( 'pa_operator_pending_count' ) ? pa_operator_pending_count() : 0,
		)
	);
}

/**
 * Bot username for deep links (from getMe cache or setting).
 *
 * @param bool $live Allow a live getMe lookup on cache miss. Must stay
 *                   false on frontend paths to avoid blocking page loads.
 */
function pa_bale_bot_username( $live = true ) {
	$cached = get_option( 'pa_bale_bot_username', '' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}
	if ( ! $live ) {
		return '';
	}
	$adapter = new PA_Bale_Adapter();
	if ( ! $adapter->configured() ) {
		return '';
	}
	return pa_bale_remember_identity( $adapter->get_me() );
}
