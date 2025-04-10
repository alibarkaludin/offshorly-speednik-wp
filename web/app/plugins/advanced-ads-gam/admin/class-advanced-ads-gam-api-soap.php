<?php

/**
 * Handle communication with Google server using PHP's SOAP extension
 */
class Advanced_Ads_Gam_Api_Soap implements Advanced_Ads_Gam_Api {

	/**
	 * Google Ad Manager API version
	 *
	 * @const
	 */
	const API_VERSION = 'v202411';

	/**
	 * GAM API function base URL
	 *
	 * @const
	 */
	const API_URL = 'https://www.google.com/apis/ads/publisher/' . self::API_VERSION;

	/**
	 * WSDL location base URL
	 *
	 * @const
	 */
	const WSDL_URL = 'https://ads.google.com/apis/ads/publisher/' . self::API_VERSION;

	/**
	 * Ad list page size.
	 *
	 * @const
	 */
	const API_PAGINATION = 200;

	/**
	 * API access token
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * OAuth2 handler
	 *
	 * @var Advanced_Ads_Gam_Oauth2
	 */
	private $oauth2;

	/**
	 * Constructor
	 *
	 * @param Advanced_Ads_Gam_Oauth2 $oauth2_handler OAuth2 handler.
	 */
	public function __construct( $oauth2_handler ) {
		$this->oauth2       = $oauth2_handler;
		$this->access_token = $oauth2_handler->get_access_token();
	}

	/**
	 * Get HTTP context headers
	 *
	 * @return array[]
	 */
	private function get_context_headers() {
		return [
			'http' => [
				'protocol_version' => 1.1,
				'header'           => 'Authorization:Bearer ' . $this->access_token . "\r\n",
			],
		];
	}

	/**
	 * Get common SOAP call header
	 *
	 * @return SoapHeader
	 */
	private function get_soap_headers() {
		return new SoapHeader(
			self::API_URL,
			'RequestHeader',
			new SoapVar(
				[
					'ns1:applicationName' => AAGAM_APP_NAME,
					'ns1:networkCode'     => Advanced_Ads_Network_Gam::get_option()['account']['networkCode'],
				],
				SOAP_ENC_OBJECT,
				'RequestHeader',
				self::API_URL
			)
		);
	}

	/**
	 * Get a SoapClient set up for the Inventory Service
	 *
	 * @return SoapClient|WP_Error
	 */
	private function get_inventory_client() {
		try {
			return new SoapClient(
				null,
				[
					'stream_context' => stream_context_create( $this->get_context_headers() ),
					'location'       => self::WSDL_URL . '/InventoryService',
					'uri'            => self::API_URL,
					'use'            => SOAP_LITERAL,
				]
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'soap_fault', $e->getMessage() );
		}
	}

	/**
	 * Query a paginated ad unit list.
	 *
	 * @param int        $offset      query offset.
	 * @param SoapClient $soap_client already set up SOAP client.
	 *
	 * @return stdClass|WP_Error
	 */
	private function get_paged_ad_units( $offset, $soap_client ) {
		try {
			return $soap_client->__soapCall(
				'getAdUnitsByStatement',
				[
					new SoapParam(
						new SoapVar(
							[ 'ns1:query' => 'LIMIT ' . self::API_PAGINATION . ' OFFSET ' . $offset ],
							SOAP_ENC_OBJECT,
							'Statement',
							self::API_URL
						),
						'ns1:filterStatement'
					),
				],
				[],
				[ $this->get_soap_headers() ]
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'soap_fault', $e->getMessage() );
		}
	}

	/**
	 * Format SOAP result to match DB options format and return formatted network data.
	 *
	 * @param stdClass $network data from SOAP call.
	 *
	 * @return array
	 */
	private function format_network_data( $network ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- $network comes from Google's servers.
		return [
			'id'                    => $network->id,
			'networkCode'           => $network->networkCode,
			'displayName'           => $network->displayName,
			'currencyCode'          => $network->currencyCode,
			'isTest'                => (bool) $network->isTest,
			'effectiveRootAdUnitId' => $network->effectiveRootAdUnitId,
		];
		// phpcs:enable
	}

	/**
	 * Test if API access is enabled on the account, then count ad units.
	 *
	 * @param string $root_ad_unit the account's root ad unit (to be excluded from the list).
	 * @param string $network_code current connected network code.
	 *
	 * @return array
	 */
	public function test_the_api( $root_ad_unit, $network_code ) {
		$client = $this->get_inventory_client();

		if ( is_wp_error( $client ) ) {
			return [
				'status' => false,
				'error'  => $client->get_error_message(),
				'code'   => $client->get_error_code(),
			];
		}

		$units_count = 0;
		$total       = PHP_INT_MAX;
		$offset      = 0;
		$options     = Advanced_Ads_Network_Gam::get_option();

		// Query for ad units per chunk of self::API_PAGINATION elements.
		do {
			$units = $this->get_paged_ad_units( $offset, $client );

			if ( is_wp_error( $units ) ) {
				return [
					'status' => false,
					'error'  => $units->get_error_message(),
					'code'   => $units->get_error_code(),
				];
			}

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- $units comes from Google's servers.
			if ( $total > absint( $units->totalResultSetSize ) ) {
				// Update the total ad count. This still includes non-working ads.
				$total = $units->totalResultSetSize;
			}
			// phpcs:enable

			foreach ( $units->results as $unit ) {
				if ( ! $this->is_working_unit( $unit, $root_ad_unit ) ) {
					// Don't count, the unit is not working on web pages.
					$total--;
					continue;
				}

				$units_count++;

				if ( $units_count > Advanced_Ads_Gam_Importer::get_importer_limit() ) {
					$options['ad_count_at_connection'] = $units_count;
					update_option( AAGAM_OPTION, $options );

					return [
						'status' => true,
						'count'  => $units_count,
					];
				}
			}

			$offset += self::API_PAGINATION;
		} while ( $total > $units_count );

		$options['ad_count_at_connection'] = $units_count;
		update_option( AAGAM_OPTION, $options );

		return [
			'status' => true,
			'count'  => $units_count,
		];
	}

	/**
	 * Get all ad units in the current network
	 *
	 * @return array
	 */
	public function get_ad_units() {
		$gam_option = Advanced_Ads_Network_Gam::get_option();
		$client     = $this->get_inventory_client();

		if ( is_wp_error( $client ) ) {
			return [
				'status' => false,
				'error'  => $client->get_error_message(),
				'code'   => $client->get_error_code(),
			];
		}

		$units      = [];
		$unit_count = 0;
		$total      = PHP_INT_MAX;
		$offset     = 0;

		do {
			$response = $this->get_paged_ad_units( $offset, $client );

			if ( is_wp_error( $response ) ) {
				return [
					'status' => false,
					'error'  => $response->get_error_message(),
					'code'   => $response->get_error_code(),
				];
			}

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- $response comes from Google's servers.
			if ( $total > (int) $response->totalResultSetSize ) {
				$total = $response->totalResultSetSize;
			}
			// phpcs:enable

			if ( isset( $response->id ) ) {
				// Only one ad unit.
				$response->results = [ $response ];
			}

			foreach ( $response->results as $unit ) {
				if ( ! self::is_working_unit( $unit, $gam_option['account']['effectiveRootAdUnitId'] ) ) {
					// Excludes non displayable units.
					$total--;
					continue;
				}

				$units[ $unit->id ] = self::format_unit_from_soap_data( $unit, $gam_option['account']['networkCode'], $gam_option['account']['effectiveRootAdUnitId'] );
				$unit_count++;
			}

			$offset += self::API_PAGINATION;
		} while ( $total > $unit_count );

		return [
			'status' => true,
			'units'  => $units,
			'count'  => count( $units ),
		];
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
		$client = $this->get_inventory_client();

		if ( is_wp_error( $client ) ) {
			return [
				'status' => false,
				'error'  => $client->get_error_message(),
				'code'   => $client->get_error_code(),
			];
		}

		try {
			$response = $client->__soapCall(
				'getAdUnitsByStatement',
				[
					new SoapParam(
						new SoapVar(
							[ 'ns1:query' => $what === 'id' ? "WHERE id = '{$value}'" : "WHERE name LIKE '%{$value}%' AND status != 'ARCHIVED'" ],
							SOAP_ENC_OBJECT,
							'Statement',
							self::API_URL
						),
						'ns1:filterStatement'
					),
				],
				[],
				[ $this->get_soap_headers() ]
			);
		} catch ( Exception $e ) {
			return [
				'status' => false,
				'error'  => $e->getMessage(),
			];
		}

		$gam_option = Advanced_Ads_Network_Gam::get_option();
		$units      = [];

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- $response comes from Google's servers.
		if ( $response->totalResultSetSize === '1' ) {
			$response->results = [ $response->results ];
		} elseif ( $response->totalResultSetSize === '0' ) {
			$response->results = [];
		}
		//phpcs:enable

		foreach ( $response->results as $unit ) {
			if ( ! self::is_working_unit( $unit, $gam_option['account']['effectiveRootAdUnitId'] ) ) {
				continue;
			}
			$units[ $unit->id ] = self::format_unit_from_soap_data( $unit, $gam_option['account']['networkCode'], $gam_option['account']['effectiveRootAdUnitId'] );
		}

		return [
			'status' => true,
			'units'  => $units,
			'count'  => count( $units ),
		];
	}

	/**
	 * Get a list of all network associated with the newly obtained token data
	 *
	 * @param array $token_data ALl token data.
	 *
	 * @return array
	 */
	public function get_all_networks( $token_data ) {
		try {
			$soap_var = new SoapVar(
				[ 'ns1:applicationName' => AAGAM_APP_NAME ],
				SOAP_ENC_OBJECT,
				'SoapRequestHeader',
				self::API_URL
			);
			$net      = ( new SoapClient(
				self::WSDL_URL . '/NetworkService?wsdl',
				[ 'stream_context' => stream_context_create( $this->get_context_headers() ) ]
			) )->__soapCall(
				'getAllNetworks',
				[],
				[],
				[
					new SoapHeader(
						self::API_URL,
						'RequestHeader',
						$soap_var
					),
				]
			);
		} catch ( Exception $e ) {
			return [
				'status'     => false,
				'error'      => $e->getMessage(),
				'error_code' => 'soap_fault',
			];
		}

		if ( ! isset( $net->rval ) ) {
			return [
				'status'     => false,
				'error'      => esc_html__( 'Unknown response format', 'advanced-ads-gam' ),
				'error_data' => $net,
			];
		}

		$net_array = json_decode( json_encode( $net ), true );

		if ( empty( $net_array ) ) {
			// Empty account.
			return [
				'status'     => false,
				'error'      => esc_html__( 'No Ad Manager network found in this Google account', 'advanced-ads-gam' ),
				'error_code' => 'empty_account',
			];
		}

		if ( is_array( $net->rval ) ) {
			// Multiple networks in account.
			$networks = [];
			foreach ( $net->rval as $network ) {
				$networks[] = $this->format_network_data( $network );
			}

			return [
				'status'     => false,
				'action'     => 'select_account',
				'token_data' => $token_data,
				'networks'   => $networks,
			];
		}

		$network = $this->format_network_data( $net->rval );
		$this->oauth2->save_tokens( $token_data );
		$gam_option            = Advanced_Ads_Network_Gam::get_option();
		$gam_option['account'] = $network;
		Advanced_Ads_Network_Gam::update_option( $gam_option );

		return [ 'status' => true ];
	}

	/**
	 * Check if a unit is a working one
	 * Not archived, not the root ad unit, not the network (which is also a unit) and is not a native app unit (mobile devices).
	 *
	 * @param stdClass $unit         the ad unit to test.
	 * @param string   $root_ad_unit the root unit of the network.
	 *
	 * @return bool
	 */
	private function is_working_unit( $unit, $root_ad_unit ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- $unit comes from Google's servers.
		return $unit->status !== 'ARCHIVED' && $root_ad_unit !== $unit->id && isset( $unit->parentPath ) && $unit->smartSizeMode !== 'SMART_BANNER';
	}

	/**
	 * Format ad units from SOAP call results
	 *
	 * @param stdClass $unit         ad unit object.
	 * @param string   $network_code current network code.
	 * @param string   $root_unit    effective root ad unit id for the current network.
	 *
	 * @return array
	 */
	public static function format_unit_from_soap_data( $unit, $network_code, $root_unit ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- $unit comes from Google's servers.
		$formatted_unit = [
			'id'                    => $unit->id,
			'networkCode'           => $network_code,
			'effectiveRootAdUnitId' => $root_unit,
			'name'                  => $unit->name,
			'parentPath'            => $unit->parentPath,
			'adUnitCode'            => $unit->adUnitCode,
			'description'           => $unit->description,
			'isFluid'               => $unit->isFluid === 'true',
			'isNative'              => $unit->isNative === 'true',
		];

		if ( isset( $unit->adUnitSizes ) ) {
			$formatted_unit['adUnitSizes'] = $unit->adUnitSizes;
		}
		// phpcs:enable

		return $formatted_unit;
	}

}
