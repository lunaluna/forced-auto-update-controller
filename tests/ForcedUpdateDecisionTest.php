<?php
/**
 * FAUC_Auto_Update_Controller::control_auto_update_plugin() /
 * control_auto_update_theme() / control_auto_update_ui_for_plugins() /
 * control_auto_update_ui_for_themes() の回帰テスト.
 *
 * ③「強制ONが機能していない（null が素通しされる）」と
 * ⑤「未設定時に自動更新列が消える」の再発防止（表B B1〜B5）.
 * null/true/false を assertSame で厳密に区別する（assertEquals では
 * null == false で通ってしまい、今回のバグを検出できないため）.
 *
 * @package ForcedAutoUpdateController
 */

require_once __DIR__ . '/class-fauc-test-case.php';

/**
 * ForcedUpdateDecisionTest クラス.
 */
class ForcedUpdateDecisionTest extends FAUC_Test_Case {

	/**
	 * B1: ドメインパターン未設定時は UI フィルタ・判定フィルタとも素通しする.
	 *
	 * @return void
	 */
	public function test_b1_unconfigured_passes_through() {
		$controller = $this->new_controller();

		$this->assertTrue( $this->invoke( $controller, 'control_auto_update_ui_for_plugins', array( true ) ) );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_ui_for_plugins', array( false ) ) );
		$this->assertTrue( $this->invoke( $controller, 'control_auto_update_ui_for_themes', array( true ) ) );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_ui_for_themes', array( false ) ) );

		$plugin_item = (object) array( 'plugin' => 'hello-dolly/hello.php' );
		$this->assertNull( $this->invoke( $controller, 'control_auto_update_plugin', array( null, $plugin_item ) ) );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_plugin', array( false, $plugin_item ) ) );

		$theme_item = (object) array( 'theme' => 'twentytwentytwo' );
		$this->assertNull( $this->invoke( $controller, 'control_auto_update_theme', array( null, $theme_item ) ) );
	}

	/**
	 * B2: 設定済みだが不一致（active=false）のとき、UI列は非表示・判定は強制 false.
	 *
	 * @return void
	 */
	public function test_b2_configured_but_inactive() {
		$GLOBALS['fauc_test_options']['home']                           = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'other.com';

		$controller = $this->new_controller();

		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_ui_for_plugins', array( true ) ) );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_ui_for_themes', array( true ) ) );

		$plugin_item = (object) array( 'plugin' => 'hello-dolly/hello.php' );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_plugin', array( null, $plugin_item ) ) );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_plugin', array( true, $plugin_item ) ) );
	}

	/**
	 * B3: active=true かつ除外リストに含まれるプラグイン/テーマは強制設定に関わらず false.
	 *
	 * @return void
	 */
	public function test_b3_excluded_item_is_always_false() {
		$GLOBALS['fauc_test_options']['home']                                              = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain']                    = 'example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain_excluded_plugins']    = array( 'hello-dolly/hello.php' );
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain_excluded_themes']     = array( 'twentytwentytwo' );

		$controller = $this->new_controller();

		// UIフィルタは除外リストと無関係に active の値をそのまま返す（列自体は表示される）.
		$this->assertTrue( $this->invoke( $controller, 'control_auto_update_ui_for_plugins', array( false ) ) );

		$excluded_plugin = (object) array( 'plugin' => 'hello-dolly/hello.php' );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_plugin', array( true, $excluded_plugin ) ) );

		$excluded_theme = (object) array( 'theme' => 'twentytwentytwo' );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_theme', array( true, $excluded_theme ) ) );
	}

	/**
	 * B4: active=true・除外なし・強制ON設定（既定）のとき、null/false も true に強制される.
	 *
	 * これが③のバグの直接の回帰テスト: 修正前は return $update; のため
	 * null がそのまま素通しされ、コアが「強制なし」と解釈していた.
	 *
	 * @return void
	 */
	public function test_b4_force_non_excluded_default_on() {
		$GLOBALS['fauc_test_options']['home']                           = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain'] = 'example.com';

		$controller = $this->new_controller();

		$plugin_item = (object) array( 'plugin' => 'akismet/akismet.php' );
		$this->assertTrue( $this->invoke( $controller, 'control_auto_update_plugin', array( null, $plugin_item ) ) );
		$this->assertTrue( $this->invoke( $controller, 'control_auto_update_plugin', array( false, $plugin_item ) ) );

		$theme_item = (object) array( 'theme' => 'twentytwentythree' );
		$this->assertTrue( $this->invoke( $controller, 'control_auto_update_theme', array( null, $theme_item ) ) );
		$this->assertTrue( $this->invoke( $controller, 'control_auto_update_theme', array( false, $theme_item ) ) );
	}

	/**
	 * B5: 強制ON設定を OFF にすると、1.9.1 までと同じ「個別トグル尊重」に戻る.
	 *
	 * @return void
	 */
	public function test_b5_force_non_excluded_off_respects_individual_toggle() {
		$GLOBALS['fauc_test_options']['home']                                              = 'https://example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain']                    = 'example.com';
		$GLOBALS['fauc_test_options']['FAUC_forced_auto_update_domain_force_non_excluded'] = false;

		$controller = $this->new_controller();

		$plugin_item = (object) array( 'plugin' => 'akismet/akismet.php' );
		$this->assertNull( $this->invoke( $controller, 'control_auto_update_plugin', array( null, $plugin_item ) ) );
		$this->assertFalse( $this->invoke( $controller, 'control_auto_update_plugin', array( false, $plugin_item ) ) );
		$this->assertTrue( $this->invoke( $controller, 'control_auto_update_plugin', array( true, $plugin_item ) ) );
	}
}
