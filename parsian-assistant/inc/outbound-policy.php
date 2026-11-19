<?php
/**
 * Outbound destination policy for the assistant plugin.
 *
 * When the Parsian theme is active, the theme owns the settings and the
 * canonical validator; this wrapper delegates to it. In standalone mode the
 * plugin fails closed: only the documented default provider hosts are accepted,
 * because an administrator-controlled custom allowlist is not available.
 *
 * @package Parsian_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pa_outbound_validate_endpoint' ) ) {
	/**
	 * Validate an endpoint before attaching a credential.
	 *
	 * @param string $url      Configured endpoint URL.
	 * @param string $provider bale, rubika or ai.
	 * @return true|WP_Error
	 */
	function pa_outbound_validate_endpoint( $url, $provider ) {
		if ( function_exists( 'pm_outbound_validate_endpoint' ) ) {
			return pm_outbound_validate_endpoint( $url, $provider );
		}

		$error = function_exists( 'pa_outbound_blocked_error' )
			? pa_outbound_blocked_error()
			: new WP_Error( 'pa_outbound_blocked', 'آدرس مقصد مجاز نیست.' );

		$url   = (string) $url;
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $error;
		}
		if ( 'https' !== strtolower( (string) $parts['scheme'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return $error;
		}
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			return $error;
		}
		if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return $error;
		}

		$host = strtolower( trim( (string) $parts['host'] ) );
		$defaults = array(
			'bale'   => 'tapi.bale.ai',
			'rubika' => 'botapi.rubika.ir',
			'ai'     => 'api.openai.com',
		);
		if ( ! isset( $defaults[ $provider ] ) || $host !== $defaults[ $provider ] ) {
			return $error;
		}
		return true;
	}
}

if ( ! function_exists( 'pa_outbound_blocked_error' ) ) {
	/**
	 * Generic, secret-free error.
	 *
	 * @return WP_Error
	 */
	function pa_outbound_blocked_error() {
		return new WP_Error( 'pa_outbound_blocked_endpoint', 'آدرس مقصد مجاز نیست. درخواست ارسال نشد.' );
	}
}

if ( ! function_exists( 'pa_outbound_safe_request_args' ) ) {
	/**
	 * Disable redirects and reject unsafe URLs for authenticated requests.
	 *
	 * @param array $args Request args.
	 * @return array
	 */
	function pa_outbound_safe_request_args( $args ) {
		if ( function_exists( 'pm_outbound_safe_request_args' ) ) {
			return pm_outbound_safe_request_args( $args );
		}
		$args                       = is_array( $args ) ? $args : array();
		$args['redirection']        = 0;
		$args['reject_unsafe_urls'] = true;
		return $args;
	}
}
