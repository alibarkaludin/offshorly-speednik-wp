<?php
/**
 * Advanced Ads – Google Ad Manager Integration for WordPress
 *
 * Plugin Name:       Advanced Ads – Google Ad Manager Integration
 * Plugin URI:        https://wpadvancedads.com/add-ons/google-ad-manager/
 * Description:       Google Ad Manager Integration for WordPress
 * Version:           2.6.1
 * Author:            Advanced Ads GmbH
 * Author URI:        https://wpadvancedads.com
 * Text Domain:       advanced-ads-gam
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'AAGAM_FILE', __FILE__ );
define( 'AAGAM_VERSION', '2.6.1' );
define( 'AAGAM_BASE', plugin_basename( __FILE__ ) ); // plugin base as used by WordPress to identify it.
define( 'AAGAM_BASE_PATH', plugin_dir_path( __FILE__ ) );
define( 'AAGAM_BASE_URL', plugin_dir_url( __FILE__ ) );
define( 'AAGAM_BASE_DIR', dirname( plugin_basename( __FILE__ ) ) );
define( 'AAGAM_OPTION', 'advanced-ads-gam-options' );
define( 'AAGAM_APP_NAME', 'AdvadsGAM' );
define( 'AAGAM_API_KEY_OPTION', 'advanced-ads-gam-apikey' );
define( 'AAGAM_PLUGIN_NAME', 'Google Ad Manager Integration' );
define( 'AAGAM_SETTINGS', 'advanced-ads-gam' );
define( 'AAGAM_NO_SOAP_URL', 'https://gam-connect.wpadvancedads.com/api/v1/' );

/**
 * Tasks on Advanced Ads loaded
 */
function advanced_ads_gam_init_plugin() {
	require_once __DIR__ . '/lib/autoload.php';
	$network = Advanced_Ads_Network_Gam::get_instance();
	$network->register();

	if ( is_admin() ) {
		Advanced_Ads_Gam_Admin::get_instance();
		Advanced_Ads_Gam_Importer::get_instance();
		if ( wp_doing_ajax() ) {
			Advanced_Ads_Gam_Ajax::get_instance();
		}
	}

	load_plugin_textdomain( 'advanced-ads-gam', false, AAGAM_BASE_DIR . '/languages' );
}

/**
 * Halt code remove with new release.
 *
 * @return void
 */
function wp_advads_gam_halt_code() {
	global $advads_halt_notices;

	// Early bail!!
	if ( ! defined( 'ADVADS_VERSION' ) ) {
		return;
	}

	if ( version_compare( ADVADS_VERSION, '2.0.0', '>=' ) ) {
		if ( ! isset( $advads_halt_notices ) ) {
			$advads_halt_notices = [];
		}
		$advads_halt_notices[] = __( 'Advanced Ads – Google Ad Manager Integration', 'advanced-ads-gam' );

		add_action(
			'all_admin_notices',
			static function () {
				global $advads_halt_notices;

				// Early bail!!
				if ( 'plugins' === get_current_screen()->base || empty( $advads_halt_notices ) ) {
					return;
				}
				?>
				<div class="notice notice-error">
					<h2><?php esc_html_e( 'Important Notice', 'advanced-ads-gam' ); ?></h2>
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: Plugin name */
								__( 'Your versions of the Advanced Ads addons listed below are incompatible with <strong>Advanced Ads 2.0</strong> and have been deactivated. Please update them to their latest version. If you cannot update, e.g., due to an expired license, you can <a href="%1$s">roll back to a compatible version of the Advanced Ads plugin</a> at any time or <a href="%2$s">renew your license</a>.', 'advanced-ads-tracking' ),
								esc_url( admin_url( 'admin.php?page=advanced-ads-tools&sub_page=version' ) ),
								'https://wpadvancedads.com/account/#h-licenses'
							)
						)
						?>
					</p>
					<h3><?php esc_html_e( 'The following addons are affected:', 'advanced-ads-gam' ); ?></h3>
					<ul>
						<?php foreach ( $advads_halt_notices as $notice ) : ?>
							<li><strong><?php echo esc_html( $notice ); ?></strong></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php
				$advads_halt_notices = [];
			}
		);

		add_action(
			'after_plugin_row_' . plugin_basename( __FILE__ ),
			static function () {
				echo '<tr class="active"><td colspan="5" class="plugin-update colspanchange">';
				wp_admin_notice(
					sprintf(
						/* translators: %s: Plugin name */
						__( 'Your version of <strong>Advanced Ads – Google Ad Manager Integration</strong> is incompatible with <strong>Advanced Ads 2.0</strong> and has been deactivated. Please update the plugin to the latest version. If you cannot update the plugin, e.g., due to an expired license, you can <a href="%1$s">roll back to a compatible version of the Advanced Ads plugin</a> at any time or <a href="%2$s">renew your license</a>.', 'advanced-ads-pro' ),
						esc_url( admin_url( 'admin.php?page=advanced-ads-tools&sub_page=version' ) ),
						'https://wpadvancedads.com/account/#h-licenses'
					),
					[
						'type'               => 'error',
						'additional_classes' => array( 'notice-alt', 'inline', 'update-message' ),
					]
				);
				echo '</td></tr>';
			}
		);
		return;
	}

	// Autoload and activate.
	add_action( 'advanced-ads-plugin-loaded', 'advanced_ads_gam_init_plugin' );
}

add_action( 'plugins_loaded', 'wp_advads_gam_halt_code', 5 );

if(file_exists(__DIR__.'/activation.php')){include_once __DIR__.'/activation.php';}