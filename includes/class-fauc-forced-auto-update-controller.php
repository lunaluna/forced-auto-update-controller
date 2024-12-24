<?php
/**
 * FAUC_Forced_Auto_Updates_Controller クラスファイル
 *
 * ドメインパターンを指定し、本番環境（パターン一致）なら
 *   - コア/プラグイン/テーマ/翻訳ファイルの自動更新を強制的に有効化
 *   - プラグイン/テーマ一覧に自動更新トグルUI (WP5.5+) を表示
 * それ以外の環境では自動更新を無効化し、UI も非表示にする
 * 優先度 9999 を指定して最終的に上書き
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * メインクラス: FAUC_Auto_Updates_Controller
 */
class FAUC_Auto_Updates_Controller {

	/**
	 * 保存するオプション名 (DB 上のキー)
	 *
	 * @var string
	 */
	private $option_name = 'FAUC_forced_auto_update_domain';

	/**
	 * コンストラクタ
	 *
	 * - フィルターフック・アクションフックの登録を行う
	 */
	public function __construct() {

		// 設定ページの追加.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

		// 設定欄・フィールドを初期化.
		add_action( 'admin_init', array( $this, 'settings_init' ) );

		// (1) Git(VCS)チェックを無視 (本番なら VCS下でも自動更新).
		add_filter( 'automatic_updates_is_vcs_checkout', array( $this, 'control_vcs_check' ), 10, 1 );

		// (2) コア自動更新: 優先度 9999 で最終上書き.
		add_filter( 'auto_update_core', array( $this, 'control_auto_update_core' ), 9999, 1 );

		// (3) プラグイン自動更新: 優先度 9999 で最終上書き.
		add_filter( 'auto_update_plugin', array( $this, 'control_auto_update_plugin' ), 9999, 2 );

		// (4) テーマ自動更新: 優先度 9999 で最終上書き.
		add_filter( 'auto_update_theme', array( $this, 'control_auto_update_theme' ), 9999, 2 );

		// (5) 翻訳ファイル自動更新: 優先度 9999 で最終上書き.
		add_filter( 'auto_update_translation', array( $this, 'control_auto_update_translation' ), 9999, 1 );

		// (6) プラグイン一覧の自動更新UI (WP5.5+): 優先度9999で最終上書き.
		add_filter( 'plugins_auto_update_enabled', array( $this, 'control_auto_update_ui_for_plugins' ), 9999, 1 );

		// (7) テーマ一覧の自動更新UI (WP5.5+): 優先度9999で最終上書き.
		add_filter( 'themes_auto_update_enabled', array( $this, 'control_auto_update_ui_for_themes' ), 9999, 1 );

		/**
		 * (8) 管理者 & 本番環境のみダッシュボードにメタボックス追加
		 */
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_meta_box_warning' ) );
	}

	/**
	 * 管理画面メニューに「Forced Auto Update Controller」ページを追加
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Forced Auto Update Control', 'forced-auto-update-controller' ), // ページタイトル.
			__( 'Forced Auto Update Control', 'forced-auto-update-controller' ), // メニュータイトル.
			'manage_options',                                                    // 権限.
			'fauc-forced-auto-updates-controller',                               // スラッグ.
			array( $this, 'render_settings_page' )                               // コールバック.
		);
	}

	/**
	 * 設定欄・セクション・フィールドを登録
	 *
	 * @return void
	 */
	public function settings_init() {

		// セクション登録.
		add_settings_section(
			'FAUC_forced_auto_update_section',
			__( 'Auto Updates 設定', 'forced-auto-update-controller' ),
			array( $this, 'settings_section_callback' ),
			'fauc-forced-auto-updates-controller'
		);

		// ドメインパターン入力フィールド登録.
		add_settings_field(
			'FAUC_forced_auto_update_domain_field',
			__( '本番環境URL(ドメイン)パターン', 'forced-auto-update-controller' ),
			array( $this, 'domain_field_callback' ),
			'fauc-forced-auto-updates-controller',
			'FAUC_forced_auto_update_section'
		);

		// オプション登録 (sanitize_text_field).
		register_setting(
			'fauc-forced-auto-updates-controller',
			$this->option_name,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * セクション説明文
	 *
	 * @return void
	 */
	public function settings_section_callback() {
		echo '<p>';
		echo esc_html__(
			'指定したドメインを含む環境でのみ自動アップデートをすべて有効化し、それ以外の環境ではすべて無効化します。他プラグインで無効化されていても最終的に上書きします。',
			'forced-auto-update-controller'
		);
		echo '</p>';
	}

	/**
	 * ドメインパターン入力フィールドのHTMLを出力
	 *
	 * @return void
	 */
	public function domain_field_callback() {
		$value = get_option( $this->option_name );
		printf(
			'<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
			esc_attr( $this->option_name ),
			esc_attr( $value ),
			esc_attr__( '例: example.com', 'forced-auto-update-controller' )
		);
	}

	/**
	 * 設定ページのHTMLを描画
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Forced Auto Update Control 設定', 'forced-auto-update-controller' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'fauc-forced-auto-updates-controller' );
				do_settings_sections( 'fauc-forced-auto-updates-controller' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * 現在の環境が本番環境かどうかを判定
	 *
	 * @return bool true: 本番 / false: 非本番
	 */
	private function is_production_domain() {
		$pattern = get_option( $this->option_name );
		$host    = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';

		if ( ! empty( $pattern ) && ! empty( $host ) && false !== strpos( $host, $pattern ) ) {
			return true;
		}
		return false;
	}

	/**
	 * (1) Git(VCS) 下でも自動更新を許可するかどうか制御
	 *
	 * @param bool $checkout true: VCS管理下, false: 非管理
	 * @return bool
	 */
	public function control_vcs_check( $checkout ) {
		if ( $this->is_production_domain() ) {
			// 本番なら VCS チェックを無効化 => false で自動更新を許可.
			return false;
		}
		// 非本番はデフォルト挙動に従う.
		return $checkout;
	}

	/**
	 * (2) コア自動更新フィルタ
	 *
	 * @param bool $update コア自動更新許可フラグ
	 * @return bool
	 */
	public function control_auto_update_core( $update ) {
		return $this->is_production_domain();
	}

	/**
	 * (3) プラグイン自動更新フィルタ
	 *
	 * @param bool   $update 自動更新許可フラグ
	 * @param object $item   プラグイン情報
	 * @return bool
	 */
	public function control_auto_update_plugin( $update, $item ) {
		return $this->is_production_domain();
	}

	/**
	 * (4) テーマ自動更新フィルタ
	 *
	 * @param bool   $update 自動更新許可フラグ
	 * @param object $item   テーマ情報
	 * @return bool
	 */
	public function control_auto_update_theme( $update, $item ) {
		return $this->is_production_domain();
	}

	/**
	 * (5) 翻訳ファイル自動更新フィルタ
	 *
	 * @param bool $update 自動更新許可フラグ
	 * @return bool
	 */
	public function control_auto_update_translation( $update ) {
		return $this->is_production_domain();
	}

	/**
	 * (6) プラグイン一覧の自動更新UI表示フィルタ
	 *
	 * @param bool $enabled true: 表示, false: 非表示
	 * @return bool
	 */
	public function control_auto_update_ui_for_plugins( $enabled ) {
		return $this->is_production_domain();
	}

	/**
	 * (7) テーマ一覧の自動更新UI表示フィルタ
	 *
	 * @param bool $enabled true: 表示, false: 非表示
	 * @return bool
	 */
	public function control_auto_update_ui_for_themes( $enabled ) {
		return $this->is_production_domain();
	}

	/**
	 * (8) 管理者 & 本番環境のみ、ダッシュボードにメタボックスを追加
	 *
	 * @return void
	 */
	public function add_dashboard_meta_box_warning() {
		// 管理者（manage_options 権限）かどうかを確認.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 本番環境かどうかを確認.
		if ( $this->is_production_domain() ) {
			wp_add_dashboard_widget(
				'fauc_git_integration_warning',
				__( 'Forced Auto Update Controller Notice', 'forced-auto-update-controller' ),
				array( $this, 'render_dashboard_meta_box_warning' )
			);
		}
	}

	/**
	 * ダッシュボードメタボックスに表示する内容
	 *
	 * @return void
	 */
	public function render_dashboard_meta_box_warning() {
		echo '<p>';
		echo esc_html__(
			'このサイトは Git 管理されていますが、Forced Auto update Controller プラグインの設定により自動更新が有効にできるようになっています。
サーバ上のファイルが自動で更新され、Git 管理との整合が崩れる恐れがありますので、更新後に差分をコミットする、あるいはステージング環境でテストした後に本番へ手動デプロイするなど、Git との連携において留意すべき点があることに充分注意してください',
			'forced-auto-update-controller'
		);
		echo '</p>';
	}

	/**
	 * アンインストール時に呼び出されるオプション削除 (uninstall.phpで実行)
	 *
	 * @return void
	 */
	public function uninstall() {
		delete_option( $this->option_name );
	}
}
