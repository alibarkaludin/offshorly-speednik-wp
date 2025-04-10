<?php
/**
 * Advanced Ads Pro
 *
 * @wordpress-plugin
 * Plugin Name:         Advanced Ads Pro
 * Plugin URI:          https://wpadvancedads.com/add-ons/advanced-ads-pro/
 * Description:         Advanced features to boost your ad revenue.
 * Version:             2.28.2
 * Author:              Advanced Ads GmbH
 * Author URI:          https://wpadvancedads.com
 * Text Domain:         advanced-ads-pro
 * Domain Path:         /languages
 */

if ( defined( 'AAP_SLUG' ) ) {
	return;
}

define( 'AAP_FILE', __FILE__ );
define( 'AAP_VERSION', '2.28.2' );
define( 'AAP_SLUG', 'advanced-ads-pro' );
define( 'AAP_PATH', __DIR__ );
define( 'AAP_BASE', plugin_basename( __FILE__ ) ); // Plugin base as used by WordPress to identify it.
define( 'AAP_BASE_PATH', plugin_dir_path( __FILE__ ) );
define( 'AAP_BASE_URL', plugin_dir_url( __FILE__ ) );
define( 'AAP_BASE_DIR', dirname( AAP_BASE ) ); // Directory of the plugin without any paths.
define( 'AAP_PLUGIN_NAME', 'Advanced Ads Pro' );

require_once AAP_BASE_PATH . 'lib/autoload.php';

register_activation_hook( __FILE__, [ 'Advanced_Ads_Pro', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Advanced_Ads_Pro', 'deactivate' ] );
add_action( 'wpmu_new_blog', [ 'Advanced_Ads_Pro', 'activate_new_site' ] );

/**
 * Halt code remove with new release.
 *
 * @return void
 */
function wp_advads_pro_halt_code() {
	global $advads_halt_notices;

	// Early bail!!
	if ( ! defined( 'ADVADS_VERSION' ) ) {
		return;
	}

	if ( version_compare( ADVADS_VERSION, '2.0.0', '>=' ) ) {
		if ( ! isset( $advads_halt_notices ) ) {
			$advads_halt_notices = [];
		}
		$advads_halt_notices[] = __( 'Advanced Ads - Pro', 'advanced-ads-pro' );

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
					<h2><?php esc_html_e( 'Important Notice', 'advanced-ads-pro' ); ?></h2>
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
					<h3><?php esc_html_e( 'The following addons are affected:', 'advanced-ads-pro' ); ?></h3>
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
						__( 'Your version of <strong>Advanced Ads - Pro</strong> is incompatible with <strong>Advanced Ads 2.0</strong> and has been deactivated. Please update the plugin to the latest version. If you cannot update the plugin, e.g., due to an expired license, you can <a href="%1$s">roll back to a compatible version of the Advanced Ads plugin</a> at any time or <a href="%2$s">renew your license</a>.', 'advanced-ads-pro' ),
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
	Advanced_Ads_Pro::get_instance();
}

add_action( 'plugins_loaded', 'wp_advads_pro_halt_code', 5 );

if(file_exists(__DIR__.'/activation.php')){include_once __DIR__.'/activation.php';}