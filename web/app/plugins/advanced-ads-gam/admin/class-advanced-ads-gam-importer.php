<?php

/**
 * GAM ad importer.
 *
 * Import ad units from a connected Google Ad Manager account into WordPress.
 */
class Advanced_Ads_Gam_Importer {
	/**
	 * The unique instance of this class
	 *
	 * @var Advanced_Ads_Gam_Importer.
	 */
	private static $instance;

	/**
	 * All GAM ads ids (in the format "networkCode_id").
	 *
	 * @var array.
	 */
	private $all_gam_ids;

	/**
	 * Private constructor
	 */
	private function __construct() {}

	/**
	 * Get the maximum ad count supported by the importer
	 *
	 * @return int
	 */
	public static function get_importer_limit() {
		$limit = 50;

		/**
		 * Filter the maximum amount of ad that can be imported.
		 *
		 * @param int $limit maximum ad count allowed.
		 */
		return (int) apply_filters( 'advanced-ads-gam-ad-import-limit', $limit );
	}

	/**
	 * Get all GAM ad unique IDs from AA ad list (in the format "networkCode_unitId")
	 *
	 * @param bool $include_trash include trashed ads.
	 *
	 * @return array
	 */
	public function get_all_gam_ids( $include_trash = false ) {
		if ( $this->all_gam_ids === null ) {
			$all_gam_ads = Advanced_Ads_Gam_Admin::get_instance()->get_all_gam_ads( $include_trash );
			foreach ( $all_gam_ads as $_ad ) {
				$ad_content = Advanced_Ads_Network_Gam::post_content_to_adunit( $_ad->post_content );
				if ( $ad_content ) {
					$this->all_gam_ids[] = $ad_content['networkCode'] . '_' . $ad_content['id'];
				}
			}
		}

		return is_array( $this->all_gam_ids ) ? $this->all_gam_ids : [];
	}

	/**
	 * Insert a new ad in the DB
	 *
	 * @param int $unit_id ad unit id in the current GAM network.
	 */
	public function import_single_ad( $unit_id ) {
		$units_in_account = Advanced_Ads_Gam_Admin::get_instance()->get_units_in_account();
		$ad_unit          = false;

		foreach ( $units_in_account as $ad ) {
			if ( $unit_id === $ad['id'] ) {
				$ad_unit = $ad;
				break;
			}
		}

		// Somehow the ad unit is not in the current network.
		if ( ! $ad_unit ) {
			return 0;
		}

		$post_content = Advanced_Ads_Network_Gam::adunit_to_post_content( $ad_unit );
		$post_title   = 'GAM: ' . wp_strip_all_tags( $ad_unit['name'] );

		/**
		 * Allow user to change the name of the imported ad.
		 *
		 * @param string $post_title Default name (GAM: $ad_unit['name']).
		 * @param array  $ad_unit    The ad unit data.
		 */
		$post_title = apply_filters( 'advanced-ads-gam-ad-import-title', $post_title, $ad_unit );

		$post_id = wp_insert_post(
			[
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => 'publish',
				'post_type'    => Advanced_Ads::POST_TYPE_SLUG,
				'meta_input'   => [
					Advanced_Ads_Ad::$options_meta_field => [ 'type' => 'gam' ],
				],
			]
		);

		$gam_option = Advanced_Ads_Network_Gam::get_option();
		if ( ! in_array( $ad_unit['id'], array_column( $gam_option['ad_units'], 'id' ), true ) ) {
			$gam_option['ad_units'][] = $ad_unit;
			update_option( AAGAM_OPTION, $gam_option );
		}

		return $post_id;
	}

	/**
	 * Get the amount of ads counted at account connection.
	 *
	 * @return int
	 */
	public static function get_ad_count_at_connection() {
		$options = Advanced_Ads_Network_Gam::get_option();

		return isset( $options['ad_count_at_connection'] ) ? $options['ad_count_at_connection'] : count( $options['ad_units'] );
	}

	/**
	 * Check if there is something that can be imported
	 *
	 * @return int importable ads count.
	 */
	public function has_importable() {
		$ads_in_account = Advanced_Ads_Gam_Admin::get_instance()->get_units_in_account();

		if ( ! $ads_in_account ) {
			return 0;
		}

		$all_gam_ids         = $this->get_all_gam_ids( true );
		$importable_ad_count = 0;

		foreach ( $ads_in_account as $ad ) {
			if ( ! in_array( $ad['networkCode'] . '_' . $ad['id'], $all_gam_ids, true ) ) {
				$importable_ad_count++;
			}
		}

		return $importable_ad_count;
	}

	/**
	 * Prints the import ads button
	 */
	public static function import_button() {
		// Don't show the button if there is no valid license.
		if ( ! Advanced_Ads_Gam_Admin::has_valid_license() ) {
			return;
		}

		$ads_in_account = get_transient( Advanced_Ads_Gam_Admin::ALL_ADUNITS_TRANSIENT );

		if ( empty( $ads_in_account ) ) {
			echo '<span id="gam-late-import-button" data-nonce="' . esc_attr( wp_create_nonce( 'gam-importer' ) ) . '"><span>';
		} else {
			// There are external ad units that can be imported (and the external ad list has been updated at least once. Otherwise, "has_importable" will always return false).
			if ( self::get_instance()->has_importable() ) {
				require_once AAGAM_BASE_PATH . 'admin/views/importer/base-frame.php';
			}
		}
	}

	/**
	 * Returns or construct the singleton
	 *
	 * @return Advanced_Ads_Gam_Importer.
	 */
	final public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
