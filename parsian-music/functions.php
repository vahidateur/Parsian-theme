<?php
/**
 * Parsian Music Academy – theme bootstrap.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PM_VERSION', '1.6.0' );
define( 'PM_DIR', get_template_directory() );
define( 'PM_URI', get_template_directory_uri() );

require_once PM_DIR . '/inc/setup.php';
require_once PM_DIR . '/inc/widgets.php';
require_once PM_DIR . '/inc/enqueue.php';
require_once PM_DIR . '/inc/template-tags.php';
require_once PM_DIR . '/inc/icons.php';
require_once PM_DIR . '/inc/post-types.php';
require_once PM_DIR . '/inc/meta-fields.php';
require_once PM_DIR . '/inc/forms.php';
require_once PM_DIR . '/inc/customizer.php';
require_once PM_DIR . '/inc/demo-content.php';
require_once PM_DIR . '/inc/ai-assistant.php';
require_once PM_DIR . '/inc/notifications.php';
require_once PM_DIR . '/inc/theme-settings.php';
require_once PM_DIR . '/inc/admin-branding.php';
require_once PM_DIR . '/inc/seo.php';
