<?php
/**
 * Website popup upgrade: Bale continuation + account linking.
 *
 * Additive only — the theme's popup markup and ai-chat.js are untouched.
 * This script layers a "continue in Bale" row onto the panel when the
 * plugin is active and a bot username is known.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'pa_popup_assets', 30 );

/**
 * Enqueue the Bale-continuation layer.
 */
function pa_popup_assets() {
	if ( is_admin() || ! function_exists( 'pm_ai_get' ) || '1' !== pm_ai_get( 'ai_enabled' ) ) {
		return;
	}
	if ( ! wp_script_is( 'pm-ai-chat', 'enqueued' ) ) {
		return;
	}
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
