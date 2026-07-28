<?php
/**
 * FAUC_Auto_Update_Controller クラスファイル.
 *
 * ドメインパターンを指定し、パターンが一致したら
 *   - コア/翻訳ファイルの自動更新を強制的に有効化
 *   - プラグイン/テーマは、個別トグル（auto_update_plugins/themes）の設定を尊重して
 *     Git などのバージョン管理下でも自動更新が機能するようにする
 *   - プラグイン/テーマ一覧に自動更新トグルUI (WP5.5+) を表示
 *   - ただし、チェックが入っているプラグイン・テーマは自動更新を強制除外
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
 * メインクラス: FAUC_Auto_Update_Controller.
 */
class FAUC_Auto_Update_Controller {

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

		// 緊急停止スイッチ: 定義されていれば、以降のフック登録を一切行わない.
		if ( defined( 'FAUC_DISABLE' ) && FAUC_DISABLE ) {
			return;
		}

		// 設定ページの追加.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

		// 設定欄・フィールドを初期化.
		add_action( 'admin_init', array( $this, 'settings_init' ) );

		// (1) バージョンコントロールのチェックを無効化.
		add_filter( 'automatic_updates_is_vcs_checkout', array( $this, 'control_vcs_check' ), 9999, 1 );

		// (2) コア自動更新: 優先度 9999 で最終上書き（引数 2 つに変更）.
		add_filter( 'auto_update_core', array( $this, 'control_auto_update_core' ), 9999, 2 );

		// (3) プラグイン自動更新: 優先度 9999 で最終上書き（チェックしたプラグインは除外）.
		add_filter( 'auto_update_plugin', array( $this, 'control_auto_update_plugin' ), 9999, 2 );

		// (4) テーマ自動更新: 優先度 9999 で最終上書き（チェックしたテーマは除外）.
		add_filter( 'auto_update_theme', array( $this, 'control_auto_update_theme' ), 9999, 2 );

		// (5) 翻訳ファイル自動更新: 優先度 9999 で最終上書き.
		add_filter( 'auto_update_translation', array( $this, 'control_auto_update_translation' ), 9999, 1 );

		// (6) プラグイン一覧の自動更新 UI（WP5.5+）: 優先度 9999 で最終上書き.
		add_filter( 'plugins_auto_update_enabled', array( $this, 'control_auto_update_ui_for_plugins' ), 9999, 1 );

		// (7) テーマ一覧の自動更新 UI（WP5.5+）: 優先度 9999 で最終上書き.
		add_filter( 'themes_auto_update_enabled', array( $this, 'control_auto_update_ui_for_themes' ), 9999, 1 );

		// (8) 管理者のみダッシュボードにメタボックス追加.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_meta_box_warning' ) );

		// (8-1) ドメインパターン未設定・不一致が続いている場合、管理画面に常時警告を表示.
		add_action( 'admin_notices', array( $this, 'render_unconfigured_or_mismatch_notice' ) );

		/**
		 * (9) WordPress本体のアップデート通知を非表示にする
		 *     - 「Update 通知設定」でチェックが入っている場合にのみ実行する
		 *     - 更新バッジやダッシュボード上部のバナーを制御するフィルター: wp_get_update_data
		 */
		add_filter( 'wp_get_update_data', array( $this, 'hide_wordpress_update_notifications' ), 9999 );

		/**
		 *  (9-1) コアアップデートがある際に管理画面上部に表示されるバナー "WordPress x.x.x が利用可能です" を削除
		 */
		add_action( 'admin_head', array( $this, 'remove_update_nag_for_core' ), 9999 );

		/**
		 * (10) マイナーアップデートのトグル表示を制御.
		 *
		 * WordPress は has_filter('allow_minor_auto_core_updates') をチェックしないため、
		 * このフィルタを登録してもトグルUIは非表示にならない.
		 */
		add_filter( 'allow_minor_auto_core_updates', array( $this, 'allow_minor_auto_core_updates' ), 9999, 1 );

		/**
		 * (11) auto_update_core_minor オプション値をドメイン合致時に 'enabled' に強制する.
		 *
		 * WordPress の更新ページは get_site_option('auto_update_core_minor') でオプション値を
		 * 直接読み取るため、pre_site_option / pre_option フィルタでインターセプトする.
		 */
		add_filter( 'pre_site_option_auto_update_core_minor', array( $this, 'force_core_minor_option' ), 9999 );
		add_filter( 'pre_option_auto_update_core_minor', array( $this, 'force_core_minor_option' ), 9999 );

		/**
		 * (12) Site Health に「更新通知抑止とコア自動更新の整合性」テストを追加.
		 *
		 * 「WordPress本体の更新通知を非表示にする」がONなのにコア自動更新が実際には
		 * 機能していない状態（放置状態）を critical として検出する.
		 */
		add_filter( 'site_status_tests', array( $this, 'register_site_health_test' ) );
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

		// 非本番環境でもコアのマイナー/セキュリティ自動更新を許可するかどうか.
		add_settings_field(
			'FAUC_allow_core_minor_everywhere_field',
			__( '非本番環境でもコアのマイナー/セキュリティ自動更新を許可する', 'forced-auto-update-controller' ),
			array( $this, 'allow_core_minor_everywhere_field_callback' ),
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
				'sanitize_callback' => array( $this, 'sanitize_plugin_checklist' ),
				'default'           => array(),
			)
		);

		// テーマ除外リスト.
		register_setting(
			'fauc-forced-auto-update-controller',
			$this->option_name . '_excluded_themes',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_theme_checklist' ),
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

		// 非本番環境でもコアのマイナー/セキュリティ自動更新を許可する（デフォルト ON）.
		register_setting(
			'fauc-forced-auto-update-controller',
			$this->option_name . '_allow_core_minor_everywhere',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
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
			'指定したドメインに合致した場合、Git などのバージョン管理下でも各プラグイン・テーマの個別の自動更新設定が有効に機能するようになります。下記のチェックリストで除外したプラグイン・テーマは、個別設定の状態にかかわらず自動更新されません。',
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
			'WordPress本体のみ、更新通知（バナーやボタン、更新ページの文言など）を非表示にできます。プラグイン・テーマの更新通知はそのまま表示されます。',
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
		$overridden_by_constant = $this->is_domain_overridden_by_constant();
		$value                  = $overridden_by_constant ? FAUC_PRODUCTION_DOMAIN : get_option( $this->option_name );

		echo '<p>' . esc_html__( 'ここに有効化したいサイトのドメインを入力します。サブディレクトリで公開している場合はサブディレクトリも含めてください。「https://」や最後の「/」は不要です。', 'forced-auto-update-controller' ) . '</p>';

		if ( $overridden_by_constant ) {
			printf(
				'<input type="text" value="%1$s" class="regular-text" readonly disabled />',
				esc_attr( $value )
			);
			echo '<p class="description">' . esc_html__( 'FAUC_PRODUCTION_DOMAIN 定数で固定されているため、この設定は無効です（定数の値が優先されます）。', 'forced-auto-update-controller' ) . '</p>';
		} else {
			printf(
				'<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="%3$s" />',
				esc_attr( $this->option_name ),
				esc_attr( $value ),
				esc_attr__( '例: example.com、example.com/sample など', 'forced-auto-update-controller' )
			);
		}

		// 診断情報を表示. home_url() はフィルタの影響を受けるため、DB上の生値である
		// get_option('home') を比較対象にする（is_production_domain() と同じ基準）.
		$url_parts = wp_parse_url( (string) get_option( 'home' ) );
		$detected  = '';
		if ( ! empty( $url_parts['host'] ) ) {
			$detected = $url_parts['host'];
			if ( ! empty( $url_parts['port'] ) ) {
				$detected .= ':' . $url_parts['port'];
			}
			$path = isset( $url_parts['path'] ) ? trim( $url_parts['path'], '/' ) : '';
			if ( '' !== $path ) {
				$detected .= '/' . $path;
			}
		}

		echo '<div style="margin-top:8px;padding:8px 12px;background:#f0f0f1;border-left:4px solid #2271b1;">';
		printf(
			'<p style="margin:0 0 4px;"><strong>%s</strong> <code>%s</code></p>',
			esc_html__( '検出されたサイトドメイン:', 'forced-auto-update-controller' ),
			esc_html( $detected )
		);

		if ( ! empty( $value ) && '' !== $detected ) {
			if ( strtolower( $detected ) === strtolower( $value ) ) {
				printf(
					'<p style="margin:0;color:#00a32a;">&#10003; %s</p>',
					esc_html__( '保存済みパターンと一致しています。プラグインの自動更新制御は有効です。', 'forced-auto-update-controller' )
				);
			} else {
				printf(
					'<p style="margin:0;color:#d63638;">&#10007; %s</p>',
					esc_html__( '保存済みパターンと一致しません。自動更新制御は無効の状態です。', 'forced-auto-update-controller' )
				);
				printf(
					'<p style="margin:4px 0 0;color:#50575e;font-size:12px;">%s <code>%s</code> / %s <code>%s</code></p>',
					esc_html__( '保存値:', 'forced-auto-update-controller' ),
					esc_html( $value ),
					esc_html__( '検出値:', 'forced-auto-update-controller' ),
					esc_html( strtolower( $detected ) )
				);
			}
		} elseif ( empty( $value ) ) {
			printf(
				'<p style="margin:0;color:#dba617;">&#9888; %s</p>',
				esc_html__( 'ドメインパターンが未設定です。上記の検出値を参考に入力してください。', 'forced-auto-update-controller' )
			);
		}
		echo '</div>';
	}

	/**
	 * プラグインチェックリストの表示コールバック
	 *
	 * @return void
	 */
	public function plugin_checklist_field_callback() {
		// 現在の除外設定を取得.
		$excluded_plugins = get_option( $this->option_name . '_excluded_plugins', array() );

		// インストール済みプラグインの一覧を取得する.
		$all_plugins = get_plugins();

		if ( ! empty( $all_plugins ) ) {
			echo '<p>' . esc_html__( 'チェックを入れると「自動更新の対象から外す」プラグインになります。', 'forced-auto-update-controller' ) . '</p>';
			echo '<ul>';
			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				$plugin_name = $plugin_data['Name'];
				$is_checked  = in_array( $plugin_file, $excluded_plugins, true );

				printf(
					'<li><label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label></li>',
					esc_attr( $this->option_name . '_excluded_plugins' ),
					esc_attr( $plugin_file ),
					checked( $is_checked, true, false ),
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

		// インストール済みテーマ一覧を取得する.
		$all_themes = wp_get_themes();

		if ( ! empty( $all_themes ) ) {
			echo '<p>' . esc_html__( 'チェックを入れると「自動更新の対象から外す」テーマになります。', 'forced-auto-update-controller' ) . '</p>';
			echo '<ul>';
			foreach ( $all_themes as $theme_slug => $theme_obj ) {
				$theme_name = $theme_obj->get( 'Name' );
				$is_checked = in_array( $theme_slug, $excluded_themes, true );
				printf(
					'<li><label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label></li>',
					esc_attr( $this->option_name . '_excluded_themes' ),
					esc_attr( $theme_slug ),
					checked( $is_checked, true, false ),
					esc_html( $theme_name )
				);
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'テーマがインストールされていません。', 'forced-auto-update-controller' ) . '</p>';
		}
	}

	/**
	 * 「非本番環境でもコアのマイナー/セキュリティ自動更新を許可する」チェックボックスのHTMLを出力.
	 *
	 * @return void
	 */
	public function allow_core_minor_everywhere_field_callback() {
		$option = get_option( $this->option_name . '_allow_core_minor_everywhere', true );

		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->option_name . '_allow_core_minor_everywhere' ),
			checked( $option, true, false ),
			esc_html__( 'ドメインパターンに一致しない環境でも、コアのマイナー/セキュリティ自動更新のみは許可します（推奨）。', 'forced-auto-update-controller' )
		);
	}

	/**
	 * WordPress本体のアップデート通知を非表示にするチェックボックスのHTMLを出力
	 *
	 * @return void
	 */
	public function hide_wordpress_updates_field_callback() {
		// 現在の値を取得（true/false）.
		$option = get_option( $this->option_name . '_hide_wp_updates', false );

		// チェックボックスを表示.
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->option_name . '_hide_wp_updates' ),
			checked( $option, true, false ),
			esc_html__( 'チェックを入れると WordPress の更新通知が非表示になります。', 'forced-auto-update-controller' )
		);
	}

	/**
	 * ドメインパターンのサニタイズおよびバリデーションコールバック.
	 *
	 * @param string $input ユーザー入力値です.
	 * @return string サニタイズおよびバリデーション後の値です.
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

		// ドメイン（ポート番号可）＋任意の深さのパスを検証.
		if ( ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}(:[0-9]+)?(\/[a-z0-9_.~-]+)*$/i', $pattern ) ) {
			add_settings_error(
				'fauc-forced-auto-update-controller-notices',
				'FAUC_invalid_domain_pattern_format',
				__( 'ドメインパターンの形式が正しくありません。例: example.com、example.com/sample など', 'forced-auto-update-controller' ),
				'error'
			);
			return '';
		}

		return strtolower( $pattern );
	}

	/**
	 * プラグイン除外チェックリストのサニタイズコールバック.
	 * インストール済みプラグインのファイルパスとの厳密照合でホワイトリスト化する.
	 *
	 * @param array $input ユーザー送信値です.
	 * @return array サニタイズ後の値です.
	 */
	public function sanitize_plugin_checklist( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = array_keys( get_plugins() );

		$output = array();
		foreach ( $input as $val ) {
			if ( is_string( $val ) && in_array( $val, $installed_plugins, true ) ) {
				$output[] = $val;
			}
		}
		return array_values( array_unique( $output ) );
	}

	/**
	 * テーマ除外チェックリストのサニタイズコールバック.
	 * インストール済みテーマのスラッグとの厳密照合でホワイトリスト化する.
	 *
	 * @param array $input ユーザー送信値です.
	 * @return array サニタイズ後の値です.
	 */
	public function sanitize_theme_checklist( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$installed_themes = array_keys( wp_get_themes() );

		$output = array();
		foreach ( $input as $val ) {
			if ( is_string( $val ) && in_array( $val, $installed_themes, true ) ) {
				$output[] = $val;
			}
		}
		return array_values( array_unique( $output ) );
	}

	/**
	 * FAUC_PRODUCTION_DOMAIN 定数でドメインパターンが上書き固定されているかどうか.
	 *
	 * 定数が定義されている環境では設定 UI 側の値は無視され、コード側の値が優先される.
	 *
	 * @return bool
	 */
	private function is_domain_overridden_by_constant() {
		return defined( 'FAUC_PRODUCTION_DOMAIN' ) && '' !== trim( (string) FAUC_PRODUCTION_DOMAIN );
	}

	/**
	 * 実際に判定に使うドメインパターンを取得する.
	 *
	 * FAUC_PRODUCTION_DOMAIN 定数が定義されていればそちらを優先し、
	 * なければ DB に保存された設定値を使う.
	 *
	 * @return string
	 */
	private function get_domain_pattern() {
		if ( $this->is_domain_overridden_by_constant() ) {
			return (string) FAUC_PRODUCTION_DOMAIN;
		}

		return (string) get_option( $this->option_name, '' );
	}

	/**
	 * 現在の環境がパターンに一致するか（本番環境か）どうかを判定.
	 *
	 * @return bool true: 一致（本番） / false: 不一致（非本番）です.
	 */
	private function is_production_domain() {
		$pattern = $this->get_domain_pattern();

		if ( empty( $pattern ) ) {
			return false;
		}

		$pattern = preg_replace( '#^https?://#i', '', $pattern );
		$pattern = rtrim( $pattern, '/' );

		if ( empty( $pattern ) ) {
			return false;
		}

		// ドメイン（ポート番号可）＋任意の深さのパスを許容.
		if ( ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}(:[0-9]+)?(\/[a-z0-9_.~-]+)*$/i', $pattern ) ) {
			return false;
		}

		// wp_get_environment_type()（WP 5.5+）を補助的な条件として利用する.
		// WP_ENVIRONMENT_TYPE 定数/環境変数で明示的に production 以外（staging 等）に
		// 設定されている環境では、ドメインパターンが一致していても強制しない.
		if ( function_exists( 'wp_get_environment_type' ) && 'production' !== wp_get_environment_type() ) {
			return false;
		}

		// home_url() はフィルタの影響を受け、動的な WP_HOME 定義下では信頼できないため、
		// DB上の生値である get_option('home') を比較対象にする.
		$url_parts = wp_parse_url( (string) get_option( 'home' ) );

		if ( empty( $url_parts ) ) {
			return false;
		}

		$host = isset( $url_parts['host'] ) ? $url_parts['host'] : '';

		if ( empty( $host ) ) {
			return false;
		}

		// ポート番号があれば付与.
		if ( ! empty( $url_parts['port'] ) ) {
			$host .= ':' . $url_parts['port'];
		}

		$path = isset( $url_parts['path'] ) ? trim( $url_parts['path'], '/' ) : '';

		$host_with_path = $host;
		if ( '' !== $path ) {
			$host_with_path .= '/' . $path;
		}

		// 小文字に正規化して比較.
		$result = ( strtolower( $host_with_path ) === strtolower( $pattern ) );

		/**
		 * 本番ドメイン判定の最終結果をフィルタする.
		 *
		 * 緊急停止など、コードレベルで最終的に判定結果を上書きしたい場合に利用する.
		 *
		 * @param bool   $result  判定結果です.
		 * @param string $pattern 判定に使用したドメインパターンです.
		 */
		return (bool) apply_filters( 'fauc_is_production_domain', $result, $pattern );
	}

	/**
	 * ドメインパターンが一度でも設定されているかどうかを判定.
	 *
	 * 未設定の場合、本プラグインは一切のフィルタに介入せず WordPress の
	 * デフォルト挙動（$update をそのまま返す）に委ねる。設定前に有効化しただけで
	 * コアのマイナー/セキュリティ自動更新まで強制停止してしまうフェイルセーフ不備を防ぐ.
	 *
	 * @return bool 設定済みなら true.
	 */
	private function is_configured() {
		return '' !== trim( $this->get_domain_pattern() );
	}

	/**
	 * 非本番（ドメインパターン不一致）環境でも、コアのマイナー/セキュリティ自動更新を
	 * 許可するかどうかの設定値を取得する（デフォルト ON）.
	 *
	 * 非本番環境で本来問題になり得るのは Git 管理ファイルの書き換えであり、
	 * それは automatic_updates_is_vcs_checkout（M-4）が担保する領域. セキュリティ
	 * パッチまで一律に止める必要はない.
	 *
	 * @return bool
	 */
	private function allow_core_minor_updates_everywhere() {
		return (bool) get_option( $this->option_name . '_allow_core_minor_everywhere', true );
	}

	/**
	 * (1) Git などのバージョン管理下でも自動更新を許可するかどうか制御.
	 *
	 * @param bool $checkout true: バージョン管理下, false: 非管理です.
	 * @return bool 自動更新の可否です.
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
	 * (2) コア自動更新フィルタ.
	 *
	 * Auto_update_core フィルタは WP_Automatic_Updater::should_update() (バックグラウンド
	 * 実行時) でのみ呼ばれる。core_auto_updates_settings() (更新画面の UI) はこのフィルタを
	 * 使用しない。UI 側の制御は automatic_updates_is_vcs_checkout / allow_minor_auto_core_updates
	 * / pre_site_option_auto_update_core_minor 等の別フックで行う。
	 *
	 * @param bool|null $update コア自動更新許可フラグです.
	 * @param object    $item   アップデート情報オブジェクトです.
	 * @return bool メジャーアップデート許可状況に応じた結果です.
	 */
	public function control_auto_update_core( $update, $item ) {
		// ドメインパターン未設定時は介入しない（WordPress のデフォルト挙動に委ねる）.
		if ( ! $this->is_configured() ) {
			return $update;
		}

		if ( $this->is_production_domain() ) {
			if ( $this->is_core_major_update( $item ) ) {
				return $this->is_major_core_auto_update_enabled();
			}

			return true;
		}

		// 非本番環境でも、マイナー/セキュリティ更新は設定で許可できる（デフォルト ON）.
		if ( ! $this->is_core_major_update( $item ) && $this->allow_core_minor_updates_everywhere() ) {
			return $update;
		}

		return false;
	}

	/**
	 * コアアップデートがメジャーアップデートかどうかを判定する.
	 *
	 * 現在の WordPress バージョンと更新対象バージョンを比較し、
	 * X.Y の部分が変わる場合はメジャーアップデートと判定する.
	 *
	 * @param object $item アップデート情報オブジェクトです.
	 * @return bool メジャーアップデートの場合は true、マイナーアップデートの場合は false.
	 */
	private function is_core_major_update( $item ) {
		global $wp_version;

		// 更新対象バージョンを取得.
		$new_version = isset( $item->version ) ? $item->version : '';

		// バージョン情報が取得できない場合は安全のためメジャーとして扱う.
		if ( empty( $new_version ) || empty( $wp_version ) ) {
			return true;
		}

		// 現在のバージョンと新しいバージョンのメジャー・マイナー部分 (X.Y) を比較.
		$current_parts = explode( '.', $wp_version );
		$new_parts     = explode( '.', $new_version );

		// X.Y 部分を取得（最低2要素必要）.
		if ( count( $current_parts ) < 2 || count( $new_parts ) < 2 ) {
			// バージョン形式が不正な場合は安全のためメジャーとして扱う.
			return true;
		}

		// X.Y の部分を比較（メジャーバージョン + マイナーバージョン）.
		$current_major_minor = $current_parts[0] . '.' . $current_parts[1];
		$new_major_minor     = $new_parts[0] . '.' . $new_parts[1];

		// X.Y が異なればメジャーアップデート、同じならマイナーアップデート.
		return $current_major_minor !== $new_major_minor;
	}

	/**
	 * WordPress が記憶しているメジャーアップデート設定を真偽値に変換して取得.
	 *
	 * 管理画面のトグルリンクは 'enabled' / 'disabled' などの文字列で保存されるため、
	 * ここで正しく解釈してフィルターの戻り値に利用する.
	 *
	 * @return bool メジャーアップデートを許可する場合は true.
	 */
	private function is_major_core_auto_update_enabled() {
		// ネットワークオプションを優先的に確認（マルチサイト対応）.
		$option = get_site_option( 'auto_update_core_major', null );

		if ( null === $option ) {
			$option = get_option( 'auto_update_core_major', null );
		}

		// 文字列の場合は WordPress が利用する値に合わせて判定する.
		if ( is_string( $option ) ) {
			$normalized = strtolower( trim( $option ) );

			if ( in_array( $normalized, array( 'enabled', 'enable', 'on', 'true', '1' ), true ) ) {
				return true;
			}

			if ( in_array( $normalized, array( 'disabled', 'disable', 'off', 'false', '0' ), true ) ) {
				return false;
			}
		}

		// 真偽値・数値の場合はそのまま評価する.
		if ( is_bool( $option ) ) {
			return $option;
		}

		if ( is_numeric( $option ) ) {
			return (bool) (int) $option;
		}

		// 値が存在しない場合は WordPress のデフォルトに合わせて false を返す.
		return false;
	}

	/**
	 * (3) プラグイン自動更新フィルタ.
	 *
	 * @param bool   $update 自動更新を許可するか (true=許可, false=拒否) です.
	 * @param object $item   プラグイン情報 ( $item->plugin = "hello-dolly/hello.php" 等) です.
	 * @return bool ドメイン判定による自動更新可否です.
	 */
	public function control_auto_update_plugin( $update, $item ) {
		// ドメインパターン未設定時は介入しない（WordPress のデフォルト挙動に委ねる）.
		if ( ! $this->is_configured() ) {
			return $update;
		}

		// 「除外リスト」に含まれていれば強制的に自動更新から除外する.
		$excluded_plugins = get_option( $this->option_name . '_excluded_plugins', array() );

		if ( isset( $item->plugin ) && in_array( $item->plugin, $excluded_plugins, true ) ) {
			return false; // チェック済み → 自動更新除外.
		}

		if ( ! $this->is_production_domain() ) {
			return false;
		}

		// 除外リストに含まれないプラグインは、プラグイン一覧の個別トグル
		// （auto_update_plugins オプション）に基づく $update をそのまま尊重する。
		// 一律 true にすると、個別に無効化したはずが実際には更新され続ける
		// 誤った安心（false sense of security）を生むため.
		return $update;
	}

	/**
	 * (4) テーマ自動更新フィルタ.
	 *
	 * @param bool   $update (true=許可, false=拒否) です.
	 * @param object $item   テーマ情報 ($item->theme = 'twentytwentytwo' 等) です.
	 * @return bool ドメイン判定による自動更新可否です.
	 */
	public function control_auto_update_theme( $update, $item ) {
		// ドメインパターン未設定時は介入しない（WordPress のデフォルト挙動に委ねる）.
		if ( ! $this->is_configured() ) {
			return $update;
		}

		$excluded_themes = get_option( $this->option_name . '_excluded_themes', array() );

		if ( isset( $item->theme ) && in_array( $item->theme, $excluded_themes, true ) ) {
			return false; // 除外.
		}

		if ( ! $this->is_production_domain() ) {
			return false;
		}

		// 除外リストに含まれないテーマは、テーマ一覧の個別トグル
		// （auto_update_themes オプション）に基づく $update をそのまま尊重する.
		return $update;
	}

	/**
	 * (5) 翻訳ファイル自動更新フィルタ.
	 *
	 * @param bool $update 自動更新許可フラグです.
	 * @return bool ドメイン判定による自動更新可否です.
	 */
	public function control_auto_update_translation( $update ) {
		// ドメインパターン未設定時は介入しない（WordPress のデフォルト挙動に委ねる）.
		if ( ! $this->is_configured() ) {
			return $update;
		}

		return $this->is_production_domain();
	}

	/**
	 * (6) プラグイン一覧の自動更新UI表示フィルタ.
	 *
	 * @param bool $enabled true: 表示, false: 非表示です.
	 * @return bool UI 表示可否です.
	 */
	public function control_auto_update_ui_for_plugins( $enabled ) {
		unset( $enabled );

		return $this->is_production_domain();
	}

	/**
	 * (7) テーマ一覧の自動更新UI表示フィルタ.
	 *
	 * @param bool $enabled true: 表示, false: 非表示です.
	 * @return bool UI 表示可否です.
	 */
	public function control_auto_update_ui_for_themes( $enabled ) {
		unset( $enabled );

		return $this->is_production_domain();
	}

	/**
	 * ドメインパターンが未設定、または保存済みパターンが現在のサイトと一致していない状態が
	 * 続いている場合に、管理画面へ常時警告を表示する（dismissible にしない）.
	 *
	 * 自動更新の強制制御が働いていないことに管理者が気づけないまま放置される事態を防ぐ.
	 *
	 * @return void
	 */
	public function render_unconfigured_or_mismatch_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->is_configured() && $this->is_production_domain() ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=fauc-forced-auto-update-controller' );

		echo '<div class="notice notice-warning">';
		if ( ! $this->is_configured() ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'Forced Auto Update Controller: ドメインパターンが未設定です。設定するまで自動更新の強制制御は行われません。', 'forced-auto-update-controller' ),
				esc_url( $settings_url ),
				esc_html__( '設定画面へ', 'forced-auto-update-controller' )
			);
		} else {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'Forced Auto Update Controller: 保存済みのドメインパターンが現在のサイトドメインと一致していません。自動更新の強制制御は無効の状態です。', 'forced-auto-update-controller' ),
				esc_url( $settings_url ),
				esc_html__( '設定画面へ', 'forced-auto-update-controller' )
			);
		}
		echo '</div>';
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

		// 通知抑止設定の有無にかかわらず、現状を常時可視化する.
		$this->render_core_update_status();

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

		// 通知抑止設定の有無にかかわらず、現状を常時可視化する.
		$this->render_core_update_status();

		// ラップ用の div を閉じる.
		echo '</div>';
	}

	/**
	 * (9) WordPress本体のアップデート通知（バッジなど）を非表示にする処理.
	 *
	 * @param array $update_data WP の更新情報（連想配列）です.
	 * @return array 加工後の更新情報です.
	 */
	public function hide_wordpress_update_notifications( $update_data ) {
		// 「Update 通知設定」でチェックが入っているかどうか.
		if ( $this->should_hide_wp_update_notifications() ) {
			// WordPress本体の更新数を取得（通常 0 or 1 だが念のため変数へ）.
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
	 * (9-1) コアアップデートがある際に管理画面上部に表示されるバナー "WordPress x.x.x が利用可能です" を削除
	 * （update_nag は WordPress本体更新用の通知に限るので、プラグイン更新には影響しない）
	 *
	 * @return void
	 */
	public function remove_update_nag_for_core() {
		if ( $this->should_hide_wp_update_notifications() ) {
			// update_nag フックを削除する → WPコア向けバナーの削除.
			remove_action( 'admin_notices', 'update_nag', 3 );
		}
	}

	/**
	 * ダッシュボードウィジェットに、現在のコアバージョン・保留中の更新の有無・
	 * このプラグインによるコア自動更新の有効状況を常時表示する.
	 *
	 * 「WordPress本体の更新通知を非表示にする」設定の ON/OFF に関わらず表示することで、
	 * 通知を消したことで更新の必要性そのものに気づけなくなる事態を防ぐ.
	 *
	 * @return void
	 */
	private function render_core_update_status() {
		global $wp_version;

		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$core_updates    = get_core_updates( array( 'dismissed' => false ) );
		$pending_version = '';
		if ( ! empty( $core_updates ) && isset( $core_updates[0]->response, $core_updates[0]->version ) && 'upgrade' === $core_updates[0]->response ) {
			$pending_version = $core_updates[0]->version;
		}

		$core_auto_update_enabled = $this->is_production_domain();

		echo '<div class="forced-auto-update-core-status" style="margin-top:12px;padding:8px 12px;background:#f0f0f1;border-left:4px solid #2271b1;">';

		printf(
			'<p style="margin:0 0 4px;"><strong>%s</strong> %s</p>',
			esc_html__( '現在のコアバージョン:', 'forced-auto-update-controller' ),
			esc_html( $wp_version )
		);

		if ( '' !== $pending_version ) {
			printf(
				'<p style="margin:0 0 4px;color:#d63638;">%s <strong>%s</strong></p>',
				esc_html__( '保留中の更新があります:', 'forced-auto-update-controller' ),
				esc_html( $pending_version )
			);
		} else {
			echo '<p style="margin:0 0 4px;color:#00a32a;">' . esc_html__( '保留中の更新はありません。', 'forced-auto-update-controller' ) . '</p>';
		}

		printf(
			'<p style="margin:0;">%s <strong>%s</strong></p>',
			esc_html__( 'このプラグインによるコア自動更新:', 'forced-auto-update-controller' ),
			$core_auto_update_enabled
				? esc_html__( '有効です。', 'forced-auto-update-controller' )
				: esc_html__( '無効です（ドメインパターン未一致）。', 'forced-auto-update-controller' )
		);

		echo '</div>';
	}

	/**
	 * WP本体の更新通知を非表示にする設定かどうか
	 *
	 * @return bool
	 */
	private function should_hide_wp_update_notifications() {
		// オプションが true (1) なら隠す設定.
		if ( ! (bool) get_option( $this->option_name . '_hide_wp_updates', false ) ) {
			return false;
		}

		// 自動更新が実際に機能している（ドメインパターンに合致する）環境でのみ通知を抑止する.
		// 合致しない環境で通知まで消すと、更新もされず気づきようもない「放置状態」を生む.
		return $this->is_production_domain();
	}

	/**
	 * (10) マイナーアップデートのトグル表示を許可するかどうか制御.
	 *
	 * @param bool $value デフォルトの値です.
	 * @return bool マイナーアップデート許可状況です.
	 */
	public function allow_minor_auto_core_updates( $value ) {
		if ( $this->is_production_domain() ) {
			return true;
		}
		return $value;
	}

	/**
	 * (11) auto_update_core_minor オプションをドメイン合致時に 'enabled' に強制する.
	 *
	 * Pre_site_option / pre_option フィルタのコールバック.
	 * 非 false の値を返すと get_site_option() / get_option() が DB 検索をスキップし、
	 * この戻り値をそのまま使用する.
	 *
	 * @param mixed $value pre_option / pre_site_option から渡される値（デフォルトは false）.
	 * @return mixed ドメイン合致時は 'enabled'、それ以外は元の $value.
	 */
	public function force_core_minor_option( $value ) {
		if ( $this->is_production_domain() ) {
			return 'enabled';
		}
		return $value;
	}

	/**
	 * (12) Site Health の「直接実行」テスト一覧に自プラグインのテストを登録する.
	 *
	 * @param array $tests Site Health のテスト一覧です.
	 * @return array テスト追加後の一覧です.
	 */
	public function register_site_health_test( $tests ) {
		$tests['direct']['fauc_hidden_update_notifications'] = array(
			'label' => __( 'Forced Auto Update Controller: 更新通知抑止の整合性', 'forced-auto-update-controller' ),
			'test'  => array( $this, 'run_site_health_test' ),
		);

		return $tests;
	}

	/**
	 * 「WordPress本体の更新通知を非表示にする」がONなのに、コアの自動更新が
	 * 実際には機能していない（ドメインパターン未設定 or 不一致）状態を検出する.
	 *
	 * このプラグインが更新通知を消した結果、更新の必要性に誰も気づけなくなる
	 * 「放置状態」を Site Health の critical として可視化する.
	 *
	 * @return array Site Health テスト結果です.
	 */
	public function run_site_health_test() {
		$result = array(
			'label'       => __( '更新通知の抑止設定は自動更新の状態と整合しています', 'forced-auto-update-controller' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'セキュリティ', 'forced-auto-update-controller' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'Forced Auto Update Controller の「WordPress本体の更新通知を非表示にする」設定は、コア自動更新が実際に有効な環境でのみ機能しています。', 'forced-auto-update-controller' )
			),
			'actions'     => '',
			'test'        => 'fauc_hidden_update_notifications',
		);

		$hide_wp_updates_enabled = (bool) get_option( $this->option_name . '_hide_wp_updates', false );
		$core_auto_update_active = $this->is_configured() && $this->is_production_domain();

		if ( $hide_wp_updates_enabled && ! $core_auto_update_active ) {
			$result['status'] = 'critical';
			$result['label']  = __( 'WordPress本体の更新通知が非表示なのに、コア自動更新が機能していません', 'forced-auto-update-controller' );

			$result['description'] = sprintf(
				'<p>%s</p>',
				esc_html__( 'Forced Auto Update Controller の「WordPress本体の更新通知を非表示にする」がONですが、ドメインパターンが未設定または現在のサイトと一致していないため、コアの自動更新は機能していません。更新の必要性に誰も気づけない状態になっている可能性があります。', 'forced-auto-update-controller' )
			);

			$result['actions'] = sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'options-general.php?page=fauc-forced-auto-update-controller' ) ),
				esc_html__( '設定画面を開く', 'forced-auto-update-controller' )
			);
		}

		return $result;
	}
}

// インスタンスを生成.
new FAUC_Auto_Update_Controller();
