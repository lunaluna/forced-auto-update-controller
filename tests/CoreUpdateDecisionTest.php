<?php
/**
 * コア自動更新の可否判定に関わる回帰テスト.
 *
 * - is_core_major_update():                現行バージョンと更新対象のX.Y比較
 * - is_major_core_auto_update_enabled():   auto_update_core_major オプションの多形解釈
 *
 * 各データは実物のクラスに対する実測に基づく（推測で期待値を書いていない）.
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-case.php';

/**
 * CoreUpdateDecisionTest クラス.
 */
class CoreUpdateDecisionTest extends FAUC_Test_Case {

	/**
	 * is_core_major_update() のバージョン比較.
	 *
	 * @dataProvider provide_version_pairs
	 *
	 * @param string $current_version $wp_version にセットする現行バージョン.
	 * @param string $new_version     $item->version にセットする更新対象バージョン（空文字はプロパティ自体を作らない印）.
	 * @param bool   $has_version     $item に version プロパティを持たせるか.
	 * @param bool   $expected        期待するメジャー判定.
	 * @return void
	 */
	public function test_is_core_major_update( $current_version, $new_version, $has_version, $expected ) {
		$GLOBALS['wp_version'] = $current_version;

		$item = new stdClass();
		if ( $has_version ) {
			$item->version = $new_version;
		}

		$controller = $this->new_controller();

		$this->assertSame( $expected, $this->invoke( $controller, 'is_core_major_update', array( $item ) ) );
	}

	/**
	 * 表B1〜B13.
	 *
	 * @return array
	 */
	public function provide_version_pairs() {
		return array(
			'B1: マイナー更新'                       => array( '6.4.3', '6.4.4', true, false ),
			'B2: メジャー更新(マイナー桁が変わる)'   => array( '6.4.3', '6.5', true, true ),
			'B3: メジャー更新(メジャー桁が変わる)'   => array( '6.4.3', '7.0', true, true ),
			'B4: 現行が2要素のみ'                     => array( '6.4', '6.4.1', true, false ),
			'B5: 完全一致'                             => array( '6.4.3', '6.4.3', true, false ),
			'B6: 更新対象バージョンが空'              => array( '6.4.3', '', true, true ),
			'B7: 現行バージョンが空'                  => array( '', '6.5', true, true ),
			'B8: 現行が1要素のみ'                      => array( '6', '6.5', true, true ),
			'B9: 更新対象が1要素のみ'                  => array( '6.4.3', '7', true, true ),
			'B10: 数値比較(10 は 4 より大きい)'        => array( '6.4.3', '6.10.0', true, true ),
			'B11: 現行に接尾辞があってもX.Yのみ比較'  => array( '6.4.3-alpha', '6.4.4', true, false ),
			'B12: 更新対象に接尾辞があってもX.Yのみ比較' => array( '6.4.3', '6.4.4-RC1', true, false ),
			'B13: version プロパティ自体が無い'        => array( '6.4.3', '', false, true ),
		);
	}

	/**
	 * is_major_core_auto_update_enabled() のオプション値解釈.
	 *
	 * @dataProvider provide_option_values
	 *
	 * @param mixed $site_option ネットワークオプション（get_site_option）に入れる値。null なら未設定.
	 * @param mixed $option      単一サイトオプション（get_option）に入れる値。null なら未設定.
	 * @param bool  $expected    期待する真偽値.
	 * @return void
	 */
	public function test_is_major_core_auto_update_enabled( $site_option, $option, $expected ) {
		if ( null !== $site_option ) {
			$GLOBALS['fauc_test_site_options']['auto_update_core_major'] = $site_option;
		}
		if ( null !== $option ) {
			$GLOBALS['fauc_test_options']['auto_update_core_major'] = $option;
		}

		$controller = $this->new_controller();

		$this->assertSame( $expected, $this->invoke( $controller, 'is_major_core_auto_update_enabled' ) );
	}

	/**
	 * 表C1〜C19.
	 *
	 * @return array
	 */
	public function provide_option_values() {
		return array(
			"C1: site_option='enabled'"        => array( 'enabled', null, true ),
			"C2: site_option='disabled'"       => array( 'disabled', null, false ),
			"C3: site_option='ENABLED'(大文字)" => array( 'ENABLED', null, true ),
			"C4: site_option='  enabled  '(空白)" => array( '  enabled  ', null, true ),
			"C5: site_option='on'"              => array( 'on', null, true ),
			"C6: site_option='off'"             => array( 'off', null, false ),
			"C7: site_option='true'"            => array( 'true', null, true ),
			"C8: site_option='false'"           => array( 'false', null, false ),
			"C9: site_option='1'"               => array( '1', null, true ),
			"C10: site_option='0'"              => array( '0', null, false ),
			"C11: site_option='unknown'(既定へ)" => array( 'unknown', null, false ),
			'C12: site_option=true'             => array( true, null, true ),
			'C13: site_option=false'            => array( false, null, false ),
			'C14: site_option=1'                => array( 1, null, true ),
			'C15: site_option=0'                => array( 0, null, false ),
			"C16: site未設定+option='enabled'"  => array( null, 'enabled', true ),
			"C17: site未設定+option='disabled'" => array( null, 'disabled', false ),
			'C18: 両方未設定(既定 false)'        => array( null, null, false ),
			'C19: site未設定+option=true'        => array( null, true, true ),
		);
	}
}
