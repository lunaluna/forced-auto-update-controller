<?php
/**
 * プラグイン有効化時 (register_activation_hook) に呼び出される関数
 * PHP 7.4+ / WordPress 6.0+ を必須とし、満たさない場合はプラグインを無効化しつつ警告を表示
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // セキュリティ: 直接アクセス禁止.
}

/**
 * プラグイン環境をチェックし、要件を満たさない場合はプラグインを無効化
 *
 * @return void
 */
function fauc_check_environment() {
	$required_php_version = '7.4';
	$required_wp_version  = '6.0';

	$current_php_version = PHP_VERSION;
	$current_wp_version  = get_bloginfo( 'version' );

	// 要件を満たさない場合、プラグインを無効化して管理画面に警告を出す.
	if (
		version_compare( $current_php_version, $required_php_version, '<' )
		|| version_compare( $current_wp_version, $required_wp_version, '<' )
	) {
		// プラグインを無効化する.
		deactivate_plugins( plugin_basename( __DIR__ . '/../forced-auto-update-controller.php' ) );

		// 管理画面のエラー通知をフック (admin_notices).
		add_action(
			'admin_notices',
			function() use ( $required_php_version, $required_wp_version, $current_php_version, $current_wp_version ) {
				?>
				<div class="error notice">
					<p>
					<?php
					echo sprintf(
						esc_html__(
							'Forced Auto Update Controller requires PHP %1$s or higher and WordPress %2$s or higher. You have PHP %3$s and WordPress %4$s. The plugin has been deactivated.',
							'forced-auto-update-controller'
						),
						esc_html( $required_php_version ),
						esc_html( $required_wp_version ),
						esc_html( $current_php_version ),
						esc_html( $current_wp_version )
					);
					?>
					</p>
				</div>
				<?php
			}
		);
	}
}
