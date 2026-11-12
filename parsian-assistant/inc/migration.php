<?php
/**
 * Legacy theme settings migration (v1.6.0 randomized slug -> canonical).
 *
 * Detects theme_mods_{stylesheet} rows left by v1.6.0 installs whose
 * directory had a randomized suffix, previews them, backs them up, and
 * copies only schema-supported non-secret keys into
 * theme_mods_parsian-music. Never deletes the source installation or its
 * option. Never runs on frontend page loads — admin action only,
 * capability + nonce gated.
 *
 * Snapshots are allowlisted: only fields that the current theme schema
 * marks as non-secret are stored. Credentials are never copied into a
 * backup option and are never migrated from an untrusted legacy option;
 * administrators provision them through the secure settings flow.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PA_CANONICAL_SLUG', 'parsian-music' );
define( 'PA_MIGRATION_BACKUP_DAYS', 30 );
define( 'PA_MIGRATION_BACKUP_MAX', 10 );

/**
 * Sanitize a theme stylesheet slug without lowercasing it.
 * sanitize_key() would break legacy slugs containing capitals.
 *
 * @param string $slug Slug.
 * @return string
 */
function pa_sanitize_slug( $slug ) {
	$slug = (string) $slug;
	$slug = preg_replace( '/[^A-Za-z0-9_-]/', '', $slug );
	return substr( $slug, 0, 100 );
}

/**
 * Find candidate legacy theme-mod options.
 *
 * @return array slug => array(option, keys, count).
 */
function pa_migration_candidates() {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'theme_mods_parsian%'"
	);
	$out = array();
	foreach ( (array) $rows as $row ) {
		$slug = substr( $row->option_name, strlen( 'theme_mods_' ) );
		if ( PA_CANONICAL_SLUG === $slug ) {
			continue;
		}
		$mods = get_option( $row->option_name, array() );
		if ( ! is_array( $mods ) ) {
			continue;
		}
		$mods = array_filter(
			$mods,
			function ( $value, $key ) {
				return 0 !== strpos( (string) $key, '_' ) && '' !== $value && array() !== $value;
			},
			ARRAY_FILTER_USE_BOTH
		);
		if ( empty( $mods ) ) {
			continue;
		}
		$out[ $slug ] = array(
			'option' => $row->option_name,
			'keys'   => array_keys( $mods ),
			'count'  => count( $mods ),
			'mods'   => $mods,
		);
	}
	return $out;
}

/**
 * Current theme schema fields (id => type). Null when unavailable.
 *
 * @return array|null
 */
function pa_migration_schema_fields() {
	static $fields = null;
	if ( null !== $fields ) {
		return $fields;
	}
	if ( ! function_exists( 'pm_settings_schema' ) ) {
		return null;
	}
	$fields = array();
	foreach ( pm_settings_schema() as $tab ) {
		if ( empty( $tab['sections'] ) ) {
			continue;
		}
		foreach ( $tab['sections'] as $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field_id => $field ) {
				$id = is_string( $field_id ) ? $field_id : ( isset( $field['id'] ) ? $field['id'] : '' );
				if ( '' === $id ) {
					continue;
				}
				$fields[ $id ] = isset( $field['type'] ) ? (string) $field['type'] : '';
			}
		}
	}
	return $fields;
}

/**
 * Allowlisted destination keys from the current theme schema.
 *
 * @return array|null Null when the theme schema is unavailable.
 */
function pa_migration_schema_keys() {
	$fields = pa_migration_schema_fields();
	return null === $fields ? null : array_keys( $fields );
}

/**
 * Whether a key is a credential/secret and must never enter a backup.
 *
 * @param string     $key    Theme-mod key.
 * @param array|null $fields Schema fields; resolved when null.
 * @return bool
 */
function pa_migration_is_secret_key( $key, $fields = null ) {
	$key = (string) $key;
	if ( null === $fields ) {
		$fields = pa_migration_schema_fields();
	}
	if ( is_array( $fields ) && isset( $fields[ $key ] ) ) {
		$type = strtolower( (string) $fields[ $key ] );
		if ( in_array( $type, array( 'secret', 'password', 'token', 'api_key' ), true ) ) {
			return true;
		}
	}
	return (bool) preg_match( '/token|api[_-]?key|secret|password|passwd|private[_-]?key|signing[_-]?key|credential|auth[_-]?key|webhook/i', $key );
}

/**
 * Reviewed allowlist of non-secret schema keys for backup snapshots.
 *
 * @return array|null Null when the theme schema is unavailable.
 */
function pa_migration_safe_keys() {
	$fields = pa_migration_schema_fields();
	if ( null === $fields ) {
		return null;
	}
	$safe = array();
	foreach ( $fields as $key => $type ) {
		if ( ! pa_migration_is_secret_key( $key, $fields ) ) {
			$safe[] = $key;
		}
	}
	return $safe;
}

/**
 * Remove secret keys recursively while preserving every non-secret value.
 *
 * @param array $mods Theme mods.
 * @return array
 */
function pa_migration_strip_secrets( $mods ) {
	$out = array();
	foreach ( (array) $mods as $key => $value ) {
		if ( is_string( $key ) && pa_migration_is_secret_key( $key ) ) {
			continue;
		}
		$out[ $key ] = is_array( $value ) ? pa_migration_strip_secrets( $value ) : $value;
	}
	return $out;
}

/**
 * Build an allowlisted backup snapshot. Unknown keys are omitted when the
 * theme schema is available; without a schema only secret-like keys are
 * dropped, which is the conservative fallback.
 *
 * @param array $mods Theme mods.
 * @return array
 */
function pa_migration_safe_snapshot( $mods ) {
	$safe = pa_migration_safe_keys();
	$out = array();
	foreach ( (array) $mods as $key => $value ) {
		if ( ! is_string( $key ) || 0 === strpos( $key, '_' ) ) {
			continue;
		}
		if ( pa_migration_is_secret_key( $key ) ) {
			continue;
		}
		if ( null !== $safe && ! in_array( $key, $safe, true ) ) {
			continue;
		}
		$out[ $key ] = is_array( $value ) ? pa_migration_strip_secrets( $value ) : $value;
	}
	return $out;
}

/**
 * Build a migration preview without changing anything.
 *
 * @param string $source_slug Legacy stylesheet slug.
 * @return array|WP_Error Preview structure.
 */
function pa_migration_preview( $source_slug ) {
	$source_slug = pa_sanitize_slug( $source_slug );
	$candidates = pa_migration_candidates();
	if ( ! isset( $candidates[ $source_slug ] ) ) {
		return new WP_Error( 'pa_migration_source', 'منبع مهاجرت پیدا نشد.' );
	}
	$schema_keys = pa_migration_schema_keys();
	$dest_mods = get_option( 'theme_mods_' . PA_CANONICAL_SLUG, array() );
	if ( ! is_array( $dest_mods ) ) {
		$dest_mods = array();
	}
	$supported = array();
	$unsupported = array();
	$conflicts = array();
	$secrets = array();
	foreach ( $candidates[ $source_slug ]['mods'] as $key => $value ) {
		if ( pa_migration_is_secret_key( $key ) ) {
			$secrets[] = $key;
			continue;
		}
		if ( is_array( $schema_keys ) && ! in_array( $key, $schema_keys, true ) ) {
			$unsupported[] = $key;
			continue;
		}
		$has_dest = array_key_exists( $key, $dest_mods ) && '' !== $dest_mods[ $key ] && array() !== $dest_mods[ $key ];
		if ( $has_dest && $dest_mods[ $key ] !== $value ) {
			$conflicts[] = $key;
		}
		$supported[] = $key;
	}
	return array(
		'source'        => $source_slug,
		'source_option' => $candidates[ $source_slug ]['option'],
		'destination'   => PA_CANONICAL_SLUG,
		'supported'     => $supported,
		'unsupported'   => $unsupported,
		'conflicts'     => $conflicts,
		'secrets'       => $secrets,
		'policy'        => 'fill-empty',
	);
}

/**
 * Run the migration. Idempotent. Preserves the source option untouched.
 *
 * @param string $source_slug Legacy stylesheet slug.
 * @param bool   $force Re-run even when a completed record exists.
 * @return array|WP_Error Report {migrated, skipped_conflicts, preserved_unsupported, backup}.
 */
function pa_migration_run( $source_slug, $force = false ) {
	$source_slug = pa_sanitize_slug( $source_slug );
	$preview = pa_migration_preview( $source_slug );
	if ( is_wp_error( $preview ) ) {
		return $preview;
	}
	$record_key = 'pa_migration_done_' . md5( $source_slug );
	if ( ! $force ) {
		$record = get_option( $record_key, null );
		if ( is_array( $record ) && ! empty( $record['completed'] ) ) {
			$still_ok = true;
			$dest_mods = get_option( 'theme_mods_' . PA_CANONICAL_SLUG, array() );
			foreach ( (array) $record['migrated'] as $key ) {
				if ( ! array_key_exists( $key, (array) $dest_mods ) ) {
					$still_ok = false;
					break;
				}
			}
			if ( $still_ok ) {
				$record['idempotent'] = true;
				return $record;
			}
		}
	}

	$source_option = $preview['source_option'];
	$source_mods = get_option( $source_option, array() );
	$dest_option = 'theme_mods_' . PA_CANONICAL_SLUG;
	$dest_before = get_option( $dest_option, array() );
	if ( ! is_array( $dest_before ) ) {
		$dest_before = array();
	}

	$source_snapshot = pa_migration_safe_snapshot( $source_mods );
	$dest_snapshot = pa_migration_safe_snapshot( $dest_before );
	$backup_id = 'pa_migration_backup_' . gmdate( 'YmdHis' ) . '_' . substr( md5( $source_slug ), 0, 6 );
	update_option(
		$backup_id,
		array(
			'source_slug'      => $source_slug,
			'source_option'    => $source_option,
			'source_mods'      => $source_snapshot,
			'dest_before'      => $dest_snapshot,
			'dest_keys_before' => array_keys( $dest_snapshot ),
			'created_at'       => current_time( 'mysql' ),
		),
		false
	);

	$dest_mods = $dest_before;
	$migrated = array();
	$skipped = array();
	foreach ( $preview['supported'] as $key ) {
		if ( ! array_key_exists( $key, $source_mods ) ) {
			continue;
		}
		$value = $source_mods[ $key ];
		$has_dest = array_key_exists( $key, $dest_mods ) && '' !== $dest_mods[ $key ] && array() !== $dest_mods[ $key ];
		if ( $has_dest && $dest_mods[ $key ] !== $value ) {
			$skipped[] = $key;
			continue;
		}
		$dest_mods[ $key ] = $value;
		$migrated[] = $key;
	}
	update_option( $dest_option, $dest_mods );

	$read_back = get_option( $dest_option, array() );
	$verified = true;
	foreach ( $migrated as $key ) {
		if ( ! array_key_exists( $key, (array) $read_back ) || $read_back[ $key ] !== $dest_mods[ $key ] ) {
			$verified = false;
			break;
		}
	}

	$report = array(
		'completed'             => $verified,
		'source'                => $source_slug,
		'migrated'              => $migrated,
		'skipped_conflicts'     => $skipped,
		'preserved_unsupported' => $preview['unsupported'],
		'secrets_skipped'       => $preview['secrets'],
		'backup'                => $backup_id,
		'source_preserved'      => true,
	);
	update_option( $record_key, $report, false );
	pa_migration_prune_backups();
	return $report;
}

/**
 * Restore non-secret destination values from a backup while preserving
 * currently configured credentials. A redacted/empty backup value can
 * never overwrite a live token or API key.
 *
 * @param string $backup_id Backup option name.
 * @return bool|WP_Error
 */
function pa_migration_restore( $backup_id ) {
	$backup_id = sanitize_key( $backup_id );
	if ( 0 !== strpos( $backup_id, 'pa_migration_backup_' ) ) {
		return new WP_Error( 'pa_migration_backup', 'پشتیبان معتبر نیست.' );
	}
	$backup = get_option( $backup_id, null );
	if ( ! is_array( $backup ) || ! isset( $backup['dest_before'] ) || ! is_array( $backup['dest_before'] ) ) {
		return new WP_Error( 'pa_migration_backup', 'پشتیبان پیدا نشد.' );
	}
	$dest_option = 'theme_mods_' . PA_CANONICAL_SLUG;
	$current = get_option( $dest_option, array() );
	if ( ! is_array( $current ) ) {
		$current = array();
	}
	$restored = $current;
	$safe = pa_migration_safe_keys();
	$known = isset( $backup['dest_keys_before'] ) && is_array( $backup['dest_keys_before'] )
		? $backup['dest_keys_before']
		: array_keys( $backup['dest_before'] );

	foreach ( array_keys( $current ) as $key ) {
		if ( ! is_string( $key ) || 0 === strpos( $key, '_' ) || pa_migration_is_secret_key( $key ) ) {
			continue;
		}
		if ( null !== $safe && ! in_array( $key, $safe, true ) ) {
			continue;
		}
		if ( ! in_array( $key, $known, true ) ) {
			unset( $restored[ $key ] );
		}
	}
	foreach ( $backup['dest_before'] as $key => $value ) {
		if ( ! is_string( $key ) || pa_migration_is_secret_key( $key ) ) {
			continue;
		}
		if ( null !== $safe && ! in_array( $key, $safe, true ) ) {
			continue;
		}
		$restored[ $key ] = is_array( $value ) ? pa_migration_strip_secrets( $value ) : $value;
	}
	update_option( $dest_option, $restored );
	$source_slug = isset( $backup['source_slug'] ) ? (string) $backup['source_slug'] : '';
	delete_option( 'pa_migration_done_' . md5( $source_slug ) );
	return true;
}

/**
 * Delete expired migration backups and bound the number retained.
 *
 * @param int $retention_days Default 30; 0 disables age expiry.
 * @param int $max_backups    Default 10.
 * @return int Number deleted.
 */
function pa_migration_prune_backups( $retention_days = PA_MIGRATION_BACKUP_DAYS, $max_backups = PA_MIGRATION_BACKUP_MAX ) {
	global $wpdb;
	$retention_days = absint( $retention_days );
	$max_backups = max( 1, absint( $max_backups ) );
	$names = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'pa_migration_backup_%'"
	);
	$keep = array();
	$deleted = 0;
	$cutoff = $retention_days > 0 ? time() - $retention_days * DAY_IN_SECONDS : 0;
	foreach ( (array) $names as $name ) {
		$data = get_option( $name, null );
		$created = 0;
		if ( is_array( $data ) && ! empty( $data['created_at'] ) ) {
			$created = (int) strtotime( (string) $data['created_at'] );
		}
		if ( ! $created && preg_match( '/^pa_migration_backup_(\d{14})_/', (string) $name, $matches ) ) {
			$date = \DateTime::createFromFormat( 'YmdHis', $matches[1], new \DateTimeZone( 'UTC' ) );
			if ( $date ) {
				$created = $date->getTimestamp();
			}
		}
		if ( $cutoff && $created && $created < $cutoff ) {
			delete_option( $name );
			$deleted++;
			continue;
		}
		$keep[] = array( 'name' => $name, 'created' => $created );
	}
	usort(
		$keep,
		function ( $a, $b ) {
			return $b['created'] <=> $a['created'];
		}
	);
	foreach ( array_slice( $keep, $max_backups ) as $old ) {
		delete_option( $old['name'] );
		$deleted++;
	}
	return $deleted;
}

/**
 * Remove secrets from backups created before this policy and apply
 * retention. Values are never logged. Idempotent.
 *
 * @return int Number of backup options changed.
 */
function pa_migration_sanitize_legacy_backups() {
	global $wpdb;
	$names = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'pa_migration_backup_%'"
	);
	$changed = 0;
	foreach ( (array) $names as $name ) {
		$data = get_option( $name, null );
		if ( ! is_array( $data ) ) {
			continue;
		}
		$new = $data;
		foreach ( array( 'source_mods', 'dest_before' ) as $field ) {
			if ( isset( $new[ $field ] ) && is_array( $new[ $field ] ) ) {
				$new[ $field ] = pa_migration_strip_secrets( $new[ $field ] );
			}
		}
		if ( ! isset( $new['dest_keys_before'] ) && isset( $new['dest_before'] ) && is_array( $new['dest_before'] ) ) {
			$new['dest_keys_before'] = array_keys( $new['dest_before'] );
		}
		if ( empty( $new['created_at'] ) && preg_match( '/^pa_migration_backup_(\d{14})_/', (string) $name, $matches ) ) {
			$date = \DateTime::createFromFormat( 'YmdHis', $matches[1], new \DateTimeZone( 'UTC' ) );
			if ( $date ) {
				$new['created_at'] = gmdate( 'Y-m-d H:i:s', $date->getTimestamp() );
			}
		}
		if ( $new !== $data ) {
			update_option( $name, $new, false );
			$changed++;
		}
	}
	pa_migration_prune_backups();
	return $changed;
}

/**
 * Canonical-slug no-op check for same-slug installations.
 *
 * @return array
 */
function pa_migration_status() {
	$candidates = pa_migration_candidates();
	if ( empty( $candidates ) ) {
		return array(
			'state'   => 'clean',
			'message' => 'نصب روی شناسهٔ استاندارد است و موردی برای مهاجرت نیست.',
		);
	}
	return array(
		'state'      => 'action-needed',
		'message'    => count( $candidates ) . ' مورد برای بررسی یافت شد.',
		'candidates' => array_keys( $candidates ),
	);
}
