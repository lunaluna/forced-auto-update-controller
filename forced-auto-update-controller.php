<?php
/**
 * Plugin Name: Forced Auto Update Controller
 * Description: Git などファイルのバージョン管理下でも、指定したドメインパターンに合致した場合だけは自動更新を有効化するプラグイン。
 * Version:     1.1.5
 * Author:      Hiroki Saiki
 * Author URI:  https://profiles.wordpress.org/lunaluna_dev/
 * License:     GPLv2 or later
 * Text Domain: forced-auto-update-controller
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // セキュリティ: 直接アクセスを防止.
}

/**
 * プラグイン有効化時の環境チェック (PHP 7.4+, WP 6.0+)
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/functions-activation.php';
register_activation_hook( __FILE__, 'fauc_check_environment' );

/**
 * メインクラスの読み込み
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-fauc-forced-auto-update-controller.php';

/**
 * プラグイン一覧のメタ情報欄に GitHub へのリンクを追加する関数
 *
 * - plugin_row_meta フィルタを使い、プラグインの行にカスタムリンクを追加
 *
 * @param string[] $links  既存のリンク (詳細, 設定など)
 * @param string   $file   プラグインのベースファイル名
 * @return string[]        $links に追加した結果を返す
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
 * プラグイン一覧画面でのプラグイン名部分に「Settings」リンクを追加する関数
 *
 * @param array $links 既存のアクションリンク
 * @return array 修正後のアクションリンク
 */
function fauc_add_settings_link( $links ) {
    // オプションページへのURLを生成.
    $settings_url = admin_url( 'options-general.php?page=fauc-forced-auto-update-controller' );

    // 「Settings」リンクを作成.
    $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings' ) . '</a>';

    // 既存のリンク配列の先頭に「Settings」リンクを追加.
    array_unshift( $links, $settings_link );

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fauc_add_settings_link' );
