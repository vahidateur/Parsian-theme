<?php
/**
 * Conversation store: tables, install, repository.
 *
 * Tables are plugin-owned and theme-independent. All writes use $wpdb
 * prepared statements. No theme data is read or written here.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table names.
 */
function pa_table( $short ) {
	global $wpdb;
	return $wpdb->prefix . 'pa_' . $short;
}

/**
 * Create tables (create-only; never alters user rows).
 */
function pa_store_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE " . pa_table( 'conversations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			channel varchar(20) NOT NULL DEFAULT 'web',
			external_key varchar(190) NOT NULL DEFAULT '',
			wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY channel_external (channel, external_key),
			KEY wp_user (wp_user_id)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE " . pa_table( 'messages' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(10) NOT NULL DEFAULT 'user',
			text longtext NOT NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY conversation (conversation_id)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE " . pa_table( 'events' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event varchar(60) NOT NULL,
			payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY conversation (conversation_id)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE " . pa_table( 'processed_updates' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(20) NOT NULL DEFAULT 'bale',
			update_id varchar(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY provider_update (provider, update_id)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE " . pa_table( 'link_tokens' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			wp_user_id bigint(20) unsigned NOT NULL,
			expires_at datetime NOT NULL,
			used_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_hash (token_hash),
			KEY wp_user (wp_user_id)
		) $charset;"
	);
	update_option( 'pa_db_version', PA_DB_VERSION, false );
}

/**
 * Whether all plugin tables exist.
 *
 * @return bool
 */
function pa_store_ready() {
	global $wpdb;
	foreach ( array( 'conversations', 'messages', 'events', 'processed_updates', 'link_tokens' ) as $short ) {
		$table = pa_table( $short );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $found !== $table ) {
			return false;
		}
	}
	return true;
}

/**
 * Repository implementation.
 */
class PA_Conversation_Repository implements PA_ConversationRepository {
	public function find_or_create( $channel, $external_key, $wp_user_id = 0 ) {
		global $wpdb;
		$table = pa_table( 'conversations' );
		$channel = sanitize_key( $channel );
		$external_key = sanitize_text_field( (string) $external_key );
		$id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE channel = %s AND external_key = %s", $channel, $external_key )
		);
		if ( $id ) {
			if ( $wp_user_id ) {
				$this->link_user( $id, $wp_user_id );
			}
			return $id;
		}
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			array(
				'channel'      => $channel,
				'external_key' => $external_key,
				'wp_user_id'   => absint( $wp_user_id ),
				'status'       => 'open',
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public function add_message( $conversation_id, $role, $text, array $meta = array() ) {
		global $wpdb;
		$role = in_array( $role, array( 'user', 'assistant', 'operator', 'system' ), true ) ? $role : 'user';
		$wpdb->insert(
			pa_table( 'messages' ),
			array(
				'conversation_id' => absint( $conversation_id ),
				'role'            => $role,
				'text'            => mb_substr( (string) $text, 0, 4000 ),
				'meta'            => $meta ? wp_json_encode( $meta ) : null,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		$wpdb->update(
			pa_table( 'conversations' ),
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $conversation_id ) ),
			array( '%s' ),
			array( '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public function history( $conversation_id, $limit = 30 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, text, meta, created_at FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d ORDER BY id DESC LIMIT %d",
				absint( $conversation_id ),
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public function set_status( $conversation_id, $status ) {
		global $wpdb;
		$status = sanitize_key( $status );
		$wpdb->update(
			pa_table( 'conversations' ),
			array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $conversation_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public function link_user( $conversation_id, $wp_user_id ) {
		global $wpdb;
		$wpdb->update(
			pa_table( 'conversations' ),
			array( 'wp_user_id' => absint( $wp_user_id ), 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $conversation_id ) ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public function audit( $conversation_id, $event, array $payload = array() ) {
		global $wpdb;
		$payload = pa_redact( $payload );
		$wpdb->insert(
			pa_table( 'events' ),
			array(
				'conversation_id' => absint( $conversation_id ),
				'event'           => sanitize_key( substr( (string) $event, 0, 60 ) ),
				'payload'         => $payload ? wp_json_encode( $payload ) : null,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}
}

/**
 * Redact secrets from anything that might be logged.
 *
 * @param mixed $data Data.
 * @return mixed
 */
function pa_redact( $data ) {
	if ( is_array( $data ) ) {
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && preg_match( '/token|api[_-]?key|secret|password|pass/i', $key ) ) {
				$out[ $key ] = '••••••';
				continue;
			}
			$out[ $key ] = pa_redact( $value );
		}
		return $out;
	}
	if ( is_string( $data ) ) {
		$data = preg_replace( '/sk-[A-Za-z0-9_-]{8,}/', '••••••', $data );
		$data = preg_replace( '/bot[0-9A-Za-z:_-]{12,}/', 'bot••••••', $data );
		return $data;
	}
	return $data;
}
