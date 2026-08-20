<?php
/**
 * FAUC_Auto_Update_Controller::compute_control_status() の回帰テスト.
 *
 * 設定画面と notice の判定不整合（②）を再発させないため、
 * active と reason を両方 assertSame で固定する（表A A1〜A9）。
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-case.php';

/**
 * ControlStatusTest クラス.
 */
class ControlStatusTest extends FAUC_Test_Case {

	/**
	 * 表A の全分岐.
	 *
	 * @dataProvider provide_control_status_cases
	 *
	 * @param string $home             get_option('home') に入れる値.
	 * @param string $pattern          ドメインパターン設定値.
	 * @param string $env_type         wp_get_environment_type() が返す値.
	 * @param bool   $ignore_env_type  「WP_ENVIRONMENT_TYPE を無視する」設定値.
	 * @param mixed  $filter_override  fauc_is_production_domain フィルタの戻り値（null なら未登録）.
	 * @param bool   $expected_active  期待する active です.
	 * @param string $expected_reason  期待する reason です.
	 * @return void
	 */
	public function test_control_status_cases( $home, $pattern, $env_type, $ignore_env_type, $filter_override, $expected_active, $expected_reason ) {
		$GLOBALS['fauc_test_options']['home']                                              = $home;
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain']                    = $pattern;
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain_ignore_environment_type'] = $ignore_env_type;
		$GLOBALS['fauc_test_env_type']                                                     = $env_type;

		if ( null !== $filter_override ) {
			$GLOBALS['fauc_test_filters']['fauc_is_production_domain'] = function ( $result, $patterns ) use ( $filter_override ) {
				return $filter_override;
			};
		}

		$controller = $this->new_controller();
		$status     = $this->invoke( $controller, 'compute_control_status' );

		$this->assertSame( $expected_active, $status['active'], 'active が期待値と異なります' );
		$this->assertSame( $expected_reason, $status['reason'], 'reason が期待値と異なります' );
	}

	/**
	 * 表A A1〜A9 のデータセット.
	 *
	 * @return array
	 */
	public function provide_control_status_cases() {
		return array(
			'A1: パターン未設定 => unconfigured'                     => array( 'https://example.com', '', 'production', false, null, true, 'unconfigured' ),
			'A2: local かつ無視OFF => environment_type'              => array( 'https://example.com', 'example.com', 'local', false, null, false, 'environment_type' ),
			'A3: local かつ無視ON・一致 => active'                    => array( 'https://example.com', 'example.com', 'local', true, null, true, 'active' ),
			'A4: local かつ無視ON・不一致 => domain_mismatch'         => array( 'https://example.com', 'other.com', 'local', true, null, false, 'domain_mismatch' ),
			'A5: production かつ host取得不能 => no_host'             => array( '', 'example.com', 'production', false, null, false, 'no_host' ),
			'A6: production かつ不一致 => domain_mismatch'            => array( 'https://example.com', 'other.com', 'production', false, null, false, 'domain_mismatch' ),
			'A7: production かつ一致 => active'                       => array( 'https://example.com', 'example.com', 'production', false, null, true, 'active' ),
			'A8: 一致だがフィルタで false に上書き => filtered_off'   => array( 'https://example.com', 'example.com', 'production', false, false, false, 'filtered_off' ),
			'A9: 不一致だがフィルタで true に上書き => active'        => array( 'https://example.com', 'other.com', 'production', false, true, true, 'active' ),
		);
	}

	/**
	 * get_control_status() が compute_control_status() の結果をメモ化すること.
	 *
	 * @return void
	 */
	public function test_get_control_status_is_memoized() {
		$GLOBALS['fauc_test_options']['home']                            = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain']  = 'example.com';

		$controller = $this->new_controller();

		$first = $this->invoke( $controller, 'get_control_status' );

		$GLOBALS['fauc_test_options']['home'] = 'https://changed.example';

		$second = $this->invoke( $controller, 'get_control_status' );

		$this->assertSame( $first, $second );
	}
}
