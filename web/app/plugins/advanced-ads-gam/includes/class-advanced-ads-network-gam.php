<?php
/**
 * Network class for GAM
 */
class Advanced_Ads_Network_Gam extends Advanced_Ads_Ad_Network {

	/**
	 * The unique instance of this class
	 *
	 * @var Advanced_Ads_Network_Gam
	 */
	private static $instance;

	/**
	 * Default plugin options
	 *
	 * @var array
	 */
	private static $default_option;

	/**
	 * Construct the unique instance
	 */
	public function __construct() {
		parent::__construct( 'gam', 'Google Ad Manager' );
		self::$default_option = [
			'account'           => [],
			'ad_units'          => [],
			'units_update_time' => 0,
			'tokens'            => [],
			'force_no_soap'     => false,
		];
	}

	/**
	 * Retrieve the plugin's backend option
	 */
	public static function get_option() {
		$options = get_option( AAGAM_OPTION, [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}
		$options += self::$default_option;

		return self::remove_empty_div_option( $options );
	}

	/**
	 * Get settings (settings page)
	 */
	public static function get_setting() {
		$default = [
			'empty-div' => 'default',
		];
		return get_option( AAGAM_SETTINGS, $default );
	}

	/**
	 * Remove "empty-div" filed from options
	 * Also disable autoload of backend option if this field is found. (v1.6.0 and earlier).
	 *
	 * @param array $options options value.
	 *
	 * @return array
	 */
	private static function remove_empty_div_option( $options ) {
		if ( isset( $options['empty-div'] ) ) {
			// Empty div setting already in AAGAM_SETTINGS.
			unset( $options['empty-div'] );
			// Disable autoload of backend option.
			update_option( AAGAM_OPTION, $options, 'no' );
		}

		return $options;
	}

	/**
	 * Update plugin's backend option
	 *
	 * @param [array] $option The new option value.
	 */
	public static function update_option( $option = [] ) {
		if ( ! is_array( $option ) ) {
			return;
		}
		update_option( AAGAM_OPTION, self::remove_empty_div_option( $option ) + self::get_option() );
	}

	/**
	 * Output the actual ad list markup
	 *
	 * @param [bool] $hide_idle_ads Whether to show idle ads.
	 */
	public function print_external_ads_list( $hide_idle_ads = true ) {
		require AAGAM_BASE_PATH . 'admin/views/ads-list.php';
	}

	/**
	 * Register our setting into WordPress Setting API
	 *
	 * @param [string] $hook The settings page hook.
	 * @param [string] $section_id Section in which the settings are added.
	 */
	protected function register_settings( $hook, $section_id ) {
		// add setting account setting.
		add_settings_field(
			'gam-netid',
			esc_html__( 'Ad Manager account', 'advanced-ads-gam' ),
			[ $this, 'render_settings_gam_netid' ],
			$hook,
			$section_id
		);
		$connected = self::get_instance()->is_account_connected();

		if ( $connected ) {
			// add setting account setting.
			add_settings_field(
				'gam-empty-div',
				esc_html__( 'Collapse empty elements', 'advanced-ads-gam' ),
				[ $this, 'render_settings_empty_div' ],
				$hook,
				$section_id
			);
		}
	}

	/**
	 * The callback method for the filter "advanced-ads-ad-types"
	 *
	 * @param [array] $types The currently registerd ad types.
	 */
	public function register_ad_type_callback( $types ) {
		$types[ $this->identifier ] = $this->get_ad_type();
		return $types;
	}

	/**
	 * Markup for "empty div" setting
	 */
	public function render_settings_empty_div() {
		require_once AAGAM_BASE_PATH . 'admin/views/gam-empty-div.php';
	}

	/**
	 * Markup for "network id" setting
	 */
	public function render_settings_gam_netid() {
		require_once AAGAM_BASE_PATH . 'admin/views/gam-network-id.php';
	}

	/**
	 * Retrieves an instance of the ad type for this ad network
	 */
	public function get_ad_type() {
		return new Advanced_Ads_Gam_Ad_Type();
	}

	/**
	 * Update the stored ad unit lists from Google
	 *
	 * This method will be called via wp AJAX.
	 * It has to retrieve the list of ads from the ad network and store it as an option.
	 * Does not return ad units - use "get_external_ad_units" if you're looking for an array of ad units.
	 */
	public function update_external_ad_units() {
		$oauth2   = Advanced_Ads_Gam_Oauth2::get_safe_oauth2();
		$ad_units = $oauth2->get_gam_api_handler()->get_ad_units();

		if ( isset( $ad_units['status'], $ad_units['units'] ) && $ad_units['status'] ) {
			$options                      = self::get_option();
			$units                        = Advanced_Ads_Gam_Admin::sort_ad_units( $ad_units['units'] );
			$options['ad_units']          = $units;
			$options['units_update_time'] = time();
			update_option( AAGAM_OPTION, $options );
		}
	}

	/**
	 * Update data on some units or add them to the list if they are not there.
	 *
	 * @param array $units ad units to be added or updated.
	 */
	public function update_ad_units( $units ) {
		$options     = self::get_option();
		$unit_ids    = array_column( $units, 'id' );
		$unit_by_ids = array_combine( $unit_ids, $units );

		foreach ( $options['ad_units'] as $key => $unit ) {
			// Update existing unit.
			if ( in_array( $unit['id'], $unit_ids, true ) ) {
				$options['ad_units'][ $key ] = $unit_by_ids[ $unit['id'] ];
				unset( $unit_by_ids[ $unit['id'] ] );
			}
		}

		// Some units are not in our stored ad unit list.
		if ( ! empty( $unit_by_ids ) ) {
			foreach ( $unit_by_ids as $unit ) {
				$options['ad_units'][] = $unit;
			}
			$options['ad_units'] = Advanced_Ads_Gam_Admin::sort_ad_units( $options['ad_units'] );
		}

		update_option( AAGAM_OPTION, $options );
	}

	/**
	 * Sanitize the network specific options
	 *
	 * @param array $options the options to sanitize.
	 *
	 * @return array the sanitized options.
	 */
	protected function sanitize_settings( $options ) {
		$options['empty-div'] = ( isset( $options['empty-div'] ) && in_array( $options['empty-div'], [ 'default', 'collapse', 'fill' ] ) ) ? $options['empty-div'] : 'default';

		return $options;
	}

	/**
	 * Sanitize the settings for this ad network
	 *
	 * @param $ad_settings_post.
	 *
	 * @return [mixed] the sanitized settings.
	 */
	public function sanitize_ad_settings( $ad_settings_post ) {
		return $ad_settings_post;
	}

	/**
	 * Get the ad units list
	 *
	 * @return array ad units list.
	 */
	public function get_external_ad_units() {
		$options = self::get_option();
		return $options['ad_units'];
	}

	/**
	 * Checks if the ad_unit is supported by advanced ads
	 *
	 * This determines wheter it can be imported or not.
	 *
	 * @param $ad_unit the ad unit.
	 *
	 * @return [bool]
	 */
	public function is_supported( $ad_unit ) {
		return true;
	}

	/**
	 * Whether there is an account connected
	 *
	 * There is no common way to connect to an external account. You will have to implement it somehow, just
	 * like the whole setup process (usually done in the settings tab of this network).
	 * This method provides a way to return this account connection.
	 *
	 * @return [bool] true, when an account was successfully connected
	 */
	public function is_account_connected() {
		return ! empty( self::get_option()['tokens'] );
	}

	/**
	 * Get the JavaScript file that handle this network in the dashboard
	 *
	 * External ad networks rely on the same JavaScript base code. however you still have to provide
	 * a JavaScript class that inherits from the AdvancedAdsAdNetwork js class
	 * this has to point to that file, or return false,
	 * if you don't have to include it in another way (NOT RECOMMENDED!)
	 *
	 * @return string path to the JavaScript file containing the JavaScript class for this ad type
	 */
	public function get_javascript_base_path() {
		return AAGAM_BASE_URL . 'admin/js/gam.js';
	}

	/**
	 * Inline JavaScript variable appended to the main JavaScript file
	 *
	 * Our script might need translations or other variables (like a nonce, which is included automatically).
	 * Add anything you need in this method and return the array.
	 *
	 * @param array $data array holding the data.
	 *
	 * @return array
	 */
	public function append_javascript_data( &$data ) {
		$kvs        = Advanced_Ads_Gam_Admin::get_instance()->get_key_values_types();
		$gam_option = self::get_option();

		return [
			'hasGamLicense' => Advanced_Ads_Gam_Admin::has_valid_license() ? 'yes' : 'no',
			'kvTypes'       => $kvs,
			'i18n'          => [
				'remove'          => esc_html__( 'Remove', 'advanced-ads-gam' ),
				'cancel'          => esc_html__( 'Cancel', 'advanced-ads-gam' ),
				/* translators: The string is followed by the sanitized key-value input before it is saved */
				'willBeCreatedAs' => esc_html__( 'Will be created as', 'advanced-ads-gam' ),
			],
			'networkCode'   => isset( $gam_option['account'], $gam_option['account']['networkCode'] ) ? $gam_option['account']['networkCode'] : '',
			'rootAdUnit'    => isset( $gam_option['account'], $gam_option['account']['effectiveRootAdUnitId'] ) ? $gam_option['account']['effectiveRootAdUnitId'] : '',
			'adUnitList'    => $this->get_external_ad_units(),
			'importLimit'   => Advanced_Ads_Gam_Importer::get_importer_limit(),
		];
	}

	/**
	 * Return age of the ad units list in seconds
	 *
	 * @return bool|int seconds since last update or FALSE if the age field does not exist.
	 */
	public function get_list_update_age() {
		$options = self::get_option();
		if ( empty( $options['units_update_time'] ) ) {
			return false;
		} else {
			return time() - absint( $options['units_update_time'] );
		}
	}

	/**
	 * Encode an ad unit to be stored as post content.
	 *
	 * @param array $unit The ad unit.
	 *
	 * @return string
	 */
	public static function adunit_to_post_content( $unit ) {
		return base64_encode( rawurlencode( json_encode( $unit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	}

	/**
	 * Decode post content to get working ad unit data.
	 *
	 * @param string $content The post content.
	 *
	 * @return array
	 */
	public static function post_content_to_adunit( $content ) {
		$un64 = base64_decode( $content );

		if ( $un64 !== false ) {
			$jdecode = json_decode( rawurldecode( $un64 ), true );
			if ( $jdecode !== null ) {
				return $jdecode;
			}
		}

		return [];
	}

	/**
	 * Returns the unique instance of this class
	 *
	 * @return Advanced_Ads_Network_Gam
	 */
	final public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Advanced_Ads_Network_Gam();
		}
		return self::$instance;
	}

}
