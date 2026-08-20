<?php
/**
 * FAUC_Auto_Update_Controller のテスト用基底クラス.
 *
 * @package ForcedAutoUpdateController
 */

use PHPUnit\Framework\TestCase;

/**
 * 全テストで共有するグローバルスタブのリセットと、
 * private メソッドへ Reflection でアクセスするヘルパを提供する.
 */
abstract class FAUC_Test_Case extends TestCase {

	/**
	 * 各テスト前にスタブのグローバル状態を初期化する.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['fauc_test_options']          = array();
		$GLOBALS['fauc_test_site_options']     = array();
		$GLOBALS['fauc_test_env_type']         = 'production';
		$GLOBALS['fauc_test_filters']          = array();
		$GLOBALS['fauc_test_settings_errors']  = array();
		$GLOBALS['fauc_test_transients']       = array();
		$GLOBALS['fauc_test_site_transients']  = array();
		$GLOBALS['fauc_test_current_user_can'] = true;
		$GLOBALS['fauc_test_admin_referer_ok'] = true;
		$GLOBALS['fauc_test_referer']          = false;
		$GLOBALS['fauc_test_wp_die_calls']     = array();
		$GLOBALS['fauc_test_redirects']        = array();

		unset( $GLOBALS['wp_version'] );
	}

	/**
	 * FAUC_Auto_Update_Controller の新しいインスタンスを生成する.
	 *
	 * コンストラクタは add_action / add_filter を呼ぶのみで副作用を持たない
	 * ためテストのたびに new して問題ない.
	 *
	 * @return FAUC_Auto_Update_Controller
	 */
	protected function new_controller() {
		return new FAUC_Auto_Update_Controller();
	}

	/**
	 * private/protected メソッドを Reflection 経由で呼び出す.
	 *
	 * @param object $object 対象インスタンス.
	 * @param string $method メソッド名.
	 * @param array  $args   引数.
	 * @return mixed
	 */
	protected function invoke( $object, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( get_class( $object ), $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $object, $args );
	}
}
