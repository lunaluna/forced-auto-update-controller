<?php
/**
 * PHPUnit bootstrap.
 *
 * WP のテストスイートは使わず、テスト対象のクラスファイルが読み込み時・
 * 純粋関数の実行時に触れる WP 関数だけを最小限にスタブする.
 *
 * @package ForcedAutoUpdateController
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'FAUC_VERSION', '1.8.0' );
define( 'FAUC_PLUGIN_FILE', dirname( __DIR__ ) . '/forced-auto-update-controller.php' );
define( 'FAUC_PLUGIN_BASENAME', 'forced-auto-update-controller/forced-auto-update-controller.php' );

/**
 * add_filter() のスタブ. クラス読み込み時の FAUC_GitHub_Updater::init() が呼ぶ.
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
 * add_action() のスタブ. add_filter() のエイリアス相当.
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
 * get_file_data() のスタブ. build_plugin_update_object() のテストのため、
 * 実プラグインヘッダーと同じ値を固定で返す.
 *
 * @param string $file          ファイルパス（未使用）.
 * @param array  $default_headers 取得したいヘッダーのキー => ラベル.
 * @return string[] キーごとの値.
 */
function get_file_data( $file, $default_headers ) {
	$values = array(
		'requires'     => '6.0',
		'requires_php' => '7.4',
		'tested'       => '7.1',
	);

	$result = array();
	foreach ( $default_headers as $key => $label ) {
		$result[ $key ] = isset( $values[ $key ] ) ? $values[ $key ] : '';
	}

	return $result;
}

require_once dirname( __DIR__ ) . '/includes/class-fauc-github-updater.php';
