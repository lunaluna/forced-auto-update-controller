<?php
/**
 * PHPUnit bootstrap.
 *
 * WP のテストスイートは使わず、テスト対象のクラスファイルが読み込み時・
 * 各メソッド実行時に触れる WP 関数だけを最小限にスタブする.
 *
 * クラスファイルは読み込み時に自身を `new FAUC_Auto_Update_Controller()`
 * するため、スタブの定義 → クラスファイルの require の順序を守ること.
 *
 * @package ForcedAutoUpdateController
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['fauc_test_options']           = array();
$GLOBALS['fauc_test_site_options']      = array();
$GLOBALS['fauc_test_env_type']          = 'production';
$GLOBALS['fauc_test_filters']           = array();
$GLOBALS['fauc_test_settings_errors']   = array();
$GLOBALS['fauc_test_transients']        = array();
$GLOBALS['fauc_test_site_transients']   = array();
$GLOBALS['fauc_test_current_user_can']  = true;
$GLOBALS['fauc_test_admin_referer_ok']  = true;
$GLOBALS['fauc_test_referer']           = false;
$GLOBALS['fauc_test_wp_die_calls']      = array();
$GLOBALS['fauc_test_redirects']         = array();

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 7 * 24 * 60 * 60 );
}

require_once __DIR__ . '/class-fauc-test-wp-die-exception.php';

/**
 * add_action() のスタブ.
 *
 * @param string   $hook_name フック名（未使用）.
 * @param callable $callback  コールバック（未使用）.
 * @param int      $priority  優先度（未使用）.
 * @param int      $accepted_args 引数の数（未使用）.
 * @return true 常に true.
 */
function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

/**
 * add_filter() のスタブ.
 *
 * @param string   $hook_name フック名（未使用）.
 * @param callable $callback  コールバック（未使用）.
 * @param int      $priority  優先度（未使用）.
 * @param int      $accepted_args 引数の数（未使用）.
 * @return true 常に true.
 */
function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

/**
 * is_multisite() のスタブ. コンストラクタの分岐用（既定: 単一サイト）.
 *
 * @return bool
 */
function is_multisite() {
	return false;
}

/**
 * get_option() のスタブ. $GLOBALS['fauc_test_options'] から値を返す.
 *
 * @param string $name    オプション名.
 * @param mixed  $default 既定値.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['fauc_test_options'] ) ? $GLOBALS['fauc_test_options'][ $name ] : $default;
}

/**
 * get_site_option() のスタブ. $GLOBALS['fauc_test_site_options'] から値を返す.
 *
 * @param string $name    オプション名.
 * @param mixed  $default 既定値.
 * @return mixed
 */
function get_site_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['fauc_test_site_options'] ) ? $GLOBALS['fauc_test_site_options'][ $name ] : $default;
}

/**
 * wp_get_environment_type() のスタブ.
 *
 * @return string
 */
function wp_get_environment_type() {
	return $GLOBALS['fauc_test_env_type'];
}

/**
 * wp_parse_url() のスタブ. PHP 標準の parse_url() へ委譲する.
 *
 * @param string $url       URL.
 * @param int    $component 取得するコンポーネント.
 * @return mixed
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

/**
 * apply_filters() のスタブ. $GLOBALS['fauc_test_filters'] にコールバックが
 * 登録されていればそれを適用し、無ければ素通しする.
 *
 * @param string $hook_name フック名.
 * @param mixed  $value     デフォルト値.
 * @return mixed
 */
function apply_filters( $hook_name, $value ) {
	$args = func_get_args();

	if ( isset( $GLOBALS['fauc_test_filters'][ $hook_name ] ) ) {
		return call_user_func_array( $GLOBALS['fauc_test_filters'][ $hook_name ], array_slice( $args, 1 ) );
	}

	return $value;
}

/**
 * add_settings_error() のスタブ. $GLOBALS['fauc_test_settings_errors'] に記録する.
 *
 * @param string $setting 設定グループ（未使用）.
 * @param string $code    エラーコード.
 * @param string $message エラーメッセージ.
 * @param string $type    種別（未使用）.
 * @return void
 */
function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['fauc_test_settings_errors'][] = array(
		'code'    => $code,
		'message' => $message,
	);
}

/**
 * __() のスタブ. 翻訳せず素通しする.
 *
 * @param string      $text   翻訳対象の文字列.
 * @param string|null $domain テキストドメイン（未使用）.
 * @return string
 */
function __( $text, $domain = null ) {
	return $text;
}

/**
 * Set_transient() のスタブ. $GLOBALS['fauc_test_transients'] に値と有効期限を記録する.
 *
 * @param string $transient  transient 名.
 * @param mixed  $value      値.
 * @param int    $expiration 有効期限（秒）.
 * @return true
 */
function set_transient( $transient, $value, $expiration = 0 ) {
	$GLOBALS['fauc_test_transients'][ $transient ] = array(
		'value'      => $value,
		'expiration' => $expiration,
	);
	return true;
}

/**
 * Get_transient() のスタブ.
 *
 * @param string $transient transient 名.
 * @return mixed 未設定の場合は false.
 */
function get_transient( $transient ) {
	return isset( $GLOBALS['fauc_test_transients'][ $transient ] )
		? $GLOBALS['fauc_test_transients'][ $transient ]['value']
		: false;
}

/**
 * Delete_transient() のスタブ.
 *
 * @param string $transient transient 名.
 * @return bool 削除前に存在していれば true.
 */
function delete_transient( $transient ) {
	$existed = isset( $GLOBALS['fauc_test_transients'][ $transient ] );
	unset( $GLOBALS['fauc_test_transients'][ $transient ] );
	return $existed;
}

/**
 * Set_site_transient() のスタブ. $GLOBALS['fauc_test_site_transients'] に記録する.
 *
 * @param string $transient  transient 名.
 * @param mixed  $value      値.
 * @param int    $expiration 有効期限（秒）.
 * @return true
 */
function set_site_transient( $transient, $value, $expiration = 0 ) {
	$GLOBALS['fauc_test_site_transients'][ $transient ] = array(
		'value'      => $value,
		'expiration' => $expiration,
	);
	return true;
}

/**
 * Get_site_transient() のスタブ.
 *
 * @param string $transient transient 名.
 * @return mixed 未設定の場合は false.
 */
function get_site_transient( $transient ) {
	return isset( $GLOBALS['fauc_test_site_transients'][ $transient ] )
		? $GLOBALS['fauc_test_site_transients'][ $transient ]['value']
		: false;
}

/**
 * Delete_site_transient() のスタブ.
 *
 * @param string $transient transient 名.
 * @return bool 削除前に存在していれば true.
 */
function delete_site_transient( $transient ) {
	$existed = isset( $GLOBALS['fauc_test_site_transients'][ $transient ] );
	unset( $GLOBALS['fauc_test_site_transients'][ $transient ] );
	return $existed;
}

/**
 * Current_user_can() のスタブ. $GLOBALS['fauc_test_current_user_can'] を返す.
 *
 * @param string $capability 権限（未使用）.
 * @return bool
 */
function current_user_can( $capability ) {
	return $GLOBALS['fauc_test_current_user_can'];
}

/**
 * Wp_die() のスタブ. 呼び出しを記録し、実際の処理中断は例外で表現する.
 *
 * @param string|WP_Error $message エラーメッセージ.
 * @param string          $title   タイトル（未使用）.
 * @param array           $args    引数（response コード等）.
 * @return void
 * @throws FAUC_Test_WP_Die_Exception 常に投げる.
 */
function wp_die( $message = '', $title = '', $args = array() ) {
	$GLOBALS['fauc_test_wp_die_calls'][] = array(
		'message' => $message,
		'title'   => $title,
		'args'    => $args,
	);
	throw new FAUC_Test_WP_Die_Exception( is_string( $message ) ? $message : 'wp_die' );
}

/**
 * Check_admin_referer() のスタブ. $GLOBALS['fauc_test_admin_referer_ok'] が
 * false の場合、実際の WordPress と同様に wp_die() を呼んで処理を中断する.
 *
 * @param int|string $action    nonce のアクション名（未使用）.
 * @param string     $query_arg nonce のクエリ引数名（未使用）.
 * @return int|void
 */
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	if ( ! $GLOBALS['fauc_test_admin_referer_ok'] ) {
		wp_die( '辿ったリンクは期限が切れています。', '', array( 'response' => 403 ) );
	}
	return 1;
}

/**
 * Wp_get_referer() のスタブ. $GLOBALS['fauc_test_referer'] を返す.
 *
 * @return string|false
 */
function wp_get_referer() {
	return $GLOBALS['fauc_test_referer'];
}

/**
 * Wp_safe_redirect() のスタブ. 呼び出しを記録する.
 *
 * @param string $location リダイレクト先.
 * @param int    $status   HTTP ステータスコード.
 * @return true
 */
function wp_safe_redirect( $location, $status = 302 ) {
	$GLOBALS['fauc_test_redirects'][] = array(
		'location' => $location,
		'status'   => $status,
	);
	return true;
}

/**
 * Admin_url() のスタブ.
 *
 * @param string $path 追加パス.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
}

require_once __DIR__ . '/class-fauc-test-wp-error.php';

/**
 * Is_wp_error() のスタブ.
 *
 * @param mixed $thing 判定対象.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof FAUC_Test_WP_Error;
}

/**
 * Esc_html__() のスタブ. 翻訳・エスケープせず素通しする.
 *
 * @param string      $text   翻訳対象の文字列.
 * @param string|null $domain テキストドメイン（未使用）.
 * @return string
 */
function esc_html__( $text, $domain = null ) {
	return $text;
}

/**
 * Get_plugins() のスタブ. $GLOBALS['fauc_test_plugins'] を返す.
 *
 * @return array
 */
function get_plugins() {
	return isset( $GLOBALS['fauc_test_plugins'] ) ? $GLOBALS['fauc_test_plugins'] : array();
}

/**
 * Wp_get_themes() のスタブ. $GLOBALS['fauc_test_themes'] を返す.
 *
 * @return array
 */
function wp_get_themes() {
	return isset( $GLOBALS['fauc_test_themes'] ) ? $GLOBALS['fauc_test_themes'] : array();
}

require_once dirname( __DIR__ ) . '/includes/class-fauc-auto-update-controller.php';
require_once __DIR__ . '/class-fauc-test-case.php';
