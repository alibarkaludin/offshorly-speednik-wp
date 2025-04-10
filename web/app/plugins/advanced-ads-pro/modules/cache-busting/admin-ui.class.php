<?php

use AdvancedAds\Utilities\WordPress;
use AdvancedAds\Utilities\Conditional;

/**
 * Cache Busting admin user interface.
 */
class Advanced_Ads_Pro_Module_Cache_Busting_Admin_UI {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'advanced-ads-group-hints', [ $this, 'get_group_hints' ], 10, 2 );

		if ( empty( Advanced_Ads_Pro::get_instance()->get_options()['cache-busting']['enabled'] ) ) {
			return;
		}

		add_action( 'advanced-ads-placement-options-after', [ $this, 'admin_placement_options' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'advanced-ads-ad-params-after', [ $this, 'check_ad' ], 9, 2 );
		//add_filter( 'advanced-ads-save-options', array( $this, 'save_options' ), 10, 2 );
		add_filter( 'advanced-ads-ad-notices', [$this, 'ad_notices'], 10, 3 );
		add_action( 'wp_ajax_advads-reset-vc-cache', [ $this, 'reset_vc_cache' ] );
		add_action( 'wp_ajax_advads-placement-activate-cb', [ $this, 'ads_activate_placement_cb' ] );
	}

	/**
	 * Activate placement cache busting
	 */
	public function ads_activate_placement_cb(){
		check_ajax_referer( 'advanced-ads-admin-ajax-nonce', 'nonce' );

		$cap = 'manage_options';

		if ( method_exists( 'AdvancedAds\Utilities\WordPress', 'user_cap' ) ) {
			$cap = WordPress::user_cap( 'advanced_ads_manage_options' );
		} elseif ( method_exists( 'Advanced_Ads_Plugin', 'user_cap' ) ) {
			$cap = Advanced_Ads_Plugin::user_cap( 'advanced_ads_manage_options' );
		}

		if ( ! current_user_can( $cap ) && ! filter_has_var( INPUT_POST, 'placement' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to do this.', 'advanced-ads-pro' ), 400 );
		}
		$placement_slug = sanitize_text_field( $_POST['placement'] );
		$placements = Advanced_Ads::get_ad_placements_array();

		if ( isset( $placements[$placement_slug] ) ) {
			$placements[$placement_slug]['options']['cache-busting'] = 'auto';
			Advanced_Ads::get_instance()->get_model()->update_ad_placements_array( $placements );
			wp_send_json_success( esc_html__( 'Cache busting has been successfully enabled for the assigned placement.', 'advanced-ads-pro' ) );
		}

		wp_send_json_error( esc_html__( "Couldn't find the placement.", 'advanced-ads-pro' ), 400 );
	}

	/**
	 * Update visitor consitions cache.
	 */
	public function reset_vc_cache(){

		$cap = 'manage_options';

		if ( method_exists( 'AdvancedAds\Utilities\WordPress', 'user_cap' ) ) {
			$cap = WordPress::user_cap( 'advanced_ads_manage_options' );
		} elseif ( method_exists( 'Advanced_Ads_Plugin', 'user_cap' ) ) {
			$cap = Advanced_Ads_Plugin::user_cap( 'advanced_ads_manage_options' );
		}

		if ( ! current_user_can( $cap ) ) {
			return;
		}

		check_ajax_referer( 'advads-pro-reset-vc-cache-nonce', 'security' );
		$time = time();

		$options = get_option( 'advanced-ads-pro' );
		$options['cache-busting']['vc_cache_reset'] = $time;
		update_option( 'advanced-ads-pro', $options );
		echo $time;
		exit;
	}

	/**
	 * add placement options on placement page
	 *
	 * @param string $placement_slug
	 * @param array  $placement
	 */
	public function admin_placement_options( $placement_slug, $placement ) {
		$placement_types = Advanced_Ads_Placements::get_placement_types();
		if ( isset( $placement_types[ $placement['type'] ]['options']['placement-cache-busting'] ) && ! $placement_types[ $placement['type'] ]['options']['placement-cache-busting'] ) {
			return;
		}

		// l10n
		$values = [
			Advanced_Ads_Pro_Module_Cache_Busting::OPTION_AUTO => esc_html__( 'auto','advanced-ads-pro' ),
			Advanced_Ads_Pro_Module_Cache_Busting::OPTION_ON => esc_html__( 'AJAX','advanced-ads-pro' ),
			Advanced_Ads_Pro_Module_Cache_Busting::OPTION_OFF => esc_html__( 'off','advanced-ads-pro' ),
		];

		// options
		$value = isset( $placement['options']['cache-busting'] ) ? $placement['options']['cache-busting'] : null;
		$value = $value === Advanced_Ads_Pro_Module_Cache_Busting::OPTION_ON ? Advanced_Ads_Pro_Module_Cache_Busting::OPTION_ON : ( $value === Advanced_Ads_Pro_Module_Cache_Busting::OPTION_OFF ? Advanced_Ads_Pro_Module_Cache_Busting::OPTION_OFF : Advanced_Ads_Pro_Module_Cache_Busting::OPTION_AUTO );

		ob_start();
		foreach ( $values as $k => $l ) {
			$selected = checked( $value, $k, false );
			echo '<label><input' . $selected . ' type="radio" name="advads[placements]['.
				$placement_slug.'][options][cache-busting]" value="'.$k.'" id="advads-placement-'.
				$placement_slug.'-cache-busting-'.$k.'"/>'.$l.'</label>';
		}
		$option_content = ob_get_clean();

		if( class_exists( 'Advanced_Ads_Admin_Options' ) ){
			Advanced_Ads_Admin_Options::render_option(
				'placement-cache-busting',
				_x( 'Cache-busting', 'placement admin label', 'advanced-ads-pro' ),
				$option_content );
		}
	}

	/**
	 * enqueue scripts for validation the ad
	 */
	public function enqueue_admin_scripts() {
		$screen     = get_current_screen();
		$uriRelPath = plugin_dir_url( __FILE__ );
		$is_screen  = false;
		if ( method_exists( 'AdvancedAds\Utilities\Conditional', 'is_screen_advanced_ads' ) ) {
			$is_screen = Conditional::is_screen_advanced_ads();
		} elseif ( method_exists( 'Advanced_Ads_Admin', 'screen_belongs_to_advanced_ads' ) ) {
			$is_screen = Advanced_Ads_Admin::screen_belongs_to_advanced_ads();
		}
		if ( isset( $screen->id ) && $screen->id === 'advanced_ads' ) { //ad edit page
			wp_register_script( 'krux/prescribe', $uriRelPath . 'inc/prescribe.js', [ 'jquery' ], '1.1.3' );
			wp_enqueue_script( 'advanced-ads-pro/cache-busting-admin', $uriRelPath . 'inc/admin.js', [ 'krux/prescribe' ], AAP_VERSION );
		} elseif ( $is_screen ) {
			wp_enqueue_script( 'advanced-ads-pro/cache-busting-admin', $uriRelPath . 'inc/admin.js', [], AAP_VERSION );
		}
	}

	/**
	 * add validation for cache-busting
	 *
	 * @param obj $ad ad object
	 * @param arr $types ad types
	 */
	public function check_ad( $ad, $types = []  ) {
		$options = $ad->options();
		include dirname( __FILE__ ) . '/views/settings_check_ad.php';
	}

	// public function save_options( $options = array(), $ad = 0 ) {
	// 	if ( isset( $_POST['advanced_ad']['cache-busting']['possible'] ) ) {
	// 		$options['cache-busting']['possible'] = ('true' === $_POST['advanced_ad']['cache-busting']['possible'] ) ? true : false;
	// 	}
	// 	return $options;
	// }

	/**
	 * show cache-busting specific ad notices
	 *
	 * @since 1.13.1
	 */
	public function ad_notices( $notices, $box, $post ){

	    $ad = new Advanced_Ads_Ad( $post->ID );

	    // $content = json_decode( stripslashes( $ad->content ) );

	    switch ($box['id']){
		case 'ad-parameters-box' :
			// show hint that for ad-group ad type, cache-busting method will only be AJAX or off
			if( 'group' === $ad->type ){
			    $notices[] = [
				    'text' => __( 'The <em>Ad Group</em> ad type can only use AJAX or no cache-busting, but not passive cache-busting.', 'advanced-ads-pro' ),
				    // 'class' => 'advads-ad-notice-pro-ad-group-cache-busting',
			    ];
			}
		    break;
	    }


	    return $notices;
	}

	/**
	 * Get group hints.
	 *
	 * @param string[]           $hints Group hints (escaped strings).
	 * @param Advanced_Ads_Group $group The group object.
	 * @return string[]
	 */
	public function get_group_hints( $hints, Advanced_Ads_Group $group ) {

		// Pro is installed but cache busting is disabled.
		if ( empty( Advanced_Ads_Pro::get_instance()->get_options()['cache-busting']['enabled'] ) ) {
			$hints[] = sprintf(
				wp_kses(
					// translators: %s is an URL.
					__( 'It seems that a caching plugin is activated. Your ads might not rotate properly while cache busting is disabled. <a href="%s" target="_blank">Activate cache busting.</a>', 'advanced-ads-pro' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
						],
					]
				),
				esc_url( admin_url( 'admin.php?page=advanced-ads-settings#top#pro' ) )
			);
			return $hints;
		}

		$placements = Advanced_Ads_Placements::get_placements_by( 'group', $group->id );

		// The group doesn't use a placement.
		if (
			! $placements
			&& empty( Advanced_Ads_Pro::get_instance()->get_options()['cache-busting']['passive_all'] )
		) {
			$hints[] = sprintf(
				wp_kses(
					// translators: %s is an URL.
					__( 'You need a placement to deliver this group using cache busting. <a href="%s" target="_blank">Create a placement now.</a>', 'advanced-ads-pro' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
						],
					]
				),
				esc_url( admin_url( 'admin.php?page=advanced-ads-placements' ) )
			);
			return $hints;
		}

		// The Group uses a placement where cache busting is disabled.
		foreach ( $placements as $slug => $placement ) {
			if ( isset( $placement['options']['cache-busting'] )
				&& $placement['options']['cache-busting'] === Advanced_Ads_Pro_Module_Cache_Busting::OPTION_OFF
			) {
				$hints[] = sprintf(
					wp_kses(
						// translators: %s is an URL.
						__( 'It seems that a caching plugin is activated. Your ads might not rotate properly, while cache busting is disabled for the placement your group is using. <a href="#" data-placement="%s" class="js-placement-activate-cb">Activate cache busting for this placement.</a>', 'advanced-ads-pro' ),
						[
							'a' => [
								'href'   => [],
								'data-placement' => [],
								'class' => [],
							],
						]
					),
					$slug
				);
				return $hints;
			}
		}

		return $hints;
	}

}
