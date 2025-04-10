<?php
/**
 * Compatibility fixes with other plugins.
 */
class Advanced_Ads_Pro_Compatibility {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ] );

		// Set WPML Language.
		// Note: the "Language filtering for AJAX operations" feature of WPML does not work
		// because it sets cookie later then our ajax requests are sent.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX
			&& defined( 'ICL_SITEPRESS_VERSION' )
			&& ! empty( $_REQUEST[ 'wpml_lang' ] ) ) {
			do_action( 'wpml_switch_language', $_REQUEST[ 'wpml_lang' ] );
		}

		// Weglot plugin.
		if ( function_exists( 'weglot_get_current_full_url' ) ) {
			add_filter( 'advanced-ads-pro-display-condition-url-string', [ $this, 'weglot_get_current_full_url' ], 0 );
		}

		// Gravity forms plugin.
		add_action( 'wp_loaded', [ $this, 'gravity_forms_init' ] );
	}

	/**
	 * After the theme is loaded.
	 */
	public function after_setup_theme() {
		// Newspaper theme
		if ( defined( 'TD_THEME_NAME' ) && 'Newspaper' === TD_THEME_NAME ) {
			$options = get_option( 'td_011' );
			// Check if lazy load is enabled (non-existent key or '').
			if ( empty( $options['tds_animation_stack'] ) ) {
				add_filter( 'advanced-ads-ad-image-tag-style', [ $this, 'newspaper_theme_disable_lazy_load' ] );
			}
		}
	}

	/**
	 * Newspaper theme: disable lazy load of the theme to prevent conflict with
	 * cache-busting/lazy-load of the Pro add-on.
	 *
	 * @param str $style
	 * @return str $style
	 */
	public function newspaper_theme_disable_lazy_load( $style ) {
		$style .= 'opacity: 1 !important;';
		return $style;
	}

	/**
	 * Weglot plugin: Get the current full url that contains a lauguage.
	 *
	 * @param string $url_parameter Current URI string.
	 * @return string
	 */
	public function weglot_get_current_full_url( $url_parameter ) {
		if ( wp_doing_ajax() ) {
			return $url_parameter;
		}

		$url_parsed    = wp_parse_url( weglot_get_current_full_url() );
		$url_parameter = $url_parsed['path'];
		if ( isset( $url_parsed['query'] ) ) {
			$url_parameter .= '?' . $url_parsed['query'];
		}

		return $url_parameter;
	}


	/**
	 * Gravity Forms plugin: Do JS initialization
	 *
	 * @return void
	 */
	public function gravity_forms_init() {
		if ( is_admin() || ! function_exists( 'gravity_form_enqueue_scripts' ) ) {
			return;
		}
		$has_ajaxcb_placement = false;
		$gravity_form_ads     = Advanced_Ads::get_instance()->get_model()->get_ads(
			[
				's'          => '[gravityform id=',
				'meta_query' => [
					'key'     => 'allow_shortcodes',
					'value'   => '1',
					'compare' => 'LIKE',
				],
			]
		);

		if ( empty( $gravity_form_ads ) ) {
			return;
		}

		foreach ( Advanced_Ads::get_instance()->get_model()->get_ad_placements_array() as $placement ) {
			if ( isset( $placement['options']['cache-busting'] ) && $placement['options']['cache-busting'] === 'on' ) {
				$has_ajaxcb_placement = true;
				break;
			}
		}

		if ( ! $has_ajaxcb_placement ) {
			return;
		}

		foreach ( $gravity_form_ads as $gravity_form_ad ) {
			if ( preg_match( '#gravityform id="([0-9]+)".*#', $gravity_form_ad->post_content, $form_ids ) ) {
				gravity_form_enqueue_scripts( $form_ids[1], true );
			}
		}
	}
}

