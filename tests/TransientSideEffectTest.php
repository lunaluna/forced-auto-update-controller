<?php
/**
 * 設定変更が option と transient の両方に正しく反映されるかの回帰テスト（表T）.
 *
 * 「オプションは書き換わったのに transient が古いまま」という不整合を防ぐため、
 * option を assert して終わりにせず、必ず transient 側も assert する.
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-case.php';
require_once __DIR__ . '/class-fauc-test-controller.php';

/**
 * TransientSideEffectTest クラス.
 */
class TransientSideEffectTest extends FAUC_Test_Case {

	/**
	 * 判定用 transient のキー.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'FAUC_forced_auto_update_domain_last_auto_update_summary';

	/**
	 * 成功結果のアップデート結果オブジェクトを1件作る.
	 *
	 * @param string $name 更新対象の名前.
	 * @return object
	 */
	private function make_success_result( $name ) {
		return (object) array(
			'name'   => $name,
			'result' => true,
		);
	}

	/**
	 * 失敗結果のアップデート結果オブジェクトを1件作る.
	 *
	 * @param string $name 更新対象の名前.
	 * @return object
	 */
	private function make_failure_result( $name ) {
		return (object) array(
			'name'   => $name,
			'result' => new FAUC_Test_WP_Error( 'update_failed', 'failed' ),
		);
	}

	/**
	 * T1: パターン一致・env production で成功結果を発火すると transient が新規に書かれる.
	 *
	 * @return void
	 */
	public function test_t1_success_writes_transient_on_production() {
		$GLOBALS['fauc_test_options']['home']                           = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'example.com';
		$GLOBALS['fauc_test_env_type']                                  = 'production';

		$controller = $this->new_controller();
		$controller->handle_automatic_updates_complete(
			array( 'plugin' => array( $this->make_success_result( 'Foo' ) ) )
		);

		$this->assertSame( array( 'plugin: Foo' ), get_transient( self::TRANSIENT_KEY ) );
		$this->assertSame( WEEK_IN_SECONDS, $GLOBALS['fauc_test_transients'][ self::TRANSIENT_KEY ]['expiration'] );
	}

	/**
	 * T2: パターン一致・env local・無視OFF のときは transient が書かれない.
	 *
	 * @return void
	 */
	public function test_t2_no_transient_when_environment_gate_blocks() {
		$GLOBALS['fauc_test_options']['home']                           = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'example.com';
		$GLOBALS['fauc_test_env_type']                                  = 'local';

		$controller = $this->new_controller();
		$controller->handle_automatic_updates_complete(
			array( 'plugin' => array( $this->make_success_result( 'Foo' ) ) )
		);

		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * T3: T2 の状態から ignore_environment_type を true にすると transient が書かれるようになる.
	 *
	 * オプション変更が transient 書き込みの有無に反映されることの確認.
	 *
	 * @return void
	 */
	public function test_t3_ignore_environment_type_enables_transient_write() {
		$GLOBALS['fauc_test_options']['home']                                             = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain']                   = 'example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain_ignore_environment_type'] = true;
		$GLOBALS['fauc_test_env_type']                                                    = 'local';

		$controller = $this->new_controller();
		$controller->handle_automatic_updates_complete(
			array( 'plugin' => array( $this->make_success_result( 'Foo' ) ) )
		);

		$this->assertSame( array( 'plugin: Foo' ), get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * T4: 失敗結果のみのときは transient が書かれない.
	 *
	 * @return void
	 */
	public function test_t4_failure_only_does_not_write_transient() {
		$GLOBALS['fauc_test_options']['home']                           = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'example.com';
		$GLOBALS['fauc_test_env_type']                                  = 'production';

		$controller = $this->new_controller();
		$controller->handle_automatic_updates_complete(
			array( 'plugin' => array( $this->make_failure_result( 'Foo' ) ) )
		);

		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * T5: T1 実行後、正しい nonce・権限で dismiss すると transient が削除される（option は不変）.
	 *
	 * @return void
	 */
	public function test_t5_dismiss_after_write_deletes_transient_without_touching_options() {
		$GLOBALS['fauc_test_options']['home']                           = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'example.com';
		$GLOBALS['fauc_test_env_type']                                  = 'production';

		$controller = new FAUC_Test_Controller();
		$controller->handle_automatic_updates_complete(
			array( 'plugin' => array( $this->make_success_result( 'Foo' ) ) )
		);
		$this->assertSame( array( 'plugin: Foo' ), get_transient( self::TRANSIENT_KEY ) );

		$options_before = $GLOBALS['fauc_test_options'];

		try {
			$controller->handle_dismiss_auto_update_notice();
			$this->fail( 'FAUC_Test_Terminate_Exception が投げられるはずです' );
		} catch ( FAUC_Test_Terminate_Exception $e ) {
			// exit の代わりに投げられる想定の例外.
			unset( $e );
		}

		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
		$this->assertSame( $options_before, $GLOBALS['fauc_test_options'] );
	}

	/**
	 * T6: T1 実行後、権限なし/nonce不正では transient が削除されずに残る.
	 *
	 * @return void
	 */
	public function test_t6_failed_dismiss_leaves_transient_intact() {
		$GLOBALS['fauc_test_options']['home']                           = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'example.com';
		$GLOBALS['fauc_test_env_type']                                  = 'production';

		$controller = $this->new_controller();
		$controller->handle_automatic_updates_complete(
			array( 'plugin' => array( $this->make_success_result( 'Foo' ) ) )
		);

		$GLOBALS['fauc_test_current_user_can'] = false;

		try {
			$controller->handle_dismiss_auto_update_notice();
			$this->fail( 'FAUC_Test_WP_Die_Exception が投げられるはずです' );
		} catch ( FAUC_Test_WP_Die_Exception $e ) {
			unset( $e );
		}

		$this->assertSame( array( 'plugin: Foo' ), get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * T7: 既存 transient を書き換えても、判定は必ず option から再計算される
	 * （判定結果を transient にキャッシュしていないことのネガティブテスト）.
	 *
	 * @return void
	 */
	public function test_t7_decision_does_not_use_stale_transient_content() {
		$GLOBALS['fauc_test_options']['home']                           = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'example.com';

		// 判定結果らしき値を transient に混入させても影響しないことを確認する.
		set_transient( self::TRANSIENT_KEY, array( 'plugin: Stale' ), WEEK_IN_SECONDS );

		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain_excluded_plugins'] = array( 'akismet/akismet.php' );

		$controller = $this->new_controller();
		$item       = (object) array( 'plugin' => 'akismet/akismet.php' );

		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_plugin', array( true, $item ) ) );
		$this->assertSame( array( 'akismet/akismet.php' ), $GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain_excluded_plugins'] );
	}

	/**
	 * T8: 設定保存経路（sanitize_* コールバック）を通しても、FAUC は判定用の
	 * transient を新設しない（キャッシュしていないことのネガティブテスト）.
	 *
	 * @return void
	 */
	public function test_t8_sanitize_callbacks_do_not_create_transients() {
		$controller = $this->new_controller();

		$this->invoke( $controller, 'sanitize_domain_pattern', array( 'example.com' ) );
		$this->invoke( $controller, 'sanitize_plugin_checklist', array( array() ) );
		$this->invoke( $controller, 'sanitize_theme_checklist', array( array() ) );

		$this->assertSame( array(), $GLOBALS['fauc_test_transients'] );
	}
}
