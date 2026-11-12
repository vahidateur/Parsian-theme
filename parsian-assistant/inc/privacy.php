<?php
/**
 * Data retention and privacy: pruning, export/erase, user deletion.
 *
 * Personal data collected: conversation text (user + assistant messages),
 * channel identifiers (Bale chat id / web session key), linked WP user id,
 * audit events (no message bodies). Nothing leaves the site database
 * except the configured AI endpoint (question + retrieved context) and
 * Bale sendMessage (reply text to the user's own chat).
 *
 * Pruning policy (documented in docs/DEPLOYMENT.md):
 * - Expired link tokens older than 7 days: deleted daily.
 * - Processed update ids older than 30 days: deleted daily.
 * - Closed (non-open) conversations older than pa_retention_days
 *   (default 90; 0 = keep explicitly): messages, events, then row.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pa_prune', 'pa_prune' );
add_action( 'delete_user', 'pa_user_deleted' );

/**
 * Schedule daily pruning.
 */
function pa_schedule_prune() {
	if ( ! wp_next_scheduled( 'pa_prune' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pa_prune' );
	}
}
add_action( 'init', 'pa_schedule_prune', 30 );

/**
 * Run retention pruning. Only deletes plugin-owned rows per policy.
 *
 * @return array Deleted counts.
 */
function pa_prune() {
	global $wpdb;
	$deleted = array( 'tokens' => 0, 'updates' => 0, 'conversations' => 0, 'messages' => 0, 'events' => 0, 'subjects' => 0, 'backups' => 0 );

	$deleted['tokens'] = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM " . pa_table( 'link_tokens' ) . " WHERE expires_at < %s",
			gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS )
		)
	);
	// created_at/updated_at use blog-local wall time; build the cutoff the same way.
	$old = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS );
	$deleted['updates'] = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM " . pa_table( 'processed_updates' ) . " WHERE created_at < %s",
			$old
		)
	);

	$retention_days = absint( get_option( 'pa_retention_days', 90 ) );
	if ( $retention_days > 0 ) {
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $retention_days * DAY_IN_SECONDS );
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM " . pa_table( 'conversations' ) . " WHERE status != 'open' AND updated_at < %s LIMIT 500",
				$cutoff
			)
		);
		foreach ( $ids as $id ) {
			$deleted['messages'] += (int) $wpdb->delete( pa_table( 'messages' ), array( 'conversation_id' => $id ) );
			$deleted['events'] += (int) $wpdb->delete( pa_table( 'events' ), array( 'conversation_id' => $id ) );
			if ( $wpdb->delete( pa_table( 'conversations' ), array( 'id' => $id ) ) ) {
				$deleted['conversations']++;
			}
		}
	}

	// Privacy subjects are pseudonymous retention metadata. Drop mappings
	// once neither messages nor an active account link reference them.
	$deleted['subjects'] = (int) $wpdb->query(
		"DELETE ps FROM " . pa_table( 'privacy_subjects' ) . " ps
		 WHERE NOT EXISTS ( SELECT 1 FROM " . pa_table( 'messages' ) . " m WHERE m.privacy_subject = ps.subject_hash )
		   AND NOT EXISTS ( SELECT 1 FROM " . pa_table( 'conversations' ) . " c WHERE c.wp_user_id = ps.wp_user_id )"
	);

	if ( function_exists( 'pa_migration_prune_backups' ) ) {
		$deleted['backups'] = (int) pa_migration_prune_backups();
	}

	return $deleted;
}

/**
 * On WordPress user deletion: perform immediate, documented erasure.
 *
 * The active account link and all message rows tied to the user's
 * pseudonymous privacy subject are removed, so account deletion cannot
 * leave personal history permanently undiscoverable. Support records are
 * not retained after account deletion; administrators who need a
 * time-limited support window must export data before deleting the user.
 *
 * @param int $user_id Deleted user ID.
 */
function pa_user_deleted( $user_id ) {
	global $wpdb;
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return;
	}
	$ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT id FROM " . pa_table( 'conversations' ) . " WHERE wp_user_id = %d", $user_id )
	);
	$repo = new PA_Conversation_Repository();
	foreach ( (array) $ids as $id ) {
		$repo->audit( (int) $id, 'user_deleted', array() );
	}
	pa_erase_user_data( $user_id );
}

add_filter( 'wp_privacy_personal_data_exporters', 'pa_register_data_exporter' );

/**
 * Export a user's assistant data (Tools → Export Personal Data).
 */
function pa_register_data_exporter( $exporters ) {
	$exporters['parsian-assistant'] = array(
		'exporter_friendly_name' => 'گفتگوهای دستیار پارسیان',
		'callback'               => 'pa_data_exporter',
	);
	return $exporters;
}

function pa_data_exporter( $email ) {
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		return array( 'data' => array(), 'done' => true );
	}
	global $wpdb;
	$subject = pa_privacy_backfill_user( $user->ID );
	$conversations = array();
	if ( '' !== $subject ) {
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT c.id, c.channel, c.status, c.created_at
				 FROM " . pa_table( 'conversations' ) . " c
				 INNER JOIN " . pa_table( 'messages' ) . " m ON m.conversation_id = c.id
				 WHERE m.privacy_subject = %s ORDER BY c.id ASC",
				$subject
			),
			ARRAY_A
		);
	}
	// Include a just-linked conversation that has no messages yet.
	$linked = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, channel, status, created_at FROM " . pa_table( 'conversations' ) . " WHERE wp_user_id = %d ORDER BY id ASC",
			$user->ID
		),
		ARRAY_A
	);
	$by_id = array();
	foreach ( array_merge( (array) $conversations, (array) $linked ) as $conversation ) {
		$by_id[ (int) $conversation['id'] ] = $conversation;
	}
	ksort( $by_id );

	$items = array();
	foreach ( $by_id as $conversation ) {
		if ( '' !== $subject ) {
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT role, text, created_at FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND privacy_subject = %s ORDER BY id ASC",
					$conversation['id'],
					$subject
				),
				ARRAY_A
			);
		} else {
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT role, text, created_at FROM " . pa_table( 'messages' ) . " WHERE conversation_id = %d AND ( privacy_subject = '' OR privacy_subject IS NULL ) ORDER BY id ASC",
					$conversation['id']
				),
				ARRAY_A
			);
		}
		$transcript = '';
		foreach ( (array) $messages as $message ) {
			$transcript .= '[' . $message['created_at'] . ' ' . $message['role'] . '] ' . $message['text'] . "\n";
		}
		$items[] = array(
			'group_id'    => 'pa-conversations',
			'group_label' => 'گفتگوهای دستیار',
			'item_id'     => 'pa-conversation-' . $conversation['id'],
			'data'        => array(
				array( 'name' => 'کانال', 'value' => $conversation['channel'] ),
				array( 'name' => 'وضعیت', 'value' => $conversation['status'] ),
				array( 'name' => 'متن گفتگو', 'value' => $transcript ),
			),
		);
	}
	return array( 'data' => $items, 'done' => true );
}

add_filter( 'wp_privacy_personal_data_erasers', 'pa_register_data_eraser' );

/**
 * Erase a user's assistant data (Tools → Erase Personal Data).
 */
function pa_register_data_eraser( $erasers ) {
	$erasers['parsian-assistant'] = array(
		'eraser_friendly_name' => 'گفتگوهای دستیار پارسیان',
		'callback'             => 'pa_data_eraser',
	);
	return $erasers;
}

function pa_data_eraser( $email ) {
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		return array( 'items_removed' => 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
	$removed = pa_erase_user_data( $user->ID );
	return array(
		'items_removed'  => $removed,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}
