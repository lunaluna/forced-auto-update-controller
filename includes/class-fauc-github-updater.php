<?php
/**
 * FAUC_GitHub_Updater クラスファイル.
 *
 * `Update URI: false` を維持したまま、GitHub Releases を情報源として
 * このプラグイン自身の更新を WordPress の管理画面から行えるようにする.
 *
 * - pre_set_site_transient_update_plugins: 更新トランジェントに自前のエントリを差し込む
 * - plugins_api: 「詳細を表示」モーダル用の情報を返す
 * - upgrader_process_complete: 更新完了後にキャッシュとトランジェントを掃除する
 *
 * @package ForcedAutoUpdateController
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接のアクセスを防止.
}

/**
 * GitHub Releases ベースの自己更新機構: FAUC_GitHub_Updater.
 */
class FAUC_GitHub_Updater {

	/**
	 * 更新元の GitHub リポジトリ (owner/repo).
	 *
	 * @var string
	 */
	const GITHUB_REPO = 'lunaluna/forced-auto-update-controller';

	/**
	 * プラグインスラッグ.
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'forced-auto-update-controller';

	/**
	 * リリース情報キャッシュのサイトトランジェントキー.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'FAUC_github_release_cache';

	/**
	 * リリース情報キャッシュの既定 TTL (秒). 6時間.
	 *
	 * HOUR_IN_SECONDS 等の定数演算は使わない（有効化時 fatal の実績があるため）.
	 *
	 * @var int
	 */
	const DEFAULT_CACHE_TTL = 21600;

	/**
	 * 取得失敗時のバックオフ TTL (秒). 30分.
	 *
	 * @var int
	 */
	const DEFAULT_BACKOFF_TTL = 1800;

	/**
	 * フックを登録する.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_update' ), 10, 2 );
	}

	/**
	 * 自前の更新情報を update_plugins トランジェントに差し込む.
	 *
	 * `Update URI: false` のためコアは版比較を行わないので、判定を自前で行う.
	 *
	 * @param mixed $transient update_plugins トランジェントの値.
	 * @return mixed 加工後のトランジェントの値.
	 */
	public static function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::fetch_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		// $transient->checked にはヘッダー由来の実インストール版が入っている（コア: wp-includes/update.php).
		$installed = isset( $transient->checked[ FAUC_PLUGIN_BASENAME ] )
			? $transient->checked[ FAUC_PLUGIN_BASENAME ]
			: FAUC_VERSION;

		if ( version_compare( $release['version'], $installed, '>' ) ) {
			$transient->response[ FAUC_PLUGIN_BASENAME ] = self::build_plugin_update_object( $release );
		} else {
			// 更新完了後などに残った通知を消してから no_update に登録する.
			unset( $transient->response[ FAUC_PLUGIN_BASENAME ] );
			$transient->no_update[ FAUC_PLUGIN_BASENAME ] = self::build_plugin_update_object( $release );
		}

		return $transient;
	}

	/**
	 * 「詳細を表示」モーダル用の情報を返す.
	 *
	 * @param false|object|array $result 既定の戻り値.
	 * @param string             $action 要求されたアクション.
	 * @param object             $args   plugins_api への引数.
	 * @return false|object|array 加工後の戻り値.
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::fetch_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$update_object = self::build_plugin_update_object( $release );

		$info                = new stdClass();
		$info->name          = 'Forced Auto Update Controller';
		$info->slug          = self::PLUGIN_SLUG;
		$info->version       = $release['version'];
		$info->author        = '<a href="https://profiles.wordpress.org/lunaluna_dev/">lunaluna_dev</a>';
		$info->homepage      = $update_object->url;
		$info->requires      = $update_object->requires;
		$info->requires_php  = $update_object->requires_php;
		$info->tested        = $update_object->tested;
		$info->download_link = $update_object->package;
		$info->sections      = array(
			'changelog' => '<pre style="white-space:pre-wrap">' . esc_html( $release['notes'] ) . '</pre>',
		);

		return $info;
	}

	/**
	 * プラグイン更新完了後にキャッシュを掃除する.
	 *
	 * @param WP_Upgrader $upgrader Upgrader インスタンス（未使用）.
	 * @param array       $options  更新処理の内容.
	 * @return void
	 */
	public static function after_update( $upgrader, $options ) {
		if ( ! isset( $options['action'], $options['type'] ) || 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		if ( empty( $options['plugins'] ) || ! in_array( FAUC_PLUGIN_BASENAME, $options['plugins'], true ) ) {
			return;
		}

		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * GitHub の最新リリース情報を取得する（サイトトランジェントでキャッシュ）.
	 *
	 * @return array{version:string,zip_url:string,notes:string}|null 取得できたリリース情報。取得不可なら null.
	 */
	public static function fetch_latest_release() {
		if ( ! apply_filters( 'fauc_github_updater_enabled', true ) ) {
			return null;
		}

		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return empty( $cached ) ? null : $cached;
		}

		$release = self::request_latest_release();

		if ( null === $release ) {
			$backoff_ttl = (int) apply_filters( 'fauc_github_updater_backoff_ttl', self::DEFAULT_BACKOFF_TTL );
			set_site_transient( self::CACHE_KEY, array(), $backoff_ttl );
			return null;
		}

		$cache_ttl = (int) apply_filters( 'fauc_github_updater_cache_ttl', self::DEFAULT_CACHE_TTL );
		set_site_transient( self::CACHE_KEY, $release, $cache_ttl );

		return $release;
	}

	/**
	 * GitHub API から最新リリースを取得し、正規化した配列にして返す.
	 *
	 * @return array{version:string,zip_url:string,notes:string}|null 取得・解析に成功した場合のみ配列.
	 */
	private static function request_latest_release() {
		$url = sprintf( 'https://api.github.com/repos/%s/releases/latest', self::GITHUB_REPO );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'FAUC-GitHub-Updater/' . FAUC_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		$zip_url = self::extract_zip_url( $body );
		if ( ! $zip_url ) {
			return null;
		}

		return array(
			'version' => self::normalize_version( $body['tag_name'] ),
			'zip_url' => $zip_url,
			'notes'   => isset( $body['body'] ) ? (string) $body['body'] : '',
		);
	}

	/**
	 * GitHub Release のタグ名からバージョン番号を抽出する.
	 *
	 * 先頭の "v"/"V" のみを取り除く（ltrim と異なり "vv1.0" のような文字集合の
	 * 誤った除去をしない）.
	 *
	 * @param string $tag GitHub のタグ名.
	 * @return string 正規化したバージョン文字列.
	 */
	public static function normalize_version( $tag ) {
		return preg_replace( '/^v/i', '', (string) $tag );
	}

	/**
	 * リリースのアセット一覧から配布用 ZIP の URL を名前で選ぶ.
	 *
	 * GitHub 自動生成の zipball はディレクトリ名がプラグインスラッグと一致せず
	 * プラグインディレクトリを壊すため、フォールバックはしない（見つからなければ null）.
	 *
	 * @param array $body GitHub API のレスポンスボディ（デコード済み連想配列）.
	 * @return string|null 見つかった ZIP の URL。見つからなければ null.
	 */
	public static function extract_zip_url( $body ) {
		foreach ( (array) ( isset( $body['assets'] ) ? $body['assets'] : array() ) as $asset ) {
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';

			if ( ! empty( $asset['browser_download_url'] )
				&& 0 === strpos( $name, self::PLUGIN_SLUG )
				&& '.zip' === substr( $name, -4 ) ) {
				return $asset['browser_download_url'];
			}
		}

		return null;
	}

	/**
	 * 更新情報オブジェクトを組み立てて update_plugins / no_update トランジェントに登録する.
	 *
	 * ハードコードせず、requires / requires_php / tested はプラグインヘッダーから読む.
	 *
	 * @param array{version:string,zip_url:string,notes:string} $release fetch_latest_release() が返す配列.
	 * @return stdClass 更新情報オブジェクト.
	 */
	public static function build_plugin_update_object( $release ) {
		$headers = get_file_data(
			FAUC_PLUGIN_FILE,
			array(
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
				'tested'       => 'Tested up to',
			)
		);

		$update               = new stdClass();
		$update->id           = 'github.com/' . self::GITHUB_REPO;
		$update->slug         = self::PLUGIN_SLUG;
		$update->plugin       = FAUC_PLUGIN_BASENAME;
		$update->new_version  = $release['version'];
		$update->url          = 'https://github.com/' . self::GITHUB_REPO;
		$update->package      = $release['zip_url'];
		$update->requires     = $headers['requires'];
		$update->requires_php = $headers['requires_php'];
		$update->tested       = $headers['tested'];
		$update->icons        = array();
		$update->banners      = array();

		return $update;
	}
}

FAUC_GitHub_Updater::init();
