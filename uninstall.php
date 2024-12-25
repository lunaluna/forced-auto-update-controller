<?php
/**
 * アンインストールフック (プラグイン削除時専用)
 *
 * WordPress は register_uninstall_hook() でこのファイル内の関数を呼び出す
 * 本プラグインで作成したオプションを削除して環境をクリーンに戻す
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // セキュリティ: 直接アクセスを防止.
}

/**
 * アンインストール時に設定したオプションを削除
 */
// メインのオプション (本番環境URLドメイン設定).
delete_option( 'FAUC_forced_auto_update_domain' );
// プラグイン除外リスト.
delete_option( 'FAUC_forced_auto_update_domain_excluded_plugins' );
// テーマ除外リスト.
delete_option( 'FAUC_forced_auto_update_domain_excluded_themes' );
