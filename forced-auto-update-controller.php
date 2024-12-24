<?php
/**
 * Plugin Name: Forced Auto Update Controller
 * Description: Git 管理下でも本番環境（指定ドメインパターンに合致）だけは自動更新をすべて有効化し、テスト環境・ローカル環境を無効化するプラグイン。他のテーマやプラグインが自動更新を無効化していても最終的に上書きします。
 * Version:     1.0.3
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
 * メインプラグインファイル
 *
 * 1. プラグイン有効化時に環境チェックを行う (PHP 7.4+, WP 5.5+)
 * 2. メインクラスを読み込み、インスタンス化
 * 3. アンインストールフックを設定
 */

// 1. プラグイン有効化時の環境チェック.
require_once plugin_dir_path( __FILE__ ) . 'includes/functions-activation.php';
register_activation_hook( __FILE__, 'fauc_check_environment' );

// 2. メインクラスを読み込み・インスタンス化.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-fauc-forced-auto-update-controller.php';
$fauc_auto_update_controller = new FAUC_Auto_update_Controller();

// 3. アンインストールフックの設定 (プラグイン削除時に実行).
register_uninstall_hook( __FILE__, 'fauc_forced_auto_update_controller_uninstall' );

/**
 * (4) プラグイン一覧のメタ情報欄に GitHub へのリンクを追加する関数
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
		// リンクを追加 (例: GitHub リポジトリのURL).
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/lunaluna/forced-auto-update-controller' ),
			esc_html__( 'GitHub', 'forced-auto-update-controller' )
		);
	}

	return $links;
}
// フィルターフックを登録 (優先度10, 引数2つ)
add_filter( 'plugin_row_meta', 'fauc_set_plugin_meta', 10, 2 );
