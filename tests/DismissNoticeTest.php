<?php
/**
 * FAUC_Auto_Update_Controller::handle_dismiss_auto_update_notice() の回帰テスト.
 *
 * ①「自動更新通知を消す手段がない」に追加した dismiss ハンドラの
 * 権限チェック・nonce検証・transient削除を固定する.
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-case.php';
require_once __DIR__ . '/class-fauc-test-controller.php';

/**
 * DismissNoticeTest クラス.
 */
class DismissNoticeTest extends FAUC_Test_Case {

	/**
	 * 正しい権限・nonce のとき、transient が削除され referer へリダイレクトされること.
	 *
	 * @return void
	 */
	public function test_dismiss_deletes_transient_with_valid_request() {
		set_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary', array( 'plugin: Flamingo' ), WEEK_IN_SECONDS );

		$GLOBALS['fauc_test_current_user_can'] = true;
		$GLOBALS['fauc_test_admin_referer_ok'] = true;
		$GLOBALS['fauc_test_referer']          = 'https://example.com/wp-admin/options-general.php';

		$controller = new FAUC_Test_Controller();

		try {
			$controller->handle_dismiss_auto_update_notice();
			$this->fail( 'FAUC_Test_Terminate_Exception が投げられるはずです' );
		} catch ( FAUC_Test_Terminate_Exception $e ) {
			// exit の代わりに投げられる想定の例外.
			unset( $e );
		}

		$this->assertFalse( get_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary' ) );
		$this->assertCount( 1, $GLOBALS['fauc_test_redirects'] );
		$this->assertSame( 'https://example.com/wp-admin/options-general.php', $GLOBALS['fauc_test_redirects'][0]['location'] );
	}

	/**
	 * wp_get_referer() が false のとき、admin_url() へフォールバックすること.
	 *
	 * @return void
	 */
	public function test_dismiss_falls_back_to_admin_url_without_referer() {
		set_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary', array( 'plugin: Flamingo' ), WEEK_IN_SECONDS );

		$GLOBALS['fauc_test_current_user_can'] = true;
		$GLOBALS['fauc_test_admin_referer_ok'] = true;
		$GLOBALS['fauc_test_referer']          = false;

		$controller = new FAUC_Test_Controller();

		try {
			$controller->handle_dismiss_auto_update_notice();
			$this->fail( 'FAUC_Test_Terminate_Exception が投げられるはずです' );
		} catch ( FAUC_Test_Terminate_Exception $e ) {
			// exit の代わりに投げられる想定の例外.
			unset( $e );
		}

		$this->assertFalse( get_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary' ) );
		$this->assertCount( 1, $GLOBALS['fauc_test_redirects'] );
		$this->assertSame( admin_url(), $GLOBALS['fauc_test_redirects'][0]['location'] );
	}

	/**
	 * 権限がない場合、wp_die が呼ばれ transient は削除されないこと.
	 *
	 * @return void
	 */
	public function test_dismiss_without_permission_does_not_delete_transient() {
		set_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary', array( 'plugin: Flamingo' ), WEEK_IN_SECONDS );

		$GLOBALS['fauc_test_current_user_can'] = false;

		$controller = $this->new_controller();

		try {
			$controller->handle_dismiss_auto_update_notice();
			$this->fail( 'FAUC_Test_WP_Die_Exception が投げられるはずです' );
		} catch ( FAUC_Test_WP_Die_Exception $e ) {
			// 期待される例外.
			unset( $e );
		}

		$this->assertSame( array( 'plugin: Flamingo' ), get_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary' ) );
		$this->assertCount( 1, $GLOBALS['fauc_test_wp_die_calls'] );
		$this->assertSame( 403, $GLOBALS['fauc_test_wp_die_calls'][0]['args']['response'] );
		$this->assertEmpty( $GLOBALS['fauc_test_redirects'] );
	}

	/**
	 * nonce が不正な場合、wp_die が呼ばれ transient は削除されないこと.
	 *
	 * @return void
	 */
	public function test_dismiss_with_invalid_nonce_does_not_delete_transient() {
		set_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary', array( 'plugin: Flamingo' ), WEEK_IN_SECONDS );

		$GLOBALS['fauc_test_current_user_can'] = true;
		$GLOBALS['fauc_test_admin_referer_ok'] = false;

		$controller = $this->new_controller();

		try {
			$controller->handle_dismiss_auto_update_notice();
			$this->fail( 'FAUC_Test_WP_Die_Exception が投げられるはずです' );
		} catch ( FAUC_Test_WP_Die_Exception $e ) {
			// 期待される例外.
			unset( $e );
		}

		$this->assertSame( array( 'plugin: Flamingo' ), get_transient( 'FAUC_forced_auto_update_domain_last_auto_update_summary' ) );
		$this->assertEmpty( $GLOBALS['fauc_test_redirects'] );
	}
}
