<?php
/**
 * FAUC_Auto_Update_Controller::sanitize_domain_pattern() の回帰テスト.
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-case.php';

/**
 * SanitizeDomainPatternTest クラス.
 */
class SanitizeDomainPatternTest extends FAUC_Test_Case {

	/**
	 * https:// プレフィックスと末尾スラッシュを除去し、小文字化して返すこと.
	 *
	 * @return void
	 */
	public function test_basic_domain_is_normalized() {
		$controller = $this->new_controller();

		$this->assertSame( 'example.com', $controller->sanitize_domain_pattern( 'https://EXAMPLE.com/' ) );
		$this->assertCount( 0, $GLOBALS['fauc_test_settings_errors'] );
	}
}
