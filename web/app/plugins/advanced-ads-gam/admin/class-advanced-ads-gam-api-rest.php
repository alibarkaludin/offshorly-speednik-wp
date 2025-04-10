<?php

/**
 * Handle communication with Google server via the Advanced Ads REST API
 */
class Advanced_Ads_Gam_Api_Rest implements Advanced_Ads_Gam_Api {
	/**
	 * API access token
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Constructor
	 *
	 * @param Advanced_Ads_Gam_Oauth2 $oauth2_handler handle all Google Oauth2 related tasks.
	 */
	public function __construct( $oauth2_handler ) {
		$this->access_token = $oauth2_handler->get_access_token();
	}

	/**
	 * Perform an HTTP post call and some basic error handling
	 *
	 * @param string $url  the URL to call.
	 * @param array  $args the call arguments.
	 *
	 * @return array
	 */
	private function post( $url, $args ) {
		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'status' => false,
				'error'  => 'REST API: ' . $response->get_error_message(),
			];
		}

		$decoded = json_decode( $response['body'], true );

		if ( $decoded === null ) {
			return [
				'status' => false,
				'error'  => __( 'Invalid JSON string', 'advanced-ads-gam' ),
				'raw'    => $response['body'],
			];
		}

		if ( ! is_array( $decoded ) ) {
			$decoded = [ $decoded ];
		}

		if ( isset( $decoded['apikey'] ) ) {
			update_option( AAGAM_API_KEY_OPTION, $decoded['apikey'] );
		}

		return $decoded;
	}

	/**
	 * Test if API access is enabled on the account. Stores the ad units list in the database if there are less than Advanced_Ads_Gam_Importer::AD_COUNT_LIMIT ads
	 *
	 * @param string $root_ad_unit the account's root ad unit (to be excluded from the list).
	 * @param string $network_code current connected network code.
	 *
	 * @return array
	 */
	public function test_the_api( $root_ad_unit, $network_code ) {
		$results = $this->post(
			AAGAM_NO_SOAP_URL . 'testTheAPI.php',
			[
				'body' => [
					'access_token' => $this->access_token,
					'network'      => $network_code,
					'root'         => $root_ad_unit,
					'apikey'       => get_option( AAGAM_API_KEY_OPTION ),
				],
			]
		);

		if ( isset( $results['status'] ) && $results['status'] === false ) {
			if ( isset( $results['msg'] ) ) {
				$results['error'] = __( 'Testing the API', 'advanced-ads-gam' ) . ' >> ' . $results['msg'];
			}

			return $results;
		}

		if ( isset( $results['count'] ) ) {
			$gam_option                           = Advanced_Ads_Network_Gam::get_option();
			$gam_option['ad_count_at_connection'] = $results['count'];
			if ( isset( $results['all_units'] ) ) {
				$gam_option['ad_units'] = Advanced_Ads_Gam_Admin::sort_ad_units( $results['all_units'] );
			}
			update_option( AAGAM_OPTION, $gam_option );
		}

		return $results;
	}

	/**
	 * Get a list of all network associated with the newly obtained token data
	 *
	 * @param array $token_data ALl token data.
	 *
	 * @return array
	 */
	public function get_all_networks( $token_data ) {
		$results = $this->post(
			AAGAM_NO_SOAP_URL . 'getAllNetworks.php',
			[
				'body' => [
					'token_data' => $token_data,
					'apikey'     => get_option( AAGAM_API_KEY_OPTION ),
				],
			]
		);

		if ( isset( $results['status'] ) && $results['status'] === false ) {
			if ( isset( $results['msg'] ) ) {
				$results['error'] = __( 'No SOAP. Getting network list', 'advanced-ads-gam' ) . ' >> ' . $results['msg'];
			}

			return $results;
		}

		if ( isset( $results['network'] ) ) {
			$oauth2 = new Advanced_Ads_Gam_Oauth2();
			$oauth2->save_tokens( $token_data );
			$gam_option            = Advanced_Ads_Network_Gam::get_option();
			$gam_option['account'] = $results['network'];
			Advanced_Ads_Network_Gam::update_option( $gam_option );

			return [ 'status' => true ];
		}

		if ( isset( $results['networks'], $results['apikey'] ) ) {
			update_option( AAGAM_API_KEY_OPTION, $results['apikey'] );
		}

		return $results;
	}

	/**
	 * Get all ad units in the current network
	 *
	 * @return array
	 */
	public function get_ad_units() {
		if ( ! $this->access_token ) {
			return [
				'status' => false,
				'error'  => __( 'No SOAP. No access token >> getting ad units list', 'advanced-ads-gam' ),
			];
		}

		$results = $this->post( AAGAM_NO_SOAP_URL . 'getUnits.php', [
			'body' => [
				'access_token' => $this->access_token,
				'network'      => Advanced_Ads_Network_Gam::get_option()['account'],
				'apikey'       => get_option( AAGAM_API_KEY_OPTION ),
			],
		] );

		if ( ! is_array( $results ) ) {
			return [
				'status' => false,
				'error'  => __( 'No SOAP. unknown response format >> getting ad units list', 'advanced-ads-gam' ),
				'raw'    => $results,
			];
		}

		if ( isset( $results['status'] ) && $results['status'] === false ) {
			$results['error'] = __( 'No SOAP. Getting all units', 'advanced-ads-gam' ) . ' >> ' . $results['error'];

			return $results;
		}

		return $results;
	}

	/**
	 * Get ad units by name or id
	 *
	 * @param string $what  Property on which we do the search (name or id).
	 * @param string $value value of "$what".
	 *
	 * @return array
	 */
	public function get_ads_by( $what, $value ) {
		if ( ! $this->access_token ) {
			return [
				'status' => false,
				'error'  => __( 'No SOAP. No access token >> getting ad by', 'advanced-ads-gam' ),
			];
		}

		$gam_option = Advanced_Ads_Network_Gam::get_option();

		$args    = [
			'body' => [
				'what'         => $what,
				'value'        => $value,
				'access_token' => $this->access_token,
				'network'      => $gam_option['account']['networkCode'],
				'root'         => $gam_option['account']['effectiveRootAdUnitId'],
				'apikey'       => get_option( AAGAM_API_KEY_OPTION ),
			],
		];
		$results = $this->post( AAGAM_NO_SOAP_URL . 'getAdsBy.php', $args );

		if ( isset( $results['status'] ) && $results['status'] === false ) {
			$results['error'] = __( 'No SOAP. Getting ads by', 'advanced-ads-gam' ) . ' >> ' . $results['error'];

			return $results;
		}

		return $results;
	}

}
