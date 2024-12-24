<?php
/**
 * アンインストールフック (プラグイン削除時専用)
 *
 * WordPress は register_uninstall_hook() でこのファイル内の関数を呼び出す。
 * 本プラグインで作成したオプションを削除して環境をクリーンに戻す。
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // セキュリティ: 直接アクセスを防止.
}

/**
 * アンインストール時に呼び出される関数
 *
 * @return void
 */
function fauc_forced_auto_update_controller_uninstall() {
	delete_option( 'FAUC_forced_auto_update_domain' );
}
