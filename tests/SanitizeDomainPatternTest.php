<?php
/**
 * FAUC_Auto_Update_Controller::sanitize_domain_pattern() の回帰テスト.
 *
 * 各データは実物のクラスに対する実測に基づく（推測で期待値を書いていない）.
 * エラーコードは add_settings_error() に渡されるコード文字列で、
 *   - FAUC_invalid_domain_pattern_format: 個々の行の形式が不正
 *   - FAUC_invalid_domain_pattern:        有効なパターンが1つも残らなかった（全体エラー）
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-case.php';

/**
 * SanitizeDomainPatternTest クラス.
 */
class SanitizeDomainPatternTest extends FAUC_Test_Case {

	/**
	 * サニタイズを実行し、出力とエラーコード一覧を返す.
	 *
	 * @param mixed $input サニタイズ対象の入力値.
	 * @return array{0:string,1:string[]} 出力と発生したエラーコードの配列.
	 */
	private function sanitize( $input ) {
		$controller = $this->new_controller();
		$output     = $controller->sanitize_domain_pattern( $input );

		$codes = array_map(
			function ( $error ) {
				return $error['code'];
			},
			$GLOBALS['fauc_test_settings_errors']
		);

		return array( $output, $codes );
	}

	/**
	 * https:// プレフィックスと末尾スラッシュを除去し、小文字化して返すこと.
	 *
	 * @return void
	 */
	public function test_basic_domain_is_normalized() {
		list( $output, $codes ) = $this->sanitize( 'https://EXAMPLE.com/' );

		$this->assertSame( 'example.com', $output );
		$this->assertCount( 0, $codes );
	}

	/**
	 * 単一行入力のバリエーション.
	 *
	 * @dataProvider provide_single_line_patterns
	 *
	 * @param string   $input          入力.
	 * @param string   $expected       期待する出力.
	 * @param string[] $expected_codes 期待するエラーコード一覧.
	 * @return void
	 */
	public function test_single_line_patterns( $input, $expected, array $expected_codes ) {
		list( $output, $codes ) = $this->sanitize( $input );

		$this->assertSame( $expected, $output );
		$this->assertSame( $expected_codes, $codes );
	}

	/**
	 * 表D1〜D25（単一行）.
	 *
	 * @return array
	 */
	public function provide_single_line_patterns() {
		$format = 'FAUC_invalid_domain_pattern_format';
		$empty  = 'FAUC_invalid_domain_pattern';

		return array(
			'D1: 基本形'                          => array( 'example.com', 'example.com', array() ),
			'D2: 大文字は小文字化される'          => array( 'EXAMPLE.COM', 'example.com', array() ),
			'D3: https:// は除去される'           => array( 'https://example.com', 'example.com', array() ),
			'D4: http:// + 末尾スラッシュ'        => array( 'http://example.com/', 'example.com', array() ),
			'D5: パス1階層'                        => array( 'example.com/sample', 'example.com/sample', array() ),
			'D6: パス3階層'                        => array( 'example.com/a/b/c', 'example.com/a/b/c', array() ),
			'D7: ポート番号'                       => array( 'example.com:8080', 'example.com:8080', array() ),
			'D8: ポート番号+パス'                  => array( 'example.com:8080/sub', 'example.com:8080/sub', array() ),
			'D9: localhost はドット無しで無効'    => array( 'localhost', '', array( $format, $empty ) ),
			'D10: localhost+ポートも無効'          => array( 'localhost:8888', '', array( $format, $empty ) ),
			'D11: IPv4(末尾2桁)は通る'             => array( '192.168.1.10', '192.168.1.10', array() ),
			'D12: IPv4(末尾1桁)は無効'             => array( '192.168.1.1', '', array( $format, $empty ) ),
			'D13: punycode は通る'                  => array( 'xn--eckwd4c7c.xn--zckzah', 'xn--eckwd4c7c.xn--zckzah', array() ),
			'D14: 日本語(IDN 生)は無効'            => array( '例え.テスト', '', array( $format, $empty ) ),
			'D15: パスに日本語は無効'              => array( 'example.com/日本語', '', array( $format, $empty ) ),
			'D16: 先頭ハイフンは通ってしまう'      => array( '-example.com', '-example.com', array() ),
			'D17: 連続ドットは通ってしまう'        => array( 'example..com', 'example..com', array() ),
			'D18: TLD 1文字は無効'                  => array( 'example.c', '', array( $format, $empty ) ),
			'D19: 末尾スラッシュは除去'            => array( 'example.com/sample/', 'example.com/sample', array() ),
			'D20: 前後の空白は trim される'        => array( '  example.com  ', 'example.com', array() ),
			'D21: パス許容文字一式'                => array( 'example.com/path_with-under.dot~tilde', 'example.com/path_with-under.dot~tilde', array() ),
			'D22: パスに % は無効'                 => array( 'example.com/path%20space', '', array( $format, $empty ) ),
			'D23: クエリ文字列は無効'              => array( 'example.com?q=1', '', array( $format, $empty ) ),
			'D24: フラグメントは無効'              => array( 'example.com#frag', '', array( $format, $empty ) ),
			'D25: user@ 形式は無効'                 => array( 'user@example.com', '', array( $format, $empty ) ),
		);
	}

	/**
	 * 複数行入力のバリエーション.
	 *
	 * @dataProvider provide_multiline_patterns
	 *
	 * @param string   $input          入力.
	 * @param string   $expected       期待する出力.
	 * @param string[] $expected_codes 期待するエラーコード一覧.
	 * @return void
	 */
	public function test_multiline_patterns( $input, $expected, array $expected_codes ) {
		list( $output, $codes ) = $this->sanitize( $input );

		$this->assertSame( $expected, $output );
		$this->assertSame( $expected_codes, $codes );
	}

	/**
	 * 表D26〜D32（複数行）.
	 *
	 * @return array
	 */
	public function provide_multiline_patterns() {
		$format = 'FAUC_invalid_domain_pattern_format';
		$empty  = 'FAUC_invalid_domain_pattern';

		return array(
			'D26: LF 区切り2行'                         => array(
				"example.com\nwww.example.com",
				"example.com\nwww.example.com",
				array(),
			),
			'D27: CRLF は LF に正規化される'            => array(
				"example.com\r\nwww.example.com",
				"example.com\nwww.example.com",
				array(),
			),
			'D28: 空行スキップ + 重複排除'              => array(
				"example.com\n\n  \nexample.com",
				'example.com',
				array(),
			),
			'D29: 無効行はスキップし有効行だけ残す'     => array(
				"example.com\nbadpattern",
				'example.com',
				array( $format ),
			),
			'D30: 全行無効なら全体も空扱い'             => array(
				"bad\nalsobad",
				'',
				array( $format, $format, $empty ),
			),
			'D31: 空文字は全体エラーのみ'                => array(
				'',
				'',
				array( $empty ),
			),
			'D32: 空行のみも全体エラーのみ'              => array(
				"\n\n",
				'',
				array( $empty ),
			),
		);
	}

	/**
	 * register_setting() の sanitize_callback は sanitize_option_{$option}
	 * フィルタ経由で呼ばれうるため $input に非文字列（特に null）が渡る
	 * 可能性がある（メソッドの docblock に明記あり）.
	 *
	 * @dataProvider provide_non_string_inputs
	 *
	 * @param mixed    $input          入力.
	 * @param string[] $expected_codes 期待するエラーコード一覧.
	 * @return void
	 */
	public function test_non_string_inputs_are_coerced( $input, array $expected_codes ) {
		list( $output, $codes ) = $this->sanitize( $input );

		$this->assertSame( '', $output );
		$this->assertSame( $expected_codes, $codes );
	}

	/**
	 * 表D33〜D36（非文字列入力）.
	 *
	 * 配列入力は (string) キャストで "Array to string conversion" 警告が出て
	 * PHPUnit 9 が既定でこれを例外化するため対象外とする
	 * （register_setting() がこのオプションを 'type' => 'string' で登録して
	 * おり、配列が渡る現実的な経路も無い）.
	 *
	 * @return array
	 */
	public function provide_non_string_inputs() {
		$format = 'FAUC_invalid_domain_pattern_format';
		$empty  = 'FAUC_invalid_domain_pattern';

		return array(
			'D33: null'        => array( null, array( $empty ) ),
			'D34: int'         => array( 123, array( $format, $empty ) ),
			'D35: bool true'   => array( true, array( $format, $empty ) ),
			'D36: bool false'  => array( false, array( $empty ) ),
		);
	}
}
