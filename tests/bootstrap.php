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

$GLOBALS['fauc_test_options']          = array();
$GLOBALS['fauc_test_site_options']     = array();
$GLOBALS['fauc_test_env_type']         = 'production';
$GLOBALS['fauc_test_filters']          = array();
$GLOBALS['fauc_test_settings_errors']  = array();

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

require_once dirname( __DIR__ ) . '/includes/class-fauc-auto-update-controller.php';
require_once __DIR__ . '/class-fauc-test-case.php';
