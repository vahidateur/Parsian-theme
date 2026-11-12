<?php
/**
 * Website popup upgrade: shared-engine transport + Bale continuation.
 *
 * Additive only. The theme keeps ownership of its form and handlers;
 * `pa-popup.js` registers an explicit transport contract read by
 * ai-chat.js and lays the Bale row onto the panel.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'pa_popup_assets', 30 );

/**
 * Enqueue the shared-engine transport and Bale-continuation layer.
 */
function pa_popup_assets() {
	if ( is_admin() || ! function_exists( 'pm_ai_get' ) || '1' !== pm_ai_get( 'ai_enabled' ) ) {
		return;
	}
	if ( ! wp_script_is( 'pm-ai-chat', 'enqueued' ) ) {
		return;
	}
	wp_enqueue_style(
		'pa-popup',
		plugins_url( 'assets/pa-popup.css', PA_FILE ),
		array( 'pm-ai-chat' ),
		PA_VERSION
	);
	wp_enqueue_script(
		'pa-popup',
		plugins_url( 'assets/pa-popup.js', PA_FILE ),
		array( 'pm-ai-chat' ),
		PA_VERSION,
		true
	);
	wp_localize_script(
		'pa-popup',
		'paChat',
		array(
			'endpoint' => esc_url_raw( rest_url( 'pa/v1/chat' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		)
	);
	$bot = function_exists( 'pa_bale_bot_username' ) ? pa_bale_bot_username( false ) : '';
	if ( '' === $bot ) {
		return;
	}
	wp_localize_script(
		'pa-popup',
		'paBale',
		array(
			'bot'          => $bot,
			'tokenEndpoint' => esc_url_raw( rest_url( 'pa/v1/link-token' ) ),
			'restNonce'    => wp_create_nonce( 'wp_rest' ),
			'loggedIn'     => is_user_logged_in() ? 1 : 0,
			'connectLabel' => 'ادامه در بله',
			'linkLabel'    => 'اتصال حساب به بله',
		)
	);
}
