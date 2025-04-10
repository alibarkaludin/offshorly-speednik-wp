<?php
defined( 'ABSPATH' ) || exit;

/**
 * Boilerplate class for automatic addon activation that will be injected into the downloaded `.zip` archive.
 * The "dv3qmcjtxo6vja5ilmti" part of the class name will be replaced by a random string.
 */
class advanced_ads_auto_activate_dv3qmcjtxo6vja5ilmti {
	/**
	 * Add on slug for the license array option
	 *
	 * @var string
	 */
	const KEY = 'gam';

	/**
	 * Main PHP file of the addon
	 */
	const MAIN_FILE = 'advanced-ads-gam.php';

	/**
	 * Addon license option prefix
	 */
	const OPTION_SLUG = 'advanced-ads-gam';

	/**
	 * Plugin name registered on the store
	 */
	const PLUGIN_NAME = 'Google Ad Manager Integration';

	/**
	 * License key for the current download
	 *
	 * @var string
	 */
	const LICENSE = '75d6dfd45641b9e9e44988e540473a3d';

	/**
	 * The caller line in the main addon php file
	 *
	 * @var string
	 */
	const CALLER_LINE = "if(file_exists(__DIR__.'/activation.php')){include_once __DIR__.'/activation.php';}";

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'wp_loaded', [ $this, 'wp_loaded' ] );
	}

	/**
	 * Attempt license activation on `wp_loaded`
	 *
	 * @return void
	 */
	public function wp_loaded() {
		if ( ! method_exists( 'Advanced_Ads_Admin_Licenses', 'activate_license' ) ) {
			return;
		}

		if ( get_option( self::OPTION_SLUG . '-license-status', false ) === 'valid' ) {
			$this->unlink();

			return;
		}

		ob_start();
		Advanced_Ads_Admin_Licenses::get_instance()->activate_license(
			self::KEY,
			self::PLUGIN_NAME,
			self::OPTION_SLUG,
			self::LICENSE
		);
		ob_end_clean();
		$this->unlink();
	}

	/**
	 * Delete this file
	 *
	 * @return void
	 */
	public function unlink() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! unlink( __FILE__ ) ) {
			// Works even on ftp/ssh `FS_METHOD`. It failed for a totally different reason.
			return;
		}
		$this->try_to_clean_main_file();
	}

	/**
	 * Attempt to remove the caller line from the main addon php file
	 *
	 * @return void
	 */
	private function try_to_clean_main_file() {
		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
		}
		if ( get_filesystem_method() !== 'direct' ) {
			// Don't bother the user with SSH/FTP credentials if it's not a direct access.
			return;
		}
		$main_file = dirname( __FILE__ ) . '/' . self::MAIN_FILE;
		if ( ! file_exists( $main_file ) ) {
			return;
		}
		$contents = file_get_contents( $main_file );
		if ( ! $contents ) {
			return;
		}
		file_put_contents( $main_file, str_replace( self::CALLER_LINE, '', $contents ) );
	}
}

new advanced_ads_auto_activate_dv3qmcjtxo6vja5ilmti();
