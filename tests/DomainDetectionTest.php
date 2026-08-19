<?php
/**
 * FAUC_Auto_Update_Controller::compute_is_production_domain() の回帰テスト.
 *
 * ドメイン照合の中核ロジック。環境タイプ判定・ホスト+パス構築・正規表現照合・
 * フィルタ上書き・FAUC_PRODUCTION_DOMAIN 定数優先を検証する。
 * 各データは実物のクラスに対する実測に基づく（推測で期待値を書いていない）.
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-case.php';

/**
 * DomainDetectionTest クラス.
 */
class DomainDetectionTest extends FAUC_Test_Case {

	/**
	 * home オプションとドメインパターンの組み合わせ.
	 *
	 * @dataProvider provide_domain_combinations
	 *
	 * @param string $home     get_option('home') に入れる値.
	 * @param string $pattern  ドメインパターン設定値（複数行の場合あり）.
	 * @param string $env_type wp_get_environment_type() が返す値.
	 * @param bool   $expected 期待する判定結果.
	 * @return void
	 */
	public function test_domain_combinations( $home, $pattern, $env_type, $expected ) {
		$GLOBALS['fauc_test_options']['home']                       = $home;
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = $pattern;
		$GLOBALS['fauc_test_env_type']                              = $env_type;

		$controller = $this->new_controller();

		$this->assertSame( $expected, $this->invoke( $controller, 'compute_is_production_domain' ) );
	}

	/**
	 * 表A1〜A19.
	 *
	 * @return array
	 */
	public function provide_domain_combinations() {
		return array(
			'A1: 基本一致'                             => array( 'https://example.com', 'example.com', 'production', true ),
			'A2: 環境タイプが staging なら不一致扱い'  => array( 'https://example.com', 'example.com', 'staging', false ),
			'A3: 環境タイプが local なら不一致扱い'    => array( 'https://example.com', 'example.com', 'local', false ),
			'A4: home 側が大文字'                       => array( 'https://EXAMPLE.com', 'example.com', 'production', true ),
			'A5: パターン側が大文字'                    => array( 'https://example.com', 'EXAMPLE.COM', 'production', true ),
			'A6: パターンにスキームが付いていても可'    => array( 'https://example.com', 'https://example.com', 'production', true ),
			'A7: パターンの末尾スラッシュは無視される'  => array( 'https://example.com', 'example.com/', 'production', true ),
			'A8: パス一致'                               => array( 'https://example.com/sub', 'example.com/sub', 'production', true ),
			'A9: パス欠落は不一致'                       => array( 'https://example.com/sub', 'example.com', 'production', false ),
			'A10: 余分なパスは不一致'                    => array( 'https://example.com', 'example.com/sub', 'production', false ),
			'A11: ポート一致'                            => array( 'https://example.com:8080', 'example.com:8080', 'production', true ),
			'A12: ポート欠落は不一致'                    => array( 'https://example.com:8080', 'example.com', 'production', false ),
			'A13: パターン未設定'                        => array( 'https://example.com', '', 'production', false ),
			'A14: home が空'                             => array( '', 'example.com', 'production', false ),
			'A15: home が URL として解釈できない'        => array( 'not-a-url', 'example.com', 'production', false ),
			'A16: 複数行の2つ目で一致'                   => array( 'https://example.com', "other.com\nexample.com", 'production', true ),
			'A17: 不正な行はスキップして後続で一致'      => array( 'https://example.com', "bad_pattern\nexample.com", 'production', true ),
			'A18: 多階層パス'                            => array( 'https://example.com/sub/deep', 'example.com/sub/deep', 'production', true ),
			'A19: サブドメインはワイルドカード一致しない' => array( 'https://www.example.com', 'example.com', 'production', false ),
		);
	}

	/**
	 * fauc_is_production_domain フィルタで判定結果を上書きできること.
	 *
	 * 不一致の状態からスタートし、フィルタが true を返せば最終結果も true になる.
	 *
	 * @return void
	 */
	public function test_filter_can_override_mismatch_to_true() {
		$GLOBALS['fauc_test_options']['home']                       = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'other.com';
		$GLOBALS['fauc_test_filters']['fauc_is_production_domain']  = function ( $result, $patterns ) {
			return true;
		};

		$controller = $this->new_controller();

		$this->assertTrue( $this->invoke( $controller, 'compute_is_production_domain' ) );
	}

	/**
	 * フィルタが非 bool を返しても (bool) キャストされること.
	 *
	 * @return void
	 */
	public function test_filter_non_bool_return_is_cast() {
		$GLOBALS['fauc_test_options']['home']                       = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'other.com';
		$GLOBALS['fauc_test_filters']['fauc_is_production_domain']  = function ( $result, $patterns ) {
			return 'truthy-string';
		};

		$controller = $this->new_controller();

		$this->assertTrue( $this->invoke( $controller, 'compute_is_production_domain' ) );
	}

	/**
	 * FAUC_PRODUCTION_DOMAIN 定数が DB の設定値より優先されること.
	 *
	 * define() は取り消せず後続テストを汚染するため別プロセスで隔離する.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_constant_overrides_db_value() {
		define( 'FAUC_PRODUCTION_DOMAIN', 'constant.example' );

		$GLOBALS['fauc_test_options']['home']                       = 'https://constant.example';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'db.example';
		$GLOBALS['fauc_test_env_type']                              = 'production';

		$controller = $this->new_controller();

		$this->assertTrue( $this->invoke( $controller, 'compute_is_production_domain' ) );
		$this->assertSame( array( 'constant.example' ), $this->invoke( $controller, 'get_domain_patterns' ) );
	}

	/**
	 * 定数が定義されていないことを確認する（別プロセスの define が
	 * 本体プロセスへ漏れていないことの裏付け）.
	 *
	 * @return void
	 */
	public function test_constant_is_not_defined_by_default() {
		$this->assertFalse( defined( 'FAUC_PRODUCTION_DOMAIN' ) );
	}

	/**
	 * is_production_domain() がインスタンス内でメモ化されること.
	 *
	 * 1回目の呼び出し後に home を変えても、同一インスタンスでの2回目の
	 * 呼び出しは1回目の結果を返す（都度の get_option / preg_match を避けるため）.
	 *
	 * @return void
	 */
	public function test_is_production_domain_is_memoized() {
		$GLOBALS['fauc_test_options']['home']                       = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'example.com';

		$controller = $this->new_controller();

		$first = $this->invoke( $controller, 'is_production_domain' );

		$GLOBALS['fauc_test_options']['home'] = 'https://changed.example';

		$second = $this->invoke( $controller, 'is_production_domain' );

		$this->assertTrue( $first );
		$this->assertSame( $first, $second );
	}
}
