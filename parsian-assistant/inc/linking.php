<?php
/**
 * Optional Bale account linking: one-time expiring tokens.
 *
 * Flow: authenticated user requests a link -> backend creates a
 * cryptographically secure one-time token -> user opens the bot deep link
 * (t.me-style start parameter) -> webhook validates + consumes the token ->
 * Bale identity links to the academy account -> confirmation message.
 * No linking on display name or phone number alone.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PA_LINK_TTL', 15 * MINUTE_IN_SECONDS );

/**
 * Create a one-time link token for the current user.
 *
 * @param int $wp_user_id User ID.
 * @return string|WP_Error Plain token (shown once) or error.
 */
function pa_link_token_create( $wp_user_id ) {
	global $wpdb;
	$wp_user_id = absint( $wp_user_id );
	if ( ! $wp_user_id || ! get_userdata( $wp_user_id ) ) {
		return new WP_Error( 'pa_link_user', 'کاربر معتبر نیست.' );
	}
	$active = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM " . pa_table( 'link_tokens' ) . " WHERE wp_user_id = %d AND used_at IS NULL AND expires_at > %s",
			$wp_user_id,
			gmdate( 'Y-m-d H:i:s' )
		)
	);
	if ( $active >= 5 ) {
		return new WP_Error( 'pa_link_limit', 'سقف توکن فعال پر است؛ از توکن قبلی استفاده کنید.' );
	}
	$token = wp_generate_password( 24, false, false );
	$hash = hash( 'sha256', $token );
	$now = current_time( 'mysql' );
	$wpdb->insert(
		pa_table( 'link_tokens' ),
		array(
			'token_hash' => $hash,
			'wp_user_id' => $wp_user_id,
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + PA_LINK_TTL ),
			'used_at'    => null,
			'created_at' => $now,
		),
		array( '%s', '%d', '%s', '%s', '%s' )
	);
	return $token;
}

/**
 * Validate + consume a link token, binding the Bale identity.
 *
 * @param string $token Plain token from the start parameter.
 * @param string $bale_chat_id Bale chat id to bind.
 * @return int|WP_Error WP user id or error.
 */
function pa_link_token_consume( $token, $bale_chat_id ) {
	global $wpdb;
	$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
	$bale_chat_id = sanitize_text_field( (string) $bale_chat_id );
	if ( '' === $token || '' === $bale_chat_id ) {
		return new WP_Error( 'pa_link_invalid', 'توکن پیوند معتبر نیست.' );
	}
	$table = pa_table( 'link_tokens' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", hash( 'sha256', $token ) ) );
	if ( ! $row ) {
		return new WP_Error( 'pa_link_invalid', 'توکن پیوند معتبر نیست.' );
	}
	if ( null !== $row->used_at ) {
		return new WP_Error( 'pa_link_reused', 'این توکن قبلاً استفاده شده است.' );
	}
	if ( strtotime( $row->expires_at ) < time() ) {
		return new WP_Error( 'pa_link_expired', 'توکن منقضی شده است؛ توکن جدید بسازید.' );
	}
	$now = current_time( 'mysql' );
	$affected = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} SET used_at = %s WHERE id = %d AND used_at IS NULL",
			$now,
			$row->id
		)
	);
	if ( ! $affected ) {
		return new WP_Error( 'pa_link_reused', 'این توکن قبلاً استفاده شده است.' );
	}
	$repo = new PA_Conversation_Repository();
	$conversation_id = $repo->find_or_create( 'bale', 'bale:' . $bale_chat_id, (int) $row->wp_user_id );
	$repo->audit( $conversation_id, 'account_linked', array( 'user' => (int) $row->wp_user_id ) );
	return (int) $row->wp_user_id;
}

/**
 * Unlink a Bale identity from the academy account.
 */
function pa_link_remove( $bale_chat_id ) {
	$repo = new PA_Conversation_Repository();
	$conversation_id = $repo->find_or_create( 'bale', 'bale:' . sanitize_text_field( (string) $bale_chat_id ), 0 );
	global $wpdb;
	$wpdb->update(
		pa_table( 'conversations' ),
		array( 'wp_user_id' => 0, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => $conversation_id ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	$repo->audit( $conversation_id, 'account_unlinked', array() );
}

/**
 * Extract a link token from a /start payload ("...link_<token>").
 *
 * @return string Empty when absent.
 */
function pa_start_param_token( $text ) {
	if ( preg_match( '/link_([A-Za-z0-9]{8,})/', (string) $text, $matches ) ) {
		return $matches[1];
	}
	return '';
}
