<?php
/**
 * Test bootstrap: a tiny in-memory WordPress API surface ("mini WP") plus SQLite wpdb.
 *
 * Tests run without a WordPress install. Extend the stubs here when new WP functions are needed
 * (keep them behaviourally faithful, not just no-ops).
 *
 * @package ReviewCaptain\Tests
 */

declare(strict_types=1);

// phpcs:ignoreFile

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', sys_get_temp_dir() . '/rc-wp/' );
define( 'WPINC', 'wp-includes' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'OBJECT', 'OBJECT' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'REVIEW_CAPTAIN_VERSION', '0.1.0-test' );
define( 'REVIEW_CAPTAIN_FILE', dirname( __DIR__ ) . '/review-captain.php' );
define( 'REVIEW_CAPTAIN_DIR', dirname( __DIR__ ) . '/' );
define( 'REVIEW_CAPTAIN_URL', 'https://example.test/wp-content/plugins/review-captain/' );
define( 'REVIEW_CAPTAIN_MIN_PHP', '8.5' );
define( 'REVIEW_CAPTAIN_MIN_WP', '6.5' );

/**
 * Global test state.
 */
final class RC_Test_State {
	/** @var array<string, mixed> */
	public static array $options = array();
	/** @var array<string, mixed> */
	public static array $site_options = array();
	/** @var array<string, array<int, list<array{0: callable, 1: int}>>> */
	public static array $hooks = array();
	/** @var array<string, int> */
	public static array $did = array();
	/** @var list<string> */
	public static array $caps = array();
	public static int $user_id = 0;
	/** @var array<string, array{0:int,1:array}> */
	public static array $scheduled = array();
	/** @var list<array{to:string,subject:string,message:string}> */
	public static array $mails = array();
	/** @var array<string, array<string, bool>> role => caps */
	public static array $roles = array(
		'administrator' => array(),
		'editor'        => array(),
		'author'        => array(),
	);
	/** @var array<string, mixed> */
	public static array $nonces_valid = array();
	public static bool $is_admin = false;
	public static int $queried_id = 0;

	public static function reset(): void {
		self::$options      = array();
		self::$site_options = array();
		self::$hooks        = array();
		self::$did          = array();
		self::$caps         = array();
		self::$user_id      = 0;
		self::$scheduled    = array();
		self::$mails        = array();
		self::$roles        = array( 'administrator' => array(), 'editor' => array(), 'author' => array() );
		self::$is_admin     = false;
		self::$queried_id   = 0;
	}
}

// ---------------------------------------------------------------------------
// Options & transients.
function get_option( $name, $default = false ) { return array_key_exists( $name, RC_Test_State::$options ) ? RC_Test_State::$options[ $name ] : $default; }
function add_option( $name, $value = '', $deprecated = '', $autoload = null ) { if ( array_key_exists( $name, RC_Test_State::$options ) ) { return false; } RC_Test_State::$options[ $name ] = $value; return true; }
function update_option( $name, $value, $autoload = null ) { RC_Test_State::$options[ $name ] = $value; return true; }
function delete_option( $name ) { unset( RC_Test_State::$options[ $name ] ); return true; }
function get_site_option( $name, $default = false ) { return RC_Test_State::$site_options[ $name ] ?? $default; }
function update_site_option( $name, $value ) { RC_Test_State::$site_options[ $name ] = $value; return true; }
function delete_site_option( $name ) { unset( RC_Test_State::$site_options[ $name ] ); return true; }
function get_transient( $name ) { $v = RC_Test_State::$options[ '_transient_' . $name ] ?? null; if ( ! $v ) { return false; } if ( $v[1] && $v[1] < time() ) { return false; } return $v[0]; }
function set_transient( $name, $value, $exp = 0 ) { RC_Test_State::$options[ '_transient_' . $name ] = array( $value, $exp ? time() + $exp : 0 ); return true; }
function delete_transient( $name ) { unset( RC_Test_State::$options[ '_transient_' . $name ] ); return true; }
function wp_cache_get( $k, $g = '' ) { return false; }
function wp_cache_set( $k, $v, $g = '', $e = 0 ) { return true; }
function wp_cache_delete( $k, $g = '' ) { return true; }

// ---------------------------------------------------------------------------
// Hooks.
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { RC_Test_State::$hooks[ $hook ][ $prio ][] = array( $cb, $args ); return true; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { return add_filter( $hook, $cb, $prio, $args ); }
function remove_filter( $hook, $cb, $prio = 10 ) { if ( empty( RC_Test_State::$hooks[ $hook ][ $prio ] ) ) { return false; } foreach ( RC_Test_State::$hooks[ $hook ][ $prio ] as $i => $h ) { if ( $h[0] === $cb ) { unset( RC_Test_State::$hooks[ $hook ][ $prio ][ $i ] ); return true; } } return false; }
function remove_action( $hook, $cb, $prio = 10 ) { return remove_filter( $hook, $cb, $prio ); }
function remove_all_filters( $hook ) { unset( RC_Test_State::$hooks[ $hook ] ); return true; }
function has_filter( $hook, $cb = false ) { if ( empty( RC_Test_State::$hooks[ $hook ] ) ) { return false; } if ( false === $cb ) { return true; } foreach ( RC_Test_State::$hooks[ $hook ] as $prio => $list ) { foreach ( $list as $h ) { if ( $h[0] === $cb ) { return $prio; } } } return false; }
function has_action( $hook, $cb = false ) { return has_filter( $hook, $cb ); }
function apply_filters( $hook, $value, ...$args ) { if ( empty( RC_Test_State::$hooks[ $hook ] ) ) { return $value; } $h = RC_Test_State::$hooks[ $hook ]; ksort( $h ); foreach ( $h as $list ) { foreach ( $list as $item ) { $value = call_user_func_array( $item[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, (int) $item[1] ) ) ); } } return $value; }
function do_action( $hook, ...$args ) { RC_Test_State::$did[ $hook ] = ( RC_Test_State::$did[ $hook ] ?? 0 ) + 1; if ( empty( RC_Test_State::$hooks[ $hook ] ) ) { return; } $h = RC_Test_State::$hooks[ $hook ]; ksort( $h ); foreach ( $h as $list ) { foreach ( $list as $item ) { call_user_func_array( $item[0], array_slice( $args, 0, (int) $item[1] ) ); } } }
function did_action( $hook ) { return RC_Test_State::$did[ $hook ] ?? 0; }
function doing_action( $hook = null ) { return false; }

// ---------------------------------------------------------------------------
// i18n & escaping.
function __( $t, $d = 'default' ) { return $t; }
function _x( $t, $c, $d = 'default' ) { return $t; }
function _n( $s, $p, $n, $d = 'default' ) { return 1 === (int) $n ? $s : $p; }
function _e( $t, $d = 'default' ) { echo $t; }
function esc_html__( $t, $d = 'default' ) { return esc_html( $t ); }
function esc_attr__( $t, $d = 'default' ) { return esc_attr( $t ); }
function esc_html_e( $t, $d = 'default' ) { echo esc_html( $t ); }
function esc_attr_e( $t, $d = 'default' ) { echo esc_attr( $t ); }
function esc_html_x( $t, $c, $d = 'default' ) { return esc_html( $t ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_js( $t ) { return addslashes( (string) $t ); }
function esc_url( $url, $protocols = null ) { $url = trim( (string) $url ); if ( '' === $url ) { return ''; } if ( preg_match( '#^(javascript|data|vbscript):#i', $url ) ) { return ''; } return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $url, $protocols = null ) { $url = trim( (string) $url ); return preg_match( '#^(javascript|data|vbscript):#i', $url ) ? '' : $url; }
function sanitize_url( $url, $protocols = null ) { return esc_url_raw( $url, $protocols ); }
function wp_kses_post( $html ) { return strip_tags( (string) $html, '<p><br><strong><em><b><i><ul><ol><li><a><blockquote>' ); }
function wp_kses( $html, $allowed = array() ) { return strip_tags( (string) $html, is_array( $allowed ) ? '<' . implode( '><', array_keys( $allowed ) ) . '>' : '' ); }
function wp_strip_all_tags( $t, $remove_breaks = false ) { $t = strip_tags( (string) $t ); return $remove_breaks ? trim( preg_replace( '/[\r\n\t ]+/', ' ', $t ) ) : trim( $t ); }
function sanitize_text_field( $s ) { $s = wp_strip_all_tags( (string) $s, true ); return trim( preg_replace( '/[\x00-\x1F\x7F]/u', '', $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ); }
function sanitize_file_name( $f ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $f ); }
function sanitize_email( $e ) { return preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@-]/', '', (string) $e ); }
function sanitize_html_class( $c, $fallback = '' ) { $c = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); return '' === $c ? $fallback : $c; }
function is_email( $e ) { return false !== filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function absint( $v ) { return abs( (int) $v ); }
function __return_true() { return true; }
function __return_false() { return false; }
function wp_json_encode( $d, $o = 0, $depth = 512 ) { return json_encode( $d, $o | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $depth ); }
function wp_parse_args( $args, $defaults = array() ) { if ( is_string( $args ) ) { parse_str( $args, $args ); } return array_merge( (array) $defaults, (array) $args ); }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) { $atts = (array) $atts; $out = array(); foreach ( $pairs as $name => $default ) { $out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default; } return $out; }
function wp_generate_uuid4() { $b = random_bytes( 16 ); $b[6] = chr( ( ord( $b[6] ) & 0x0f ) | 0x40 ); $b[8] = chr( ( ord( $b[8] ) & 0x3f ) | 0x80 ); return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) ); }
function wp_generate_password( $len = 12, $special = true, $extra = false ) { return substr( bin2hex( random_bytes( $len ) ), 0, $len ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme . '-0123456789abcdef0123456789abcdef'; }
function wp_hash( $data, $scheme = 'auth' ) { return hash_hmac( 'md5', (string) $data, wp_salt( $scheme ) ); }
function wp_rand( $min = 0, $max = 0 ) { return random_int( $min, $max ?: PHP_INT_MAX ); }
function wp_trim_words( $text, $num = 55, $more = '…' ) { $w = preg_split( '/\s+/', trim( (string) $text ) ); return count( $w ) > $num ? implode( ' ', array_slice( $w, 0, $num ) ) . $more : (string) $text; }
function wpautop( $t ) { return '<p>' . str_replace( "\n\n", '</p><p>', (string) $t ) . '</p>'; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, ',', '.' ); }
function size_format( $bytes, $d = 0 ) { return $bytes . ' B'; }
function selected( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function disabled( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' disabled="disabled"' : ''; if ( $echo ) { echo $r; } return $r; }

// ---------------------------------------------------------------------------
// Users, caps, nonces.
function get_current_user_id() { return RC_Test_State::$user_id; }
function current_user_can( $cap, ...$args ) { return in_array( $cap, RC_Test_State::$caps, true ) || in_array( 'manage_options', RC_Test_State::$caps, true ) && 'review_captain_' !== substr( $cap, 0, 15 ); }
function is_user_logged_in() { return RC_Test_State::$user_id > 0; }
function wp_get_current_user() { return (object) array( 'ID' => RC_Test_State::$user_id, 'user_email' => 'user' . RC_Test_State::$user_id . '@example.test', 'display_name' => 'User ' . RC_Test_State::$user_id ); }
function get_userdata( $id ) { return $id ? (object) array( 'ID' => $id, 'user_email' => 'user' . $id . '@example.test', 'display_name' => 'User ' . $id ) : false; }
function get_user_by( $field, $value ) { return 'email' === $field && preg_match( '/^user(\d+)@/', (string) $value, $m ) ? get_userdata( (int) $m[1] ) : false; }
function wp_create_nonce( $action = -1 ) { return substr( hash_hmac( 'sha256', (string) $action, 'nonce' ), 0, 10 ); }
function wp_verify_nonce( $nonce, $action = -1 ) { return hash_equals( wp_create_nonce( $action ), (string) $nonce ) ? 1 : false; }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { $n = $_REQUEST[ $query_arg ] ?? ''; if ( ! wp_verify_nonce( $n, $action ) ) { throw new RC_Test_Die( 'nonce_failed' ); } return 1; }
function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) { return check_admin_referer( $action, $query_arg ?: '_ajax_nonce' ); }
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) { $f = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . wp_create_nonce( $action ) . '" />'; if ( $echo ) { echo $f; } return $f; }
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) { return add_query_arg( $name, wp_create_nonce( $action ), $url ); }
class RC_Test_Die extends RuntimeException {}
function wp_die( $message = '', $title = '', $args = array() ) { throw new RC_Test_Die( is_string( $message ) ? $message : 'wp_die' ); }
function wp_safe_redirect( $loc, $status = 302 ) { throw new RC_Test_Die( 'redirect:' . $loc ); }
function wp_redirect( $loc, $status = 302 ) { throw new RC_Test_Die( 'redirect:' . $loc ); }

class RC_Test_Role {
	public string $name; public array $capabilities = array();
	public function __construct( string $name ) { $this->name = $name; $this->capabilities = &RC_Test_State::$roles[ $name ]; }
	public function add_cap( $cap, $grant = true ) { RC_Test_State::$roles[ $this->name ][ $cap ] = $grant; }
	public function remove_cap( $cap ) { unset( RC_Test_State::$roles[ $this->name ][ $cap ] ); }
	public function has_cap( $cap ) { return ! empty( RC_Test_State::$roles[ $this->name ][ $cap ] ); }
}
function get_role( $name ) { return isset( RC_Test_State::$roles[ $name ] ) ? new RC_Test_Role( $name ) : null; }
function wp_roles() { $o = new stdClass(); $o->role_objects = array(); $o->role_names = array(); foreach ( array_keys( RC_Test_State::$roles ) as $r ) { $o->role_objects[ $r ] = new RC_Test_Role( $r ); $o->role_names[ $r ] = ucfirst( $r ); } return $o; }

// ---------------------------------------------------------------------------
// Environment / URLs.
function is_multisite() { return false; }
function get_current_blog_id() { return 1; }
function switch_to_blog( $id ) { return true; }
function restore_current_blog() { return true; }
function is_admin() { return RC_Test_State::$is_admin; }
function wp_doing_cron() { return false; }
function wp_doing_ajax() { return false; }
function is_ssl() { return true; }
function get_bloginfo( $show = '' ) { return match ( $show ) { 'version' => '6.7', 'name' => 'Testsite', 'charset' => 'UTF-8', default => '' }; }
function get_locale() { return 'de_DE'; }
function determine_locale() { return 'de_DE'; }
function get_user_locale( $u = 0 ) { return 'de_DE'; }
function wp_timezone() { return new DateTimeZone( 'Europe/Vienna' ); }
function wp_timezone_string() { return 'Europe/Vienna'; }
function current_time( $type, $gmt = 0 ) { return 'timestamp' === $type || 'U' === $type ? time() : gmdate( 'Y-m-d H:i:s' ); }
function wp_date( $format, $ts = null, $tz = null ) { $d = new DateTimeImmutable( '@' . ( $ts ?? time() ) ); return $d->setTimezone( $tz ?? wp_timezone() )->format( $format ); }
function date_i18n( $format, $ts = false, $gmt = false ) { return gmdate( $format, $ts ?: time() ); }
function mysql2date( $format, $date, $translate = true ) { $t = strtotime( (string) $date . ' UTC' ); return 'U' === $format ? $t : gmdate( $format, $t ); }
function human_time_diff( $from, $to = 0 ) { $d = abs( ( $to ?: time() ) - $from ); return $d < 3600 ? round( $d / 60 ) . ' Minuten' : ( $d < 86400 ? round( $d / 3600 ) . ' Stunden' : round( $d / 86400 ) . ' Tage' ); }
function home_url( $path = '' ) { return 'https://example.test' . ( $path ? '/' . ltrim( $path, '/' ) : '' ); }
function site_url( $path = '' ) { return home_url( $path ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function network_admin_url( $path = '' ) { return 'https://example.test/wp-admin/network/' . ltrim( $path, '/' ); }
function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( $path, '/' ); }
function plugins_url( $path = '', $plugin = '' ) { return REVIEW_CAPTAIN_URL . ltrim( $path, '/' ); }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return REVIEW_CAPTAIN_URL; }
function plugin_basename( $file ) { return 'review-captain/' . basename( $file ); }
function add_query_arg( ...$args ) { if ( is_array( $args[0] ) ) { $params = $args[0]; $url = $args[1] ?? ''; } else { $params = array( $args[0] => $args[1] ); $url = $args[2] ?? ''; } $parts = explode( '?', (string) $url, 2 ); parse_str( $parts[1] ?? '', $q ); $q = array_merge( $q, $params ); $q = array_filter( $q, static fn( $v ) => false !== $v && null !== $v ); return $parts[0] . ( $q ? '?' . http_build_query( $q ) : '' ); }
function remove_query_arg( $key, $url = '' ) { $parts = explode( '?', (string) $url, 2 ); parse_str( $parts[1] ?? '', $q ); foreach ( (array) $key as $k ) { unset( $q[ $k ] ); } return $parts[0] . ( $q ? '?' . http_build_query( $q ) : '' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function get_permalink( $post = 0 ) { return 'https://example.test/?p=' . ( is_object( $post ) ? $post->ID : (int) $post ); }
function get_the_ID() { return 0; }
function get_post( $id = null ) { return null; }
function get_post_type( $post = null ) { return false; }
function get_the_title( $post = 0 ) { return 'Post ' . ( is_object( $post ) ? $post->ID : (int) $post ); }
function is_singular( $types = '' ) { return RC_Test_State::$queried_id > 0; }
if ( ! function_exists( 'get_queried_object_id' ) ) { function get_queried_object_id() { return RC_Test_State::$queried_id; } }
function get_avatar_url( $id_or_email, $args = null ) { return 'https://secure.gravatar.com/avatar/x'; }
function is_plugin_active( $plugin ) { return false; }
function is_plugin_active_for_network( $plugin ) { return false; }
function get_plugins() { return array(); }
function wp_get_environment_type() { return 'development'; }
function wp_upload_dir() { return array( 'basedir' => sys_get_temp_dir(), 'baseurl' => 'https://example.test/uploads', 'error' => false ); }

// Cron & mail.
function wp_next_scheduled( $hook, $args = array() ) { return RC_Test_State::$scheduled[ $hook ][0] ?? false; }
function wp_schedule_event( $ts, $rec, $hook, $args = array() ) { RC_Test_State::$scheduled[ $hook ] = array( $ts, $args, $rec ); return true; }
function wp_schedule_single_event( $ts, $hook, $args = array() ) { RC_Test_State::$scheduled[ $hook ] = array( $ts, $args, 'single' ); return true; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { unset( RC_Test_State::$scheduled[ $hook ] ); return 1; }
function wp_unschedule_event( $ts, $hook, $args = array() ) { unset( RC_Test_State::$scheduled[ $hook ] ); return true; }
function wp_get_schedules() { return array( 'hourly' => array( 'interval' => 3600 ), 'daily' => array( 'interval' => 86400 ) ); }
function wp_mail( $to, $subject, $message, $headers = '', $att = array() ) { RC_Test_State::$mails[] = compact( 'to', 'subject', 'message' ); return true; }

// Shortcodes / blocks / assets (record only).
$GLOBALS['rc_test_shortcodes'] = array();
function add_shortcode( $tag, $cb ) { $GLOBALS['rc_test_shortcodes'][ $tag ] = $cb; }
function shortcode_exists( $tag ) { return isset( $GLOBALS['rc_test_shortcodes'][ $tag ] ); }
function do_shortcode( $content ) { return $content; }
function register_block_type( $name, $args = array() ) { $GLOBALS['rc_test_blocks'][ is_string( $name ) ? $name : 'x' ] = $args; return (object) array( 'name' => $name ); }
function wp_register_style( ...$a ) { return true; }
function wp_enqueue_style( ...$a ) { $GLOBALS['rc_test_styles'][] = $a[0]; }
function wp_register_script( ...$a ) { return true; }
function wp_enqueue_script( ...$a ) { $GLOBALS['rc_test_scripts'][] = $a[0]; }
function wp_localize_script( ...$a ) { return true; }
function wp_add_inline_style( ...$a ) { return true; }
function wp_set_script_translations( ...$a ) { return true; }
function wp_style_is( $h, $l = 'enqueued' ) { return in_array( $h, $GLOBALS['rc_test_styles'] ?? array(), true ); }
function register_rest_route( $ns, $route, $args = array(), $override = false ) { $GLOBALS['rc_test_routes'][ $ns . $route ] = $args; return true; }
function load_plugin_textdomain( ...$a ) { return true; }
function wp_privacy_anonymize_data( $type, $data = '' ) { return match ( $type ) { 'email' => 'deleted@site.invalid', 'text' => '[deleted]', default => '' }; }

// Import/export, privacy (RC-044..048) – guarded stubs.
if ( ! function_exists( 'add_submenu_page' ) ) { function add_submenu_page( $parent, $page_title, $menu_title, $cap, $slug, $cb = '', $pos = null ) { $GLOBALS['rc_test_submenus'][ $slug ] = compact( 'parent', 'page_title', 'menu_title', 'cap', 'slug', 'cb' ); return 'review-captain_page_' . $slug; } }
if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) { function wp_add_privacy_policy_content( $plugin_name, $policy_text ) { $GLOBALS['rc_test_privacy_policy'][ $plugin_name ] = $policy_text; } }
if ( ! function_exists( 'wp_mkdir_p' ) ) { function wp_mkdir_p( $target ) { return is_dir( $target ) || mkdir( $target, 0777, true ); } }
if ( ! function_exists( 'wp_delete_file' ) ) { function wp_delete_file( $file ) { if ( is_file( $file ) ) { unlink( $file ); } } }
if ( ! function_exists( 'nocache_headers' ) ) { function nocache_headers() {} }

// Global wpdb.
$GLOBALS['wpdb'] = new \ReviewCaptain\Tests\Support\SqliteWpdb();

// Frontend/SEO stubs (added for Frontend/Seo tests).
if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $names, $load = false, $once = true, $args = array() ) { return ''; }
}
if ( ! function_exists( 'wp_validate_redirect' ) ) {
	function wp_validate_redirect( $location, $fallback = '' ) { $host = parse_url( (string) $location, PHP_URL_HOST ); if ( null === $host && str_starts_with( (string) $location, '/' ) && ! str_starts_with( (string) $location, '//' ) ) { return $location; } return 'example.test' === $host ? $location : $fallback; }
}

// Admin module stubs (function_exists/class_exists-guarded).
if ( ! function_exists( 'add_menu_page' ) ) { function add_menu_page( $page_title, $menu_title, $cap, $slug, $cb = '', $icon = '', $pos = null ) { $GLOBALS['rc_test_menus'][ $slug ] = compact( 'page_title', 'menu_title', 'cap', 'slug', 'cb', 'icon' ); return 'toplevel_page_' . $slug; } }
if ( ! class_exists( 'WP_List_Table' ) ) {
	class WP_List_Table {
		public $items = array(); protected $_args = array(); protected $_pagination_args = array(); public $_column_headers = array();
		public function __construct( $args = array() ) { $this->_args = (array) $args; }
		public function get_pagenum() { return max( 1, absint( $_REQUEST['paged'] ?? 1 ) ); }
		protected function set_pagination_args( $args ) { $this->_pagination_args = $args; }
		public function get_pagination_arg( $key ) { return $this->_pagination_args[ $key ] ?? null; }
		public function has_items() { return ! empty( $this->items ); }
		protected function row_actions( $actions, $always_visible = false ) { return '<div class="row-actions">' . implode( ' | ', $actions ) . '</div>'; }
		protected function get_views() { return array(); }
		public function views() { echo implode( ' | ', $this->get_views() ); }
		protected function get_bulk_actions() { return array(); }
		protected function extra_tablenav( $which ) {}
		public function no_items() { echo 'No items'; }
		public function display() { $this->extra_tablenav( 'top' ); if ( ! $this->has_items() ) { $this->no_items(); return; } foreach ( $this->items as $item ) { foreach ( array_keys( $this->get_columns() ) as $col ) { $m = 'column_' . $col; echo method_exists( $this, $m ) ? $this->$m( $item ) : $this->column_default( $item, $col ); } } }
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/** Minimal WP_Error (tests). */
	class WP_Error {
		/** @var string */
		public $code;
		/** @var string */
		public $message;
		public function __construct( $code = '', $message = '' ) { $this->code = (string) $code; $this->message = (string) $message; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}
if ( ! function_exists( 'wp_specialchars_decode' ) ) {
	function wp_specialchars_decode( $s, $q = ENT_NOQUOTES ) { return htmlspecialchars_decode( (string) $s, $q ); }
}
