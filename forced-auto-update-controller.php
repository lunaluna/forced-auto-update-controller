<?php
/**
 * Plugin Name:       Forced Auto Update Controller
 * Plugin URI:        https://github.com/lunaluna/forced-auto-update-controller
 * Description:       Git などファイルのバージョン管理下でも、指定したドメインパターンに合致した場合だけは自動更新を有効化するプラグイン.
 * Version:           1.8.0
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            lunaluna_dev
 * Author URI:        https://profiles.wordpress.org/lunaluna_dev/
 * Update URI:        false
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       forced-auto-update-controller
 * Domain Path:       /languages
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // セキュリティ: 直接アクセスを防止.
}

/**
 * 翻訳ファイル (.mo) を読み込む.
 *
 * GitHub 配布で wp.org 未登録のため、翻訳の自動読み込みに頼らず明示的に読み込む.
 *
 * @return void
 */
function fauc_load_textdomain() {
	load_plugin_textdomain(
		'forced-auto-update-controller',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'fauc_load_textdomain' );

/**
 * プラグイン有効化時の環境チェック (PHP 7.4+, WP 6.0+).
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/functions-activation.php';
register_activation_hook( __FILE__, 'fauc_check_environment' );

/**
 * メインクラスの読み込み.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-fauc-auto-update-controller.php';

/**
 * GitHub Releases ベースの自己更新機構の読み込み(l2d-wp-github-update-lib).
 */
$fauc_updater_register = require plugin_dir_path( __FILE__ ) . 'lib/l2d-updater/loader.php';
$fauc_updater_register(
	array(
		'plugin_file'   => __FILE__,
		'github_repo'   => 'lunaluna/forced-auto-update-controller',
		'cache_key'     => 'FAUC_github_release_cache',
		'filter_prefix' => 'fauc',
	)
);

/**
 * プラグイン一覧のメタ情報欄に GitHub へのリンクを追加する関数.
 *
 * - plugin_row_meta フィルタを使い、プラグインの行にカスタムリンクを追加.
 *
 * @param string[] $links  既存のリンク（詳細、設定など）.
 * @param string   $file   プラグインのベースファイル名.
 * @return string[]        $links に追加した結果を返す.
 */
function fauc_set_plugin_meta( $links, $file ) {

	// このプラグインのベースファイルパス(ディレクトリ/ファイル名).
	static $this_plugin;
	$this_plugin = plugin_basename( __FILE__ );

	// プラグイン一覧で $file が実際にこのプラグインを指しているかどうかをチェック.
	if ( $file === $this_plugin ) {
		// GitHub へのリンクを追加.
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/lunaluna/forced-auto-update-controller' ),
			esc_html__( 'GitHub', 'forced-auto-update-controller' )
		);
	}

	return $links;
}
add_filter( 'plugin_row_meta', 'fauc_set_plugin_meta', 10, 2 );

/**
 * プラグイン一覧画面でのプラグイン名部分に「Settings」リンクを追加する関数.
 *
 * @param array $links 既存のアクションリンク.
 * @return array 修正後のアクションリンク.
 */
function fauc_add_settings_link( $links ) {
	// オプションページへの URL を生成.
	$settings_url = admin_url( 'options-general.php?page=fauc-forced-auto-update-controller' );

	// 「Settings」リンクを作成.
	$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'forced-auto-update-controller' ) . '</a>';

	// 既存のリンク配列の先頭に「Settings」リンクを追加.
	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fauc_add_settings_link' );
