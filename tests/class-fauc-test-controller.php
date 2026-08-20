<?php
/**
 * FAUC_Test_Controller クラスファイル.
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-terminate-exception.php';

/**
 * FAUC_Auto_Update_Controller のテスト用サブクラス.
 *
 * terminate_request() が exit する代わりに例外を投げるようにすることで、
 * handle_dismiss_auto_update_notice() の正常系（wp_safe_redirect() の後に
 * exit する経路）を PHPUnit プロセスを終了させずにテストできるようにする.
 */
class FAUC_Test_Controller extends FAUC_Auto_Update_Controller {

	/**
	 * exit の代わりに例外を投げる.
	 *
	 * @return void
	 * @throws FAUC_Test_Terminate_Exception 常に投げる.
	 */
	protected function terminate_request() {
		throw new FAUC_Test_Terminate_Exception();
	}
}
