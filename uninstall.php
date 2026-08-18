<?php
/**
 * アンインストール.
 *
 * WordPress は register_uninstall_hook() でこのファイル内の関数を呼び出す.
 * 本プラグインで作成したオプションを削除して環境をクリーンに戻す.
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // セキュリティ: 直接アクセスを防止.
}

/**
 * アンインストールの際に、有効化時に設定したオプションを削除.
 */
// メインのオプション (本番環境URLドメイン設定).
delete_option( 'FAUC_forced_auto_update_domain' );
// プラグイン除外リスト.
delete_option( 'FAUC_forced_auto_update_domain_excluded_plugins' );
// テーマ除外リスト.
delete_option( 'FAUC_forced_auto_update_domain_excluded_themes' );
// WordPress本体の更新通知を非表示にする設定.
delete_option( 'FAUC_forced_auto_update_domain_hide_wp_updates' );
// 非本番環境でもコアのマイナー/セキュリティ自動更新を許可する設定.
delete_option( 'FAUC_forced_auto_update_domain_allow_core_minor_everywhere' );
// 直近の自動更新実行結果の通知用データ.
delete_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary' );
// GitHub Releases 更新機構のリリース情報キャッシュ.
delete_site_transient( 'FAUC_github_release_cache' );
