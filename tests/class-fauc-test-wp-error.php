<?php
/**
 * FAUC_Test_WP_Error クラスファイル.
 *
 * @package ForcedAutoUpdateController
 */

/**
 * WP_Error の最小限のスタブ. is_wp_error() の判定対象としてのみ使う.
 */
class FAUC_Test_WP_Error {

	/**
	 * コンストラクタ.
	 *
	 * @param string $code    エラーコード（未使用）.
	 * @param string $message エラーメッセージ（未使用）.
	 */
	public function __construct( $code = '', $message = '' ) {
		unset( $code, $message );
	}
}
