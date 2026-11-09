<?php
/**
 * Plugin Name: Parsian Assistant
 * Description: Shared assistant engine, Bale adapter, account linking, and legacy theme settings migration for Parsian Music Academy.
 * Version: 1.0.0
 * Requires at least: 7.1
 * Tested up to: 7.1
 * Requires PHP: 8.4.27
 * Author: Parsian Team
 * Text Domain: parsian-assistant
 *
 * Companion plugin to the Parsian Music Academy theme. Holds infrastructure
 * that must survive a theme switch: conversations, Bale webhook ingestion,
 * account linking, update deduplication, and the legacy theme-mod migration.
 * The website popup and Knowledge Base UI stay in the theme; this plugin
 * delegates to them when the theme is active and degrades gracefully when
 * it is not.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PA_VERSION', '1.0.0' );
define( 'PA_DIR', plugin_dir_path( __FILE__ ) );
define( 'PA_FILE', __FILE__ );
define( 'PA_DB_VERSION', '1.0.0' );

require_once PA_DIR . 'inc/contracts.php';
require_once PA_DIR . 'inc/store.php';
require_once PA_DIR . 'inc/engine.php';
require_once PA_DIR . 'inc/bale.php';
require_once PA_DIR . 'inc/linking.php';
require_once PA_DIR . 'inc/privacy.php';
require_once PA_DIR . 'inc/migration.php';
require_once PA_DIR . 'inc/rest.php';
require_once PA_DIR . 'inc/admin.php';
require_once PA_DIR . 'inc/popup.php';

/**
 * Create tables on activation. Never touches theme data.
 */
function pa_activate() {
	pa_store_install();
	pa_schedule_recovery();
}
register_activation_hook( __FILE__, 'pa_activate' );

/**
 * Remove the plugin's recurring queue worker. Data is untouched.
 */
function pa_deactivate() {
	wp_clear_scheduled_hook( 'pa_recover_pending' );
}
register_deactivation_hook( __FILE__, 'pa_deactivate' );

/**
 * Self-heal tables (create-only, no migrations of user data).
 */
function pa_maybe_install() {
	if ( '1.0.0' !== get_option( 'pa_db_version', '' ) ) {
		pa_store_install();
	}
}
add_action( 'init', 'pa_maybe_install', 5 );

/**
 * Feature detection for the theme and popup upgrades.
 *
 * @return bool
 */
function pa_active() {
	return true;
}
