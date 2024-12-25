<?php
/**
 * FAUC_Forced_Auto_update_Controller クラスファイル
 *
 * ドメインパターンを指定し、本番環境（パターン一致）なら
 *   - コア/プラグイン/テーマ/翻訳ファイルの自動更新を強制的に有効化
 *   - プラグイン/テーマ一覧に自動更新トグルUI (WP5.5+) を表示
 *   - ただし、チェックが入っているプラグイン・テーマは自動更新を除外
 * それ以外の環境では自動更新を無効化し、UI も非表示にする
 * 優先度 9999 を指定して最終的に上書き
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * メインクラス: FAUC_Auto_update_Controller
 */
class FAUC_Auto_update_Controller {

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

		// (3) プラグイン自動更新: 優先度 9999 で最終上書き. (チェックしたプラグインは除外)
		add_filter( 'auto_update_plugin', array( $this, 'control_auto_update_plugin' ), 9999, 2 );

		// (4) テーマ自動更新: 優先度 9999 で最終上書き. (チェックしたテーマは除外)
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
			'fauc-forced-auto-update-controller',                                // スラッグ.
			array( $this, 'render_settings_page' )                               // コールバック.
		);
	}

	/**
	 * 設定ページのHTMLを描画
	 *
	 * - add_options_page() のコールバックで呼び出されるメソッド
	 * - WordPress 管理画面での設定フォームを表示する
	 *
	 * @return void
	 */
	public function render_settings_page() {
		// 管理者権限を持たないユーザーは何もしない.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Forced Auto Update Control 設定', 'forced-auto-update-controller' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// settings_fields() は nonce 等のセキュリティフィールドを出力.
				settings_fields( 'fauc-forced-auto-update-controller' );

				// do_settings_sections() は設定セクションとフィールドを出力.
				do_settings_sections( 'fauc-forced-auto-update-controller' );

				// 「変更を保存」ボタンを出力.
				submit_button();
				?>
			</form>
		</div>
		<?php
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
			'fauc-forced-auto-update-controller'
		);

		// ドメインパターン入力フィールド.
		add_settings_field(
			'FAUC_forced_auto_update_domain_field',
			__( '本番環境URL(ドメイン)パターン', 'forced-auto-update-controller' ),
			array( $this, 'domain_field_callback' ),
			'fauc-forced-auto-update-controller',
			'FAUC_forced_auto_update_section'
		);

		/**
		 * ここからプラグイン・テーマのチェックリスト用
		 */

		// プラグインのチェックリスト.
		add_settings_field(
			'FAUC_plugin_checklist_field',
			__( '自動更新を除外したいプラグイン', 'forced-auto-update-controller' ),
			array( $this, 'plugin_checklist_field_callback' ),
			'fauc-forced-auto-update-controller',
			'FAUC_forced_auto_update_section'
		);

		// テーマのチェックリスト.
		add_settings_field(
			'FAUC_theme_checklist_field',
			__( '自動更新を除外したいテーマ', 'forced-auto-update-controller' ),
			array( $this, 'theme_checklist_field_callback' ),
			'fauc-forced-auto-update-controller',
			'FAUC_forced_auto_update_section'
		);

		/**
		 * register_setting: 2つのオプションを追加
		 */
		register_setting(
			'fauc-forced-auto-update-controller',
			$this->option_name,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		// プラグイン除外リスト.
		register_setting(
			'fauc-forced-auto-update-controller',
			$this->option_name . '_excluded_plugins',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_checklist' ),
				'default'           => array(),
			)
		);
		// テーマ除外リスト.
		register_setting(
			'fauc-forced-auto-update-controller',
			$this->option_name . '_excluded_themes',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_checklist' ),
				'default'           => array(),
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
			'指定したドメイン(本番環境)では自動アップデートを強制的に有効化し、それ以外の環境ではすべて無効化します。ただし、下記のチェックリストで除外したプラグイン・テーマは自動更新されません。',
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
	 * プラグインチェックリストの表示コールバック
	 *
	 * @return void
	 */
	public function plugin_checklist_field_callback() {
		// 現在の除外設定を取得.
		$excluded_plugins = get_option( $this->option_name . '_excluded_plugins', array() );

		// 全プラグイン一覧を取得.
		$all_plugins = get_plugins(); // [ plugin_file => array( 'Name' => 'xxx', ... ), ... ]

		if ( ! empty( $all_plugins ) ) {
			echo '<p>' . esc_html__( 'チェックを入れると「自動更新対象から外す」プラグインになります。', 'forced-auto-update-controller' ) . '</p>';
			echo '<ul>';
			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				$plugin_name = $plugin_data['Name'];
				$checked     = in_array( $plugin_file, $excluded_plugins, true ) ? 'checked' : '';
				printf(
					'<li><label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label></li>',
					esc_attr( $this->option_name . '_excluded_plugins' ),
					esc_attr( $plugin_file ),
					$checked,
					esc_html( $plugin_name )
				);
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'プラグインがインストールされていません。', 'forced-auto-update-controller' ) . '</p>';
		}
	}

	/**
	 * テーマチェックリストの表示コールバック
	 *
	 * @return void
	 */
	public function theme_checklist_field_callback() {
		// 現在の除外設定を取得.
		$excluded_themes = get_option( $this->option_name . '_excluded_themes', array() );

		// インストール済みテーマ一覧を取得.
		$all_themes = wp_get_themes(); // [ 'twentytwentytwo' => WP_Theme, ... ]

		if ( ! empty( $all_themes ) ) {
			echo '<p>' . esc_html__( 'チェックを入れると「自動更新対象から外す」テーマになります。', 'forced-auto-update-controller' ) . '</p>';
			echo '<ul>';
			foreach ( $all_themes as $theme_slug => $theme_obj ) {
				$theme_name = $theme_obj->get( 'Name' );
				$checked    = in_array( $theme_slug, $excluded_themes, true ) ? 'checked' : '';
				printf(
					'<li><label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label></li>',
					esc_attr( $this->option_name . '_excluded_themes' ),
					esc_attr( $theme_slug ),
					$checked,
					esc_html( $theme_name )
				);
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'テーマがインストールされていません。', 'forced-auto-update-controller' ) . '</p>';
		}
	}

	/**
	 * チェックリストのサニタイズコールバック
	 *
	 * @param array $input ユーザー送信値
	 * @return array
	 */
	public function sanitize_checklist( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$output = array();
		foreach ( $input as $val ) {
			$output[] = sanitize_text_field( $val );
		}
		return $output;
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
		// 本番環境かどうか
		return $this->is_production_domain();
	}

	/**
	 * (3) プラグイン自動更新フィルタ
	 *
	 * @param bool   $update    自動更新を許可するか (true=許可, false=拒否)
	 * @param object $item      プラグイン情報 ( $item->plugin = "hello-dolly/hello.php" 等)
	 * @return bool
	 */
	public function control_auto_update_plugin( $update, $item ) {
		// 「除外リスト」に含まれていれば false を返す
		$excluded_plugins = get_option( $this->option_name . '_excluded_plugins', array() );

		if ( isset( $item->plugin ) && in_array( $item->plugin, $excluded_plugins, true ) ) {
			return false; // チェック済み → 自動更新除外
		}

		// それ以外の場合は、本番なら自動更新許可、非本番なら拒否
		return $this->is_production_domain();
	}

	/**
	 * (4) テーマ自動更新フィルタ
	 *
	 * @param bool   $update (true=許可/false=拒否)
	 * @param object $item   テーマ情報 ($item->theme = 'twentytwentytwo' 等)
	 * @return bool
	 */
	public function control_auto_update_theme( $update, $item ) {
		$excluded_themes = get_option( $this->option_name . '_excluded_themes', array() );

		if ( isset( $item->theme ) && in_array( $item->theme, $excluded_themes, true ) ) {
			return false; // 除外
		}

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
}
