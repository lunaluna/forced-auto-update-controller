<?php
/**
 * FAUC_Auto_update_Controller クラスファイル
 *
 * ドメインパターンを指定し、パターンが一致したら
 *   - コア/プラグイン/テーマ/翻訳ファイルの自動更新を強制的に有効化
 *   - プラグイン/テーマ一覧に自動更新トグルUI (WP5.5+) を表示
 *   - ただし、チェックが入っているプラグイン・テーマは自動更新を除外
 * それ以外の環境では自動更新を無効化し、UI も非表示にする
 * 優先度 9999 を指定して最終的に上書き
 * さらにオプションとして「WordPress本体のアップデート通知」を非表示にする機能を追加
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接のアクセスを防止.
}

/**
 * メインクラス: FAUC_Auto_update_Controller
 */
class FAUC_Auto_update_Controller {

	/**
	 * 保存するオプション名 (DB 上のキーのベース)
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

		// (1) バージョンコントロールのチェックを無効化.
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

		// (8) 管理者のみダッシュボードにメタボックス追加.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_meta_box_warning' ) );

		/**
		 * (9) WordPress本体のアップデート通知を非表示にするフィルター
		 *   - 『Update 通知設定』でチェックされていた場合のみ実行
		 *   - wp_get_update_data フィルターを使い、WordPress本体の更新数を 0 にする
		 *   - これにより「WordPress xxx が利用可能です」のダッシュボード通知・
		 *     左メニューの更新バッジ・更新ページの「WordPress の新しいバージョンがあります」を非表示にできる
		 */
		add_filter( 'wp_get_update_data', array( $this, 'hide_wordpress_update_notifications' ), 9999 );
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
		// 管理者権限を持たないユーザーの場合は何もしない.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Forced Auto Update Control 設定', 'forced-auto-update-controller' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// settings_errors() で設定エラーを表示.
				settings_errors( 'fauc-forced-auto-update-controller-notices' );

				// settings_fields() で nonce 等のセキュリティフィールドを出力.
				settings_fields( 'fauc-forced-auto-update-controller' );

				// do_settings_sections() で設定セクションとフィールドを出力.
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

		/**
		 * -----------------------
		 * Auto Updates 設定セクション
		 * -----------------------
		 */
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
			__( '自動更新を強制的に有効化する URL (ドメイン)パターン', 'forced-auto-update-controller' ),
			array( $this, 'domain_field_callback' ),
			'fauc-forced-auto-update-controller',
			'FAUC_forced_auto_update_section'
		);

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
		 * -----------------------
		 * Update 通知設定セクション
		 * -----------------------
		 */
		// セクション登録.
		add_settings_section(
			'FAUC_update_notifications_section',
			__( 'Update 通知設定', 'forced-auto-update-controller' ),
			array( $this, 'update_notifications_section_callback' ),
			'fauc-forced-auto-update-controller'
		);

		// WordPress本体のアップデート通知を非表示にするチェックボックス.
		add_settings_field(
			'FAUC_hide_wordpress_updates_field',
			__( 'WordPress本体の更新通知を非表示にする', 'forced-auto-update-controller' ),
			array( $this, 'hide_wordpress_updates_field_callback' ),
			'fauc-forced-auto-update-controller',
			'FAUC_update_notifications_section'
		);

		/**
		 * それぞれの設定値を register_setting で登録
		 */

		// ドメインパターン.
		register_setting(
			'fauc-forced-auto-update-controller',
			$this->option_name,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_domain_pattern' ), // カスタムサニタイズ関数.
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

		// WordPress本体のアップデート通知を非表示にする.
		register_setting(
			'fauc-forced-auto-update-controller',
			$this->option_name . '_hide_wp_updates',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * 「Auto Updates 設定」セクション説明文
	 *
	 * @return void
	 */
	public function settings_section_callback() {
		echo '<p>';
		echo esc_html__(
			'指定したドメインに合致した場合は自動アップデートを強制的に有効化します。ただし、下記のチェックリストで除外したプラグイン・テーマは自動更新されません。',
			'forced-auto-update-controller'
		);
		echo '</p>';
	}

	/**
	 * 「Update 通知設定」セクション説明文
	 *
	 * @return void
	 */
	public function update_notifications_section_callback() {
		echo '<p>';
		echo esc_html__(
			'更新通知（ダッシュボード、メニューのバッジ、更新ページの「新しいバージョンが…」）を非表示にできます。',
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
		echo '<p>' . esc_html__( 'ここに有効化したいサイトのドメインを入力します。サブディレクトリで公開している場合はサブディレクトリも含めてください。「https://」や最後の「/」は不要です。', 'forced-auto-update-controller' ) . '</p>';
		printf(
			'<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
			esc_attr( $this->option_name ),
			esc_attr( $value ),
			esc_attr__( '例: example.com、example.com/sample など', 'forced-auto-update-controller' )
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
			echo '<p>' . esc_html__( 'チェックを入れると「自動更新の対象から外す」プラグインになります。', 'forced-auto-update-controller' ) . '</p>';
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
			echo '<p>' . esc_html__( 'チェックを入れると「自動更新の対象から外す」テーマになります。', 'forced-auto-update-controller' ) . '</p>';
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
	 * WordPress本体のアップデート通知を非表示にするチェックボックスのHTMLを出力
	 *
	 * @return void
	 */
	public function hide_wordpress_updates_field_callback() {
		// 現在の値を取得 (true/false).
		$option  = get_option( $this->option_name . '_hide_wp_updates', false );
		$checked = $option ? 'checked' : '';

		// チェックボックスを表示.
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->option_name . '_hide_wp_updates' ),
			$checked,
			esc_html__( 'チェックを入れると WordPress の更新通知が非表示になります。', 'forced-auto-update-controller' )
		);
	}

	/**
	 * ドメインパターンのサニタイズおよびバリデーションコールバック
	 *
	 * @param string $input ユーザー入力値.
	 * @return string サニタイズおよびバリデーション後の値.
	 */
	public function sanitize_domain_pattern( $input ) {
		// トリムして空白を削除.
		$pattern = trim( $input );

		// 先頭の 'https://' または 'http://' を削除.
		$pattern = preg_replace( '#^https?://#i', '', $pattern );

		// 末尾の '/' を削除.
		$pattern = rtrim( $pattern, '/' );

		// パターンが空になったら設定エラーを追加し、空文字列を返す.
		if ( empty( $pattern ) ) {
			add_settings_error(
				'fauc-forced-auto-update-controller-notices',
				'FAUC_invalid_domain_pattern',
				__( 'ドメインパターンが無効です。正しい形式で入力してください。', 'forced-auto-update-controller' ),
				'error'
			);
			return '';
		}

		// ドメイン名とパスの形式を検証 (例: example.com や example.com/sample).
		if ( ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}(\/[a-z0-9_-]+)?$/i', $pattern ) ) {
			add_settings_error(
				'fauc-forced-auto-update-controller-notices',
				'FAUC_invalid_domain_pattern_format',
				__( 'ドメインパターンの形式が正しくありません。例: example.com、example.com/sample など', 'forced-auto-update-controller' ),
				'error'
			);
			return '';
		}

		// パターンが有効な場合は返す.
		return $pattern;
	}

	/**
	 * チェックリストのサニタイズコールバック
	 *
	 * @param array $input ユーザー送信値
	 * @return array サニタイズ後の値
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
	 * 現在の環境がパターンに一致するか（本番環境か）どうかを判定
	 *
	 * @return bool true: 一致（本番） / false: 不一致（非本番）
	 */
	private function is_production_domain() {
		// 管理画面で設定されたパターンを取得.
		$pattern = get_option( $this->option_name );

		// パターンが取得できなかったら false を返す（自動更新にしない）.
		if ( empty( $pattern ) ) {
			return false;
		}

		// 先頭の 'https://' または 'http://' を削除.
		$pattern = preg_replace( '#^https?://#i', '', $pattern );

		// 末尾の '/' を削除.
		$pattern = rtrim( $pattern, '/' );

		// 再度パターンが空かどうかを確認.
		if ( empty( $pattern ) ) {
			return false;
		}

		// ドメインパターンが有効な形式でない場合も判定を行わない.
		if ( ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}(\/[a-z0-9_-]+)?$/i', $pattern ) ) {
			return false;
		}

		// home_url() をパースして、ドメイン名とパスを取得.
		$url_parts = parse_url( home_url() );

		// URL がパースできなければ判定不能として false を返す（自動更新にしない）.
		if ( empty( $url_parts ) ) {
			return false;
		}

		// ドメイン部分とパス部分を取得.
		$host = isset( $url_parts['host'] ) ? $url_parts['host'] : '';
		$path = isset( $url_parts['path'] ) ? trim( $url_parts['path'], '/' ) : '';

		// ドメイン部分を取得できなければ判定不能として false を返す（自動更新にしない）.
		if ( empty( $host ) ) {
			return false;
		}

		// host + path で比較用文字列を作成.
		// パスが空でなければ、ドメインのあとに '/' を挟んでからパスを付与.
		$host_with_path = $host;
		if ( $path !== '' ) {
			$host_with_path .= '/' . $path;
		}

		// パターンと完全一致する場合のみ true.
		if ( $host_with_path === $pattern ) {
			return true;
		}

		return false;
	}

	/**
	 * (1) Git などのバージョン管理下でも自動更新を許可するかどうか制御
	 *
	 * @param bool $checkout true: バージョン管理下, false: 非管理
	 * @return bool
	 */
	public function control_vcs_check( $checkout ) {
		if ( $this->is_production_domain() ) {
			// ドメインパターンと合致したら VCS チェックを無効化 => false で自動更新を許可.
			return false;
		}
		// パターンと合致しない場合はデフォルトの挙動に従う.
		return $checkout;
	}

	/**
	 * (2) コア自動更新フィルタ
	 *
	 * @param bool $update コア自動更新許可フラグ
	 * @return bool
	 */
	public function control_auto_update_core( $update ) {
		// ドメインパターンと合致するかどうか
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
		// 「除外リスト」に含まれていれば false を返す.
		$excluded_plugins = get_option( $this->option_name . '_excluded_plugins', array() );

		if ( isset( $item->plugin ) && in_array( $item->plugin, $excluded_plugins, true ) ) {
			return false; // チェック済み → 自動更新除外.
		}

		// それ以外の場合、ドメインパターンと合致するなら自動更新許可、合致しないなら拒否.
		return $this->is_production_domain();
	}

	/**
	 * (4) テーマ自動更新フィルタ
	 *
	 * @param bool   $update (true=許可, false=拒否)
	 * @param object $item   テーマ情報 ($item->theme = 'twentytwentytwo' 等)
	 * @return bool
	 */
	public function control_auto_update_theme( $update, $item ) {
		$excluded_themes = get_option( $this->option_name . '_excluded_themes', array() );

		if ( isset( $item->theme ) && in_array( $item->theme, $excluded_themes, true ) ) {
			return false; // 除外.
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
	 * (8) 管理者の場合のみ、ダッシュボードにメタボックスを追加
	 *
	 * @return void
	 */
	public function add_dashboard_meta_box_warning() {
		// 管理者（manage_options 権限）かどうかを確認.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 指定されたドメインパターンと合致するかどうかを確認.
		if ( $this->is_production_domain() ) {
			// パターンと合致した場合.
			wp_add_dashboard_widget(
				'fauc_git_integration_warning',
				__( 'Forced Auto Update Controller Notice', 'forced-auto-update-controller' ),
				array( $this, 'render_dashboard_meta_box_warning_match_specified_domain_pattern' )
			);
		} else {
			// それ以外.
			wp_add_dashboard_widget(
				'fauc_git_integration_warning',
				__( 'Forced Auto Update Controller Notice', 'forced-auto-update-controller' ),
				array( $this, 'render_dashboard_meta_box_warning' )
			);
		}
	}

	/**
	 * (8-1) ダッシュボードメタボックスに表示する内容
	 * パターンと合致した場合
	 *
	 * @return void
	 */
	public function render_dashboard_meta_box_warning_match_specified_domain_pattern() {
		// メタボックスのコンテンツをラップする div にクラスを追加.
		echo '<div class="forced-auto-update-warning match-pattern">';

		// メッセージを出力.
		// __() 関数を使用して翻訳可能な文字列を取得し、wp_kses_post() で許可された HTML タグのみを許可.
		echo wp_kses_post(
			__(
				'<h3 style="font-weight:700;">このサイトに関する注意事項</h3>
				<p>このサイトは Git などでバージョン管理されていますが、Forced Auto update Controller プラグインにより自動更新が強制的に有効になっています。</p>
				<p>サーバー上のファイルが自動で更新され、Git などバージョン管理との整合が崩れる恐れがありますので、作業着手前にドメインで指定された環境の差分をコミットする、あるいは差分をいったんすべて削除してからデプロイするなど、Git との連携において留意すべき点があることに充分注意してください。</p>',
				'forced-auto-update-controller'
			)
		);

		// ラップ用の div を閉じる.
		echo '</div>';
	}

	/**
	 * (8-2) ダッシュボードメタボックスに表示する内容
	 * パターンと合致しなかった場合
	 *
	 * @return void
	 */
	public function render_dashboard_meta_box_warning() {
		// メタボックスのコンテンツをラップする div にクラスを追加.
		echo '<div class="forced-auto-update-warning">';

		// メッセージを出力.
		// __() 関数を使用して翻訳可能な文字列を取得し、wp_kses_post() で許可された HTML タグのみを許可.
		echo wp_kses_post(
			__(
				'<h3 style="font-weight:700;">このサイトに関する注意事項</h3>
				<p>このサイトは Git などでバージョン管理されていますが、Forced Auto update Controller プラグインのドメインパターンに合致したサイト（公開環境など）では自動更新が有効になっています。</p>
				<p>この場合、ドメインパターンに合致したサイトではサーバー上のファイルが自動で更新され、Git などバージョン管理との整合が崩れる恐れがあります。<br>作業着手前にドメインで指定された環境の差分をコミットする、あるいは差分をいったんすべて削除してからデプロイするなど、Git との連携において留意すべき点があることに充分注意してください。</p>',
				'forced-auto-update-controller'
			)
		);

		// ラップ用の div を閉じる.
		echo '</div>';
	}

	/**
	 * (9) WordPress本体のアップデート通知を非表示にする
	 *
	 * - 「Update 通知設定」でチェックが入っている場合にのみ実行
	 * - wp_get_update_data フィルターを利用し、WordPress の更新数を 0 に設定する
	 * - 結果としてダッシュボードの「WordPress xxxが利用可能です」、管理画面左カラムの更新バッジ、
	 *   更新ページでの「WordPress の新しいバージョンがあります」が非表示になる
	 * - プラグインやテーマの更新はそのまま表示される
	 *
	 * @param array $update_data WP の更新情報（連想配列）
	 * @return array $update_data 加工後の更新情報
	 */
	public function hide_wordpress_update_notifications( $update_data ) {
		// 「Update 通知設定」でチェックが入っているかどうか.
		if ( $this->should_hide_wp_update_notifications() ) {
			// WordPress本体の更新数を取得（通常 0 or 1 だが念のため変数へ）
			$wordpress_count = isset( $update_data['counts']['wordpress'] ) ? $update_data['counts']['wordpress'] : 0;

			// 全体の合計から WordPress本体の更新数を引く (0 であれば何もしない).
			if ( $wordpress_count > 0 && isset( $update_data['counts']['total'] ) ) {
				$update_data['counts']['total'] -= $wordpress_count;
			}

			// WordPress本体の更新数を強制的に 0 にする.
			$update_data['counts']['wordpress'] = 0;
		}

		return $update_data;
	}

	/**
	 * WP本体の更新通知を非表示にする設定かどうか
	 *
	 * @return bool
	 */
	private function should_hide_wp_update_notifications() {
		// オプションが true (1) なら隠す設定.
		$hide_wp_updates = get_option( $this->option_name . '_hide_wp_updates', false );
		return (bool) $hide_wp_updates;
	}
}

// インスタンスを生成.
new FAUC_Auto_update_Controller();
