<?php
/**
 * Security regression tests for the outbound credential-destination policy.
 *
 * Runs with plain PHP (no WordPress bootstrap):
 *   php wp-local/tests/pa-outbound-policy-test.php
 *
 * Only dummy credentials are used. wp_remote_* is stubbed so no request ever
 * leaves the machine; the assertions check that rejected destinations never
 * reach that stub at all.
 *
 * @package Parsian_Assistant
 */

$pm_outbound_tmp = sys_get_temp_dir() . '/pm-outbound-test-' . getmypid();
if ( ! is_dir( $pm_outbound_tmp ) ) {
	mkdir( $pm_outbound_tmp, 0700, true );
}
define( 'ABSPATH', $pm_outbound_tmp . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['pm_test'] = array(
	'mods'     => array(),
	'options'  => array(),
	'caps'     => array( 'edit_theme_options' => true, 'manage_options' => true ),
	'http'     => array(),
	'nonce'    => true,
	'tmp'      => $pm_outbound_tmp,
);

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class PM_Test_WPDB {
	public $prefix = 'wp_';
	public function prepare( $query, ...$args ) { return $query; }
	public function get_results( $query ) { return array(); }
	public function get_var( $query ) { return null; }
	public function get_col( $query ) { return array(); }
	public function delete( $table, $where ) { return 0; }
	public function insert( $table, $data ) { return 1; }
	public function update( $table, $data, $where ) { return 0; }
	public function query( $query ) { return 0; }
}
$GLOBALS['wpdb'] = new PM_Test_WPDB();

function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function register_rest_route() {}
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_email( $v ) { return filter_var( (string) $v, FILTER_VALIDATE_EMAIL ) ? (string) $v : ''; }
function esc_url_raw( $v ) { return (string) $v; }
function esc_url( $v ) { return (string) $v; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function wp_kses_post( $v ) { return (string) $v; }
function wp_kses( $v, $allowed ) { return strip_tags( (string) $v, '<br><b>' ); }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function wp_parse_url( $url ) { return parse_url( (string) $url ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function absint( $v ) { return abs( (int) $v ); }
function get_theme_mod( $key, $default = '' ) {
	return array_key_exists( $key, $GLOBALS['pm_test']['mods'] ) ? $GLOBALS['pm_test']['mods'][ $key ] : $default;
}
function set_theme_mod( $key, $value ) { $GLOBALS['pm_test']['mods'][ $key ] = $value; }
function remove_theme_mod( $key ) { unset( $GLOBALS['pm_test']['mods'][ $key ] ); }
function get_option( $key, $default = '' ) {
	return array_key_exists( $key, $GLOBALS['pm_test']['options'] ) ? $GLOBALS['pm_test']['options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) { $GLOBALS['pm_test']['options'][ $key ] = $value; }
function delete_option( $key ) { unset( $GLOBALS['pm_test']['options'][ $key ] ); }
function current_user_can( $cap ) { return ! empty( $GLOBALS['pm_test']['caps'][ $cap ] ); }
function check_admin_referer( $action = -1 ) { return true; }
function wp_die( $message = '', $title = '', $args = array() ) {
	$code = isset( $args['response'] ) ? (int) $args['response'] : 500;
	throw new Exception( 'WP_DIE_' . $code . ':' . (string) $message );
}
function wp_verify_nonce( $nonce, $action ) { return (bool) $GLOBALS['pm_test']['nonce']; }
function get_transient( $key ) { return 0; }
function set_transient( $key, $value, $expiry ) { return true; }
function home_url( $path = '/' ) { return 'https://site.test' . $path; }
function get_bloginfo( $key = '' ) { return 'test'; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['pm_test']['tmp'] ); }
function wp_mkdir_p( $dir ) { if ( ! is_dir( $dir ) ) { mkdir( $dir, 0700, true ); } return true; }
function get_posts( $args ) { return array(); }
function get_the_title( $post ) { return ''; }
function get_permalink( $post ) { return ''; }
function get_post_type_archive_link( $type ) { return 'https://site.test/teachers/'; }
function trailingslashit( $value ) { return rtrim( (string) $value, '/\\' ) . '/'; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function strip_shortcodes( $text ) { return (string) $text; }
function date_i18n( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function wp_safe_redirect( $url ) {}
function add_query_arg( $args, $url = '' ) {
	$query = http_build_query( (array) $args );
	return $url . ( false === strpos( (string) $url, '?' ) ? '?' : '&' ) . $query;
}
function admin_url( $path = '' ) { return 'https://site.test/wp-admin/' . $path; }
function wp_nonce_field() {}
function selected( $a, $b ) {}
function rest_ensure_response( $value ) { return $value; }
function pm_test_mock_response( $body ) {
	return array(
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'body'     => $body,
		'headers'  => array(),
		'cookies'  => array(),
	);
}
function wp_remote_request( $url, $args ) {
	$GLOBALS['pm_test']['http'][] = array( 'url' => $url, 'args' => $args );
	return pm_test_mock_response( '{"ok":true,"result":{"message_id":1}}' );
}
function wp_remote_post( $url, $args ) {
	$GLOBALS['pm_test']['http'][] = array( 'url' => $url, 'args' => $args );
	return pm_test_mock_response( '{"choices":[{"message":{"content":"dummy reply"}}]}' );
}
function wp_remote_retrieve_response_code( $response ) { return (int) $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return (string) $response['body']; }

class PM_Test_Request {
	private $headers;
	private $params;
	public function __construct( $headers = array(), $params = array() ) {
		$this->headers = $headers;
		$this->params  = $params;
	}
	public function get_header( $key ) { return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : ''; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : ''; }
}

$root = dirname( dirname( __DIR__ ) );
require_once $root . '/parsian-music/inc/outbound-policy.php';
require_once $root . '/parsian-music/inc/template-tags.php';
require_once $root . '/parsian-music/inc/notifications.php';
require_once $root . '/parsian-music/inc/demo-content.php';
require_once $root . '/parsian-music/inc/ai-assistant.php';
require_once $root . '/parsian-music/inc/customizer.php';
require_once $root . '/parsian-music/inc/theme-settings.php';
require_once $root . '/parsian-assistant/inc/contracts.php';
require_once $root . '/parsian-assistant/inc/outbound-policy.php';
require_once $root . '/parsian-assistant/inc/engine.php';
require_once $root . '/parsian-assistant/inc/bale.php';

$GLOBALS['pm_test_pass'] = 0;
$GLOBALS['pm_test_fail'] = 0;
function pm_test( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['pm_test_pass']++;
		echo "PASS: " . $label . "\n";
	} else {
		$GLOBALS['pm_test_fail']++;
		echo "FAIL: " . $label . "\n";
	}
}
function pm_reset_http() { $GLOBALS['pm_test']['http'] = array(); }
function pm_http_blob() { return json_encode( $GLOBALS['pm_test']['http'] ); }

$valid = function ( $url, $provider ) { return true === pm_outbound_validate_endpoint( $url, $provider ); };
$invalid = function ( $url, $provider ) { return is_wp_error( pm_outbound_validate_endpoint( $url, $provider ) ); };

// Positive destinations.
pm_test( $valid( 'https://tapi.bale.ai', 'bale' ), 'documented Bale HTTPS endpoint accepted' );
pm_test( $valid( 'https://botapi.rubika.ir/v3', 'rubika' ), 'documented Rubika HTTPS endpoint accepted' );
pm_test( $valid( 'https://api.openai.com/v1/chat/completions', 'ai' ), 'documented OpenAI endpoint accepted' );
pm_test( $valid( 'https://tapi.bale.ai:443', 'bale' ), 'explicit default TLS port accepted' );

// Configured, administrator-managed custom AI host.
set_theme_mod( 'pm_ai_allowed_hosts', "AI.Example.Test\nbad host!" );
pm_test( $valid( 'https://ai.example.test/v1/chat/completions', 'ai' ), 'canonical allowlisted custom AI host accepted' );
pm_test( $invalid( 'https://attacker.invalid/v1', 'ai' ), 'unlisted host rejected even when an admin allowlist exists' );
set_theme_mod( 'pm_ai_allowed_hosts', '' );

// Negative destinations.
$bad = array(
	'https://attacker.invalid/collect',
	'https://example.com/v1',
	'http://tapi.bale.ai',
	'ftp://tapi.bale.ai',
	'https://evil.tapi.bale.ai',
	'https://tapi.bale.ai.attacker.invalid',
	'https://user:pass@tapi.bale.ai',
	'https://tapi.bale.ai:8443',
	'https://127.0.0.1',
	'https://[::1]',
	'https://localhost',
	'https://192.168.1.10',
	'https://169.254.169.254',
	'https://10.0.0.5',
	'https://tapi.bale.ai?x=1',
	'https://tapi.bale.ai#frag',
	'https://tapi.bale.ai/../evil',
	'https://tapi.bale.ai//evil',
	'https://tapi.bale.ai.',
);
foreach ( $bad as $index => $url ) {
	pm_test( $invalid( $url, 'bale' ), 'rejected destination #' . ( $index + 1 ) . ': ' . $url );
}
pm_test( $invalid( 'https://api.openai.com.evil.test/v1', 'ai' ), 'hostname containing trusted provider name rejected' );
pm_test( $invalid( 'https://attacker.invalid/v1', 'ai' ), 'arbitrary AI host rejected' );

// Bale credential exposure through the theme notification gateway.
set_theme_mod( 'pm_bale_token', 'DUMMY-BALE-TOKEN-123456789' );
set_theme_mod( 'pm_bale_base', 'https://attacker.invalid/collect' );
pm_reset_http();
$result = pm_notify_api_request( 'bale', 'getMe' );
$blob   = pm_http_blob();
pm_test( empty( $result['success'] ), 'malicious Bale base yields failed result' );
pm_test( 0 === count( $GLOBALS['pm_test']['http'] ), 'malicious Bale destination never reaches HTTP transport' );
pm_test( false === strpos( $blob . ' ' . $result['message'], 'DUMMY-BALE-TOKEN' ), 'dummy Bale token absent from transport and error text' );

set_theme_mod( 'pm_bale_base', 'https://tapi.bale.ai' );
pm_reset_http();
$result = pm_notify_api_request( 'bale', 'getMe' );
pm_test( ! empty( $result['success'] ) && 1 === count( $GLOBALS['pm_test']['http'] ), 'allowed Bale destination still sends normally' );
pm_test( 0 === $GLOBALS['pm_test']['http'][0]['args']['redirection'], 'credentialed request disables redirects' );
pm_test( ! empty( $GLOBALS['pm_test']['http'][0]['args']['reject_unsafe_urls'] ), 'credentialed request rejects unsafe URLs' );

// Theme AI chat: validation runs before the Bearer key is attached.
set_theme_mod( 'pm_ai_api_url', 'https://attacker.invalid/v1/chat/completions' );
set_theme_mod( 'pm_ai_api_key', 'DUMMY-AI-KEY-987654321' );
set_theme_mod( 'pm_ai_model', 'gpt-4o-mini' );
set_theme_mod( 'pm_ai_enabled', '1' );
pm_reset_http();
$chat = pm_ai_rest_chat( new PM_Test_Request( array( 'X-PM-Nonce' => 'n' ), array( 'message' => 'درباره اساتید بگو' ) ) );
pm_test( is_wp_error( $chat ), 'malicious AI endpoint rejected by chat endpoint' );
pm_test( 0 === count( $GLOBALS['pm_test']['http'] ), 'malicious AI endpoint never reaches HTTP transport' );
pm_test( false === strpos( $chat->get_error_message(), 'DUMMY-AI-KEY' ), 'dummy AI key absent from error text' );

// Plugin provider uses the same enforcement layer.
pm_reset_http();
$provider = new PA_AI_Provider();
$res = $provider->complete( 'system', 'message', array() );
pm_test( ! empty( $res['error'] ) && 'endpoint' === $res['error'], 'plugin provider rejects malicious endpoint' );
pm_test( 0 === count( $GLOBALS['pm_test']['http'] ), 'plugin provider never dispatches request on rejection' );
set_theme_mod( 'pm_ai_api_url', 'https://api.openai.com/v1/chat/completions' );
pm_reset_http();
$res = $provider->complete( 'system', 'message', array() );
pm_test( ! empty( $res['ok'] ) && 1 === count( $GLOBALS['pm_test']['http'] ), 'plugin provider still works with documented endpoint' );

// Authorization: editor cannot change endpoints; nonce alone is not authority.
function pm_test_save( $caps, $post ) {
	$GLOBALS['pm_test']['caps'] = $caps;
	pm_reset_http();
	try {
		pm_apply_theme_settings( $post );
		return array( 'die' => false, 'code' => 200 );
	} catch ( Exception $e ) {
		preg_match( '/WP_DIE_(\d+)/', $e->getMessage(), $matches );
		return array( 'die' => true, 'code' => isset( $matches[1] ) ? (int) $matches[1] : 0 );
	}
}
$editor = array( 'edit_theme_options' => true, 'manage_options' => false );
$admin  = array( 'edit_theme_options' => true, 'manage_options' => true );

set_theme_mod( 'pm_bale_base', 'https://tapi.bale.ai' );
$out = pm_test_save( $editor, array( 'bale_base' => 'https://attacker.invalid/collect' ) );
pm_test( $out['die'] && 403 === $out['code'], 'editor endpoint change rejected with 403' );
pm_test( 'https://tapi.bale.ai' === get_theme_mod( 'pm_bale_base' ), 'editor endpoint change not persisted' );

// An editor resubmitting the effective default endpoint must not be blocked
// just because the theme mod was never explicitly saved.
remove_theme_mod( 'pm_bale_base' );
$out = pm_test_save( $editor, array( 'bale_base' => 'https://tapi.bale.ai' ) );
pm_test( ! $out['die'], 'editor can resubmit the effective default endpoint' );
set_theme_mod( 'pm_bale_base', 'https://tapi.bale.ai' );

$out = pm_test_save( $editor, array( 'bale_base' => 'https://tapi.bale.ai' ) );
pm_test( ! $out['die'] || 400 !== $out['code'], 'editor can submit unrelated settings with unchanged endpoint' );

$out = pm_test_save( $editor, array( 'ai_api_url' => 'https://attacker.invalid/v1', 'ai_allowed_hosts' => 'evil.test' ) );
pm_test( $out['die'] && 403 === $out['code'], 'editor cannot change AI endpoint or allowlist' );

$out = pm_test_save( $admin, array( 'bale_base' => 'https://attacker.invalid/collect' ) );
pm_test( $out['die'] && 400 === $out['code'], 'admin malicious endpoint rejected with 400' );

$out = pm_test_save( $admin, array( 'bale_base' => 'https://tapi.bale.ai', 'ai_allowed_hosts' => "ai.example.test\nbad host!" ) );
pm_test( 'https://tapi.bale.ai' === get_theme_mod( 'pm_bale_base' ), 'admin can save allowed endpoint' );
pm_test( 'ai.example.test' === get_theme_mod( 'pm_ai_allowed_hosts' ), 'admin allowlist normalized; malformed entry dropped' );

echo "\nTOTAL: " . $GLOBALS['pm_test_pass'] . " passed, " . $GLOBALS['pm_test_fail'] . " failed\n";
exit( $GLOBALS['pm_test_fail'] > 0 ? 1 : 0 );
