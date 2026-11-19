<?php
/**
 * Outbound destination policy for credential-bearing requests.
 *
 * Provider base URLs are security-sensitive settings: whichever host they name
 * receives stored bot tokens or API keys. This file is the single enforcement
 * point for those destinations. It is loaded before any module that sends an
 * authenticated outbound request.
 *
 * The policy is deliberately fail-closed: an endpoint must be HTTPS, on an
 * exact, canonical, provider-approved hostname, with no embedded credentials,
 * no unapproved port, and no ambiguous URL components.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pm_outbound_sensitive_endpoint_keys' ) ) {
	/**
	 * Settings keys that select where a stored credential is transmitted.
	 *
	 * @return array
	 */
	function pm_outbound_sensitive_endpoint_keys() {
		return array( 'bale_base', 'rubika_base', 'ai_api_url', 'ai_allowed_hosts' );
	}
}

if ( ! function_exists( 'pm_outbound_provider_for_key' ) ) {
	/**
	 * Map a sensitive settings key to its provider policy.
	 *
	 * @param string $key Settings key without the pm_ prefix.
	 * @return string Empty string when the key has no provider policy.
	 */
	function pm_outbound_provider_for_key( $key ) {
		$map = array(
			'bale_base'       => 'bale',
			'rubika_base'     => 'rubika',
			'ai_api_url'      => 'ai',
			'ai_allowed_hosts' => 'ai',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}
}

if ( ! function_exists( 'pm_outbound_canonical_host' ) ) {
	/**
	 * Normalize and validate a bare hostname.
	 *
	 * IP literals, userinfo, paths, wildcards and malformed labels are rejected.
	 *
	 * @param string $raw Candidate hostname.
	 * @return string Canonical lowercase hostname, or empty string when invalid.
	 */
	function pm_outbound_canonical_host( $raw ) {
		$host = strtolower( trim( (string) $raw ) );
		if ( '' === $host || strlen( $host ) > 253 ) {
			return '';
		}
		if ( preg_match( '/[\x00-\x20\x7f\\\\\/:?#@*%,]/', $host ) ) {
			return '';
		}
		if ( 0 === strpos( $host, '.' ) || '.' === substr( $host, -1 ) ) {
			return '';
		}
		if ( function_exists( 'filter_var' ) && filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		if ( preg_match( '/[^\x00-\x7f]/', $host ) ) {
			if ( ! function_exists( 'idn_to_ascii' ) || ! defined( 'INTL_IDNA_VARIANT_UTS46' ) ) {
				return '';
			}
			$ascii = idn_to_ascii( $host, 0, INTL_IDNA_VARIANT_UTS46 );
			if ( ! is_string( $ascii ) || '' === $ascii ) {
				return '';
			}
			$host = strtolower( $ascii );
		}
		if ( ! preg_match( '/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/', $host ) ) {
			return '';
		}
		foreach ( explode( '.', $host ) as $label ) {
			if ( '' === $label || '-' === $label[0] || '-' === substr( $label, -1 ) ) {
				return '';
			}
		}
		return $host;
	}
}

if ( ! function_exists( 'pm_outbound_current_endpoint' ) ) {
	/**
	 * Effective configured value for a protected endpoint, including defaults.
	 *
	 * @param string $key Settings key.
	 * @return string
	 */
	function pm_outbound_current_endpoint( $key ) {
		$value = pm_get_option( $key, '' );
		if ( '' !== (string) $value ) {
			return (string) $value;
		}
		if ( function_exists( 'pm_notify_defaults' ) ) {
			$defaults = pm_notify_defaults();
			if ( isset( $defaults[ $key ] ) ) {
				return (string) $defaults[ $key ];
			}
		}
		if ( function_exists( 'pm_ai_defaults' ) ) {
			$defaults = pm_ai_defaults();
			if ( isset( $defaults[ $key ] ) ) {
				return (string) $defaults[ $key ];
			}
		}
		return '';
	}
}

if ( ! function_exists( 'pm_outbound_parse_allowed_hosts' ) ) {
	/**
	 * Parse an administrator-managed allowlist into canonical hostnames.
	 *
	 * Invalid entries are dropped; they are never treated as approved hosts.
	 *
	 * @param string $raw Newline/comma separated hostnames.
	 * @return array
	 */
	function pm_outbound_parse_allowed_hosts( $raw ) {
		$hosts = array();
		if ( ! is_string( $raw ) ) {
			return $hosts;
		}
		foreach ( preg_split( '/[\r\n,;]+/', $raw ) as $entry ) {
			$host = pm_outbound_canonical_host( $entry );
			if ( '' !== $host && ! in_array( $host, $hosts, true ) ) {
				$hosts[] = $host;
			}
		}
		return $hosts;
	}
}

if ( ! function_exists( 'pm_outbound_allowed_hosts' ) ) {
	/**
	 * Exact hostname allowlist per provider.
	 *
	 * The built-in entries are the only endpoints documented by this product.
	 * Additional AI hosts require an explicit, administrator-managed allowlist
	 * stored in the pm_ai_allowed_hosts theme mod.
	 *
	 * @return array
	 */
	function pm_outbound_allowed_hosts() {
		$hosts = array(
			'bale'   => array( 'tapi.bale.ai' ),
			'rubika' => array( 'botapi.rubika.ir' ),
			'ai'     => array( 'api.openai.com' ),
		);

		$custom = get_theme_mod( 'pm_ai_allowed_hosts', '' );
		if ( is_string( $custom ) && '' !== trim( $custom ) ) {
			$entries = preg_split( '/[\r\n,;]+/', $custom );
			foreach ( (array) $entries as $entry ) {
				$host = pm_outbound_canonical_host( $entry );
				if ( '' !== $host && ! in_array( $host, $hosts['ai'], true ) ) {
					$hosts['ai'][] = $host;
				}
			}
		}
		return $hosts;
	}
}

if ( ! function_exists( 'pm_outbound_validate_endpoint' ) ) {
	/**
	 * Validate a credential-bearing endpoint before it is used.
	 *
	 * @param string $url      Configured base/endpoint URL.
	 * @param string $provider One of bale, rubika, ai.
	 * @return true|WP_Error True when allowed, otherwise a generic WP_Error.
	 */
	function pm_outbound_validate_endpoint( $url, $provider ) {
		$blocked = function_exists( 'pm_outbound_blocked_error' )
			? pm_outbound_blocked_error()
			: new WP_Error( 'pm_outbound_blocked', 'آدرس مقصد مجاز نیست.' );
		$url = (string) $url;
		if ( '' === $url || preg_match( '/[\x00-\x1f\x7f]/', $url ) ) {
			return $blocked;
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $blocked;
		}
		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return $blocked;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return $blocked;
		}
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			return $blocked;
		}
		if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return $blocked;
		}
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$decoded = rawurldecode( $path );
		if ( preg_match( '/(\.\.|\/\/|\\\\|[\x00-\x1f\x7f])/', $path . ' ' . $decoded ) ) {
			return $blocked;
		}

		$host = pm_outbound_canonical_host( (string) $parts['host'] );
		if ( '' === $host ) {
			return $blocked;
		}
		$hosts = pm_outbound_allowed_hosts();
		if ( ! isset( $hosts[ $provider ] ) || ! in_array( $host, $hosts[ $provider ], true ) ) {
			return $blocked;
		}
		return true;
	}
}

if ( ! function_exists( 'pm_outbound_blocked_error' ) ) {
	/**
	 * Generic error. Never include the URL or a credential.
	 *
	 * @return WP_Error
	 */
	function pm_outbound_blocked_error() {
		return new WP_Error( 'pm_outbound_blocked_endpoint', 'آدرس مقصد مجاز نیست. درخواست ارسال نشد.' );
	}
}

if ( ! function_exists( 'pm_outbound_safe_request_args' ) ) {
	/**
	 * Harden transport args for credential-bearing requests.
	 *
	 * Redirects are disabled so a secret can never be forwarded to a destination
	 * that has not passed this policy. Unsafe URL rejection stays enabled.
	 *
	 * @param array $args Request args.
	 * @return array
	 */
	function pm_outbound_safe_request_args( $args ) {
		$args                    = is_array( $args ) ? $args : array();
		$args['redirection']     = 0;
		$args['reject_unsafe_urls'] = true;
		return $args;
	}
}
