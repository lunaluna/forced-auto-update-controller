<?php
/**
 * FAUC_GitHub_Updater の純粋関数に対する回帰テスト.
 *
 * @package ForcedAutoUpdateController
 */

use PHPUnit\Framework\TestCase;

/**
 * GitHubUpdaterTest クラス.
 */
class GitHubUpdaterTest extends TestCase {

	/**
	 * normalize_version() が "v" プレフィックスの有無・大文字小文字を問わず
	 * バージョン番号だけを返すことを確認する.
	 *
	 * @return void
	 */
	public function test_normalize_version_strips_leading_v() {
		$this->assertSame( '1.8.0', FAUC_GitHub_Updater::normalize_version( '1.8.0' ) );
		$this->assertSame( '1.8.0', FAUC_GitHub_Updater::normalize_version( 'v1.8.0' ) );
		$this->assertSame( '1.8.0', FAUC_GitHub_Updater::normalize_version( 'V1.8.0' ) );
	}

	/**
	 * normalize_version() は ltrim と異なり、先頭の "v" を1文字だけ取り除く
	 * ことを確認する（"vv1.0" のような値を壊さない）.
	 *
	 * @return void
	 */
	public function test_normalize_version_does_not_strip_repeated_v() {
		$this->assertSame( 'v1.0', FAUC_GitHub_Updater::normalize_version( 'vv1.0' ) );
	}

	/**
	 * extract_zip_url() がプラグインスラッグで始まる .zip アセットの
	 * ダウンロード URL を選ぶことを確認する.
	 *
	 * @return void
	 */
	public function test_extract_zip_url_picks_matching_asset() {
		$body = array(
			'assets' => array(
				array(
					'name'                 => 'source-code.zip',
					'browser_download_url' => 'https://example.com/source-code.zip',
				),
				array(
					'name'                 => 'forced-auto-update-controller.1.8.0.zip',
					'browser_download_url' => 'https://example.com/forced-auto-update-controller.1.8.0.zip',
				),
			),
		);

		$this->assertSame(
			'https://example.com/forced-auto-update-controller.1.8.0.zip',
			FAUC_GitHub_Updater::extract_zip_url( $body )
		);
	}

	/**
	 * extract_zip_url() は一致するアセットがなければ zipball 等へ
	 * フォールバックせず null を返すことを確認する（回帰テスト）.
	 *
	 * @return void
	 */
	public function test_extract_zip_url_returns_null_when_no_match() {
		$body = array(
			'assets' => array(
				array(
					'name'                 => 'source-code.zip',
					'browser_download_url' => 'https://example.com/source-code.zip',
				),
			),
		);

		$this->assertNull( FAUC_GitHub_Updater::extract_zip_url( $body ) );
	}

	/**
	 * extract_zip_url() はアセット配列が空でも null を返すことを確認する.
	 *
	 * @return void
	 */
	public function test_extract_zip_url_returns_null_when_assets_empty() {
		$this->assertNull( FAUC_GitHub_Updater::extract_zip_url( array( 'assets' => array() ) ) );
		$this->assertNull( FAUC_GitHub_Updater::extract_zip_url( array() ) );
	}

	/**
	 * build_plugin_update_object() が update_plugins トランジェント登録に
	 * 必要なキーを備えたオブジェクトを返すことを確認する.
	 *
	 * @return void
	 */
	public function test_build_plugin_update_object_has_required_keys() {
		$release = array(
			'version' => '1.8.0',
			'zip_url' => 'https://example.com/forced-auto-update-controller.1.8.0.zip',
			'notes'   => 'Release notes.',
		);

		$update = FAUC_GitHub_Updater::build_plugin_update_object( $release );

		$this->assertSame( 'forced-auto-update-controller', $update->slug );
		$this->assertSame( FAUC_PLUGIN_BASENAME, $update->plugin );
		$this->assertSame( '1.8.0', $update->new_version );
		$this->assertSame( 'https://example.com/forced-auto-update-controller.1.8.0.zip', $update->package );
	}
}
