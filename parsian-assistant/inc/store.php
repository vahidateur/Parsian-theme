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
			privacy_subject char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY conversation (conversation_id),
			KEY privacy_subject (privacy_subject)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE " . pa_table( 'privacy_subjects' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subject_hash char(64) NOT NULL,
			wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY subject_hash (subject_hash),
			UNIQUE KEY wp_user (wp_user_id)
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
	foreach ( array( 'conversations', 'messages', 'events', 'processed_updates', 'link_tokens', 'privacy_subjects' ) as $short ) {
		$table = pa_table( $short );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $found !== $table ) {
			return false;
		}
	}
	return true;
}

/**
 * Resolve a stable, pseudonymous privacy subject for a WordPress user.
 *
 * The subject is random (not derived from the user ID), stored in a
 * non-autoloaded plugin table, and never written to logs. It survives
 * unlink/relink and is removed by erasure or when no message references it.
 *
 * @param int  $wp_user_id User ID.
 * @param bool $create      Create the mapping when absent.
 * @return string Subject hash or empty string.
 */
function pa_privacy_subject_for_user( $wp_user_id, $create = true ) {
	global $wpdb;
	$wp_user_id = absint( $wp_user_id );
	if ( ! $wp_user_id ) {
		return '';
	}
	$table = pa_table( 'privacy_subjects' );
	$subject = $wpdb->get_var(
		$wpdb->prepare( "SELECT subject_hash FROM {$table} WHERE wp_user_id = %d", $wp_user_id )
	);
	if ( is_string( $subject ) && '' !== $subject ) {
		return $subject;
	}
	if ( ! $create ) {
		return '';
	}
	try {
		$subject = bin2hex( random_bytes( 32 ) );
	} catch ( \Throwable $e ) {
		$subject = hash( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ) . wp_rand() );
	}
	$was_suppressed = $wpdb->suppress_errors( true );
	$inserted = $wpdb->insert(
		$table,
		array(
			'subject_hash' => $subject,
			'wp_user_id'   => $wp_user_id,
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		),
		array( '%s', '%d', '%s', '%s' )
	);
	$wpdb->suppress_errors( $was_suppressed );
	if ( false !== $inserted ) {
		return $subject;
	}
	// Concurrent first link: re-read the winner.
	$subject = $wpdb->get_var(
		$wpdb->prepare( "SELECT subject_hash FROM {$table} WHERE wp_user_id = %d", $wp_user_id )
	);
	return is_string( $subject ) ? $subject : '';
}

/**
 * Backfill privacy subjects for a user's currently linked conversations.
 * Idempotent; used by schema upgrade, export, erasure, and user deletion.
 *
 * @param int $wp_user_id User ID.
 * @return string Subject hash or empty string.
 */
function pa_privacy_backfill_user( $wp_user_id ) {
	global $wpdb;
	$wp_user_id = absint( $wp_user_id );
	if ( ! $wp_user_id ) {
		return '';
	}
	$ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE wp_user_id = %d", $wp_user_id )
	);
	$subject = pa_privacy_subject_for_user( $wp_user_id, false );
	if ( '' === $subject && empty( $ids ) ) {
		// No linked data and no existing pseudonymous mapping: do not
		// create an empty subject for a user who never used the assistant.
		return '';
	}
	$subject = pa_privacy_subject_for_user( $wp_user_id, true );
	if ( '' === $subject ) {
		return '';
	}
	foreach ( (array) $ids as $cid ) {
		pa_privacy_assign_conversation( $cid, $subject );
	}
	return $subject;
}

/**
 * Assign a privacy subject to messages in one conversation that do not
 * already carry one. Existing subjects are never overwritten, so a Bale
 * chat re-linked to another WordPress account cannot inherit the previous
 * account's history.
 *
 * @param int    $conversation_id Conversation ID.
 * @param string $subject         Privacy subject hash.
 */
function pa_privacy_assign_conversation( $conversation_id, $subject ) {
	global $wpdb;
	$conversation_id = absint( $conversation_id );
	$subject = (string) $subject;
	if ( ! $conversation_id || '' === $subject ) {
		return;
	}
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE " . pa_table( 'messages' ) . " SET privacy_subject = %s WHERE conversation_id = %d AND ( privacy_subject = '' OR privacy_subject IS NULL )",
			$subject,
			$conversation_id
		)
	);
}

/**
 * Backfill every conversation that still has an active WordPress user.
 */
function pa_privacy_backfill_all() {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT DISTINCT wp_user_id FROM " . pa_table( 'conversations' ) . " WHERE wp_user_id > 0" );
	foreach ( (array) $ids as $user_id ) {
		pa_privacy_backfill_user( (int) $user_id );
	}
}

/**
 * Erase all message content and privacy mappings for a WordPress user.
 * Used by the WP privacy eraser and by delete_user. Idempotent.
 *
 * @param int $wp_user_id User ID.
 * @return int Number of message rows removed.
 */
function pa_erase_user_data( $wp_user_id ) {
	global $wpdb;
	$wp_user_id = absint( $wp_user_id );
	if ( ! $wp_user_id ) {
		return 0;
	}
	$subject = pa_privacy_backfill_user( $wp_user_id );

	// Capture every conversation the subject appears in before deleting
	// messages. This includes conversations that were unlinked earlier and
	// now have no wp_user_id, which is exactly the privacy-erasure gap this
	// mapping exists to close.
	$conversation_ids = array();
	if ( '' !== $subject ) {
		$conversation_ids = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT conversation_id FROM " . pa_table( 'messages' ) . " WHERE privacy_subject = %s",
				$subject
			)
		);
	}
	$linked_ids = (array) $wpdb->get_col(
		$wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE wp_user_id = %d", $wp_user_id )
	);
	$conversation_ids = array_values( array_unique( array_map( 'absint', array_merge( $conversation_ids, $linked_ids ) ) ) );

	$removed = 0;
	if ( '' !== $subject ) {
		$removed = (int) $wpdb->delete( pa_table( 'messages' ), array( 'privacy_subject' => $subject ) );
	}

	foreach ( $conversation_ids as $cid ) {
		$wpdb->update(
			pa_table( 'conversations' ),
			array( 'wp_user_id' => 0, 'status' => 'closed', 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $cid ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d", $cid )
		);
		if ( 0 === $remaining ) {
			$wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $cid ) );
			$wpdb->delete( pa_table( 'conversations' ), array( 'id' => $cid ) );
		}
	}

	// Remove legacy audit payloads that embedded the numeric user id.
	$legacy_events = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, payload FROM " . pa_table( 'events' ) . " WHERE payload LIKE %s",
			'%' . $wpdb->esc_like( '"user"' ) . '%'
		)
	);
	foreach ( (array) $legacy_events as $event ) {
		$payload = json_decode( (string) $event->payload, true );
		if ( is_array( $payload ) && isset( $payload['user'] ) && (int) $payload['user'] === $wp_user_id ) {
			$wpdb->delete( pa_table( 'events' ), array( 'id' => (int) $event->id ) );
		}
	}

	$wpdb->delete( pa_table( 'link_tokens' ), array( 'wp_user_id' => $wp_user_id ) );
	$wpdb->delete( pa_table( 'privacy_subjects' ), array( 'wp_user_id' => $wp_user_id ) );
	return $removed;
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
		$was_suppressed = $wpdb->suppress_errors( true );
		$inserted = $wpdb->insert(
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
		$wpdb->suppress_errors( $was_suppressed );
		if ( false !== $inserted ) {
			$id = (int) $wpdb->insert_id;
		} else {
			// Concurrent first message: the UNIQUE key picked a winner.
			// Never return 0 — re-read the winning row instead.
			$id = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE channel = %s AND external_key = %s", $channel, $external_key )
			);
			if ( ! $id ) {
				return 0;
			}
		}
		if ( $wp_user_id ) {
			$this->link_user( $id, $wp_user_id );
		}
		return $id;
	}

	public function add_message( $conversation_id, $role, $text, array $meta = array() ) {
		global $wpdb;
		$conversation_id = absint( $conversation_id );
		if ( ! $conversation_id ) {
			return 0;
		}
		$role = in_array( $role, array( 'user', 'assistant', 'operator', 'system' ), true ) ? $role : 'user';
		$subject = '';
		$owner = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT wp_user_id FROM " . pa_table( 'conversations' ) . " WHERE id = %d", $conversation_id )
		);
		if ( $owner > 0 ) {
			$subject = pa_privacy_subject_for_user( $owner, true );
		}
		$inserted = $wpdb->insert(
			pa_table( 'messages' ),
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'text'            => mb_substr( (string) $text, 0, 4000 ),
				'meta'            => $meta ? wp_json_encode( $meta ) : null,
				'privacy_subject' => $subject,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return 0;
		}
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		$formats = array( '%s' );
		if ( 'user' === $role ) {
			// New user activity reopens a retained conversation. Escalated
			// conversations stay escalated for human handling.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE " . pa_table( 'conversations' ) . " SET status = 'open' WHERE id = %d AND status = 'closed'",
					$conversation_id
				)
			);
		}
		$wpdb->update(
			pa_table( 'conversations' ),
			$update,
			array( 'id' => $conversation_id ),
			$formats,
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

	/**
	 * Current conversation status ('' when the row does not exist).
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return string
	 */
	public function status( $conversation_id ) {
		global $wpdb;
		return (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM " . pa_table( 'conversations' ) . " WHERE id = %d", absint( $conversation_id ) )
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
		$conversation_id = absint( $conversation_id );
		$wp_user_id = absint( $wp_user_id );
		if ( ! $conversation_id || ! $wp_user_id ) {
			return;
		}
		$wpdb->update(
			pa_table( 'conversations' ),
			array( 'wp_user_id' => $wp_user_id, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $conversation_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		$subject = pa_privacy_subject_for_user( $wp_user_id, true );
		if ( '' !== $subject ) {
			pa_privacy_assign_conversation( $conversation_id, $subject );
		}
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
