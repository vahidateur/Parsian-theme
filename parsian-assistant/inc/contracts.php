<?php
/**
 * Versioned provider-independent contracts (PA v1).
 *
 * These interfaces are the port boundary for a future non-WordPress
 * backend (e.g. Laravel). The Bale adapter and the website popup both
 * program against these — never against theme functions directly.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single answer engine shared by the website popup and Bale.
 */
interface PA_AssistantService {
	/**
	 * Answer a user message inside a conversation.
	 *
	 * @param array $input {channel, conversation_id, text, user_id, locale}.
	 * @return array {reply, sources, confidence, action, conversation_id}.
	 */
	public function answer( array $input );
}

/**
 * Conversation + message + audit persistence.
 */
interface PA_ConversationRepository {
	public function find_or_create( $channel, $external_key, $wp_user_id = 0 );
	public function add_message( $conversation_id, $role, $text, array $meta = array() );
	public function history( $conversation_id, $limit = 30 );
	public function set_status( $conversation_id, $status );
	public function link_user( $conversation_id, $wp_user_id );
	public function audit( $conversation_id, $event, array $payload = array() );
}

/**
 * Trusted knowledge lookup (approved entries + FAQs only).
 */
interface PA_KnowledgeRetriever {
	/**
	 * @return array List of {title, text, url, origin}.
	 */
	public function retrieve( $query, $limit = 5 );
}

/**
 * Model invocation behind a stable signature.
 */
interface PA_AIProviderInterface {
	public function configured();
	public function complete( $system, $user_message, array $context_entries );
}

/**
 * A chat channel (website popup, Bale, …).
 */
interface PA_ChannelAdapterInterface {
	public function channel();
	public function send( $recipient_key, $text, array $options = array() );
	public function parse_incoming( $raw );
}

/**
 * Operator escalation path.
 */
interface PA_OperatorHandoffService {
	public function needs_operator( array $answer );
	public function handoff( $conversation_id, $reason );
}
