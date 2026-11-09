<?php
/**
 * Legacy theme settings migration (v1.6.0 randomized slug -> canonical).
 *
 * Detects theme_mods_{stylesheet} rows left by v1.6.0 installs whose
 * directory had a randomized suffix, previews them, backs them up, and
 * copies only schema-supported keys into theme_mods_parsian-music.
 * Never deletes the source installation or its option. Never runs on
 * frontend page loads — admin action only, capability + nonce gated.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PA_CANONICAL_SLUG', 'parsian-music' );

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
 * Allowlisted destination keys from the current theme schema.
 *
 * @return array|null Null when the theme schema is unavailable.
 */
function pa_migration_schema_keys() {
	if ( ! function_exists( 'pm_settings_schema' ) ) {
		return null;
	}
	$keys = array();
	foreach ( pm_settings_schema() as $tab ) {
		if ( empty( $tab['sections'] ) ) {
			continue;
		}
		foreach ( $tab['sections'] as $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field_id => $field ) {
				$keys[] = is_string( $field_id ) ? $field_id : ( isset( $field['id'] ) ? $field['id'] : null );
			}
		}
	}
	return array_unique( $keys );
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
	foreach ( $candidates[ $source_slug ]['mods'] as $key => $value ) {
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

	$backup_id = 'pa_migration_backup_' . gmdate( 'YmdHis' ) . '_' . substr( md5( $source_slug ), 0, 6 );
	update_option(
		$backup_id,
		array(
			'source_slug'   => $source_slug,
			'source_option' => $source_option,
			'source_mods'   => $source_mods,
			'dest_before'   => $dest_before,
			'created_at'    => current_time( 'mysql' ),
		),
		false
	);

	$dest_mods = $dest_before;
	$migrated = array();
	$skipped = array();
	foreach ( $preview['supported'] as $key ) {
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
		'completed'            => $verified,
		'source'               => $source_slug,
		'migrated'             => $migrated,
		'skipped_conflicts'    => $skipped,
		'preserved_unsupported' => $preview['unsupported'],
		'backup'               => $backup_id,
		'source_preserved'     => true,
	);
	update_option( $record_key, $report, false );
	return $report;
}

/**
 * Restore the destination snapshot from a backup.
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
	if ( ! is_array( $backup ) || ! isset( $backup['dest_before'] ) ) {
		return new WP_Error( 'pa_migration_backup', 'پشتیبان پیدا نشد.' );
	}
	update_option( 'theme_mods_' . PA_CANONICAL_SLUG, $backup['dest_before'] );
	delete_option( 'pa_migration_done_' . md5( (string) $backup['source_slug'] ) );
	return true;
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
