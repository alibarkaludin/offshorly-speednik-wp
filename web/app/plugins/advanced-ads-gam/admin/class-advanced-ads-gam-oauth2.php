<?php

/**
 * Handle Google's OAuth2 tokens and authentication
 */
class Advanced_Ads_Gam_Oauth2 {

	/**
	 * Where to redirect users after they Authorize the application.
	 */
	const API_REDIRECT_URI = 'https://gam-connect.wpadvancedads.com/oauth.php';

	/**
	 * Token endpoint
	 */
	const TOKEN_URL = 'https://www.googleapis.com/oauth2/v4/token';

	/**
	 * Google application client secret
	 *
	 * @var string
	 */
	private $cs;

	/**
	 * Google application client id
	 *
	 * @var string
	 */
	private $cid;

	/**
	 * Current access token. Empty string if we fail in getting a refreshed one
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Constructor: sets credentials
	 */
	public function __construct() {
		$credentials = self::get_credentials();
		$this->cid   = $credentials['id'];
		$this->cs    = $credentials['secret'];
	}

	/**
	 * Perform an HTTP POST request and some basic error handling
	 *
	 * @param array $args the post request arguments.
	 *
	 * @return array
	 */
	private function do_post( $args ) {
		$response = wp_remote_post( self::TOKEN_URL, $args );
		if ( is_wp_error( $response ) ) {
			return [
				'status' => false,
				'error'  => $response->get_error_message(),
			];
		}

		$decoded = json_decode( $response['body'], true );

		if ( $decoded === null ) {
			return [
				'status'        => false,
				'error'         => esc_html__( 'Invalid JSON string', 'advanced-ads-gam' ),
				'response_body' => $response['body'],
			];
		}

		if ( ! is_array( $decoded ) ) {
			$decoded = [ $decoded ];
		}

		return $decoded;
	}

	/**
	 * Renew expired access token
	 *
	 * @return array
	 */
	private function renew_tokens() {
		$gam_option = Advanced_Ads_Network_Gam::get_option();
		$args       = [
			'body' => [
				'refresh_token' => $gam_option['tokens']['refresh_token'],
				'client_id'     => $this->cid,
				'client_secret' => $this->cs,
				'grant_type'    => 'refresh_token',
			],
		];
		$response   = $this->do_post( $args );
		$account    = $gam_option['account']['displayName'] . ' [' . $gam_option['account']['networkCode'] . ']';

		if ( isset( $response['status'] ) && $response['status'] === false ) {
			/* translators: GAM account holder name. */
			$response['error'] = sprintf( esc_html__( 'Error while renewing access token for "%s"', 'advanced-ads-gam' ), $account ) . ' >> ' . $response['error'];

			return $response;
		}

		if ( isset( $response['expires_in'] ) ) {
			$expires                              = time() + absint( $response['expires_in'] );
			$gam_option['tokens']['access_token'] = $response['access_token'];
			$gam_option['tokens']['expires']      = $expires;
			Advanced_Ads_Network_Gam::update_option( $gam_option );

			return [
				'status'       => true,
				'access_token' => $response['access_token'],
			];
		}

		return [
			'status'        => false,
			// translators: Google Ad Manager account ID
			'error'         => sprintf( esc_html__( 'Unknown error while renewing access token for "%s"', 'advanced-ads-gam' ), $account ),
			'json_response' => $response,
		];
	}

	/**
	 * Set manually the access token when the token is not yet and should not be stored in database
	 *
	 * @param string $token the access token.
	 *
	 * @return void
	 */
	public function set_access_token( $token ) {
		$this->access_token = $token;
	}

	/**
	 * Get access token. Check for manually set value first (on 360 accounts).
	 *
	 * @return string
	 */
	public function get_access_token() {
		if ( is_string( $this->access_token ) ) {
			return $this->access_token;
		}

		$option = Advanced_Ads_Network_Gam::get_option();
		if ( time() - 5 > $option['tokens']['expires'] ) {
			// Access token expired, renew it first.
			$new_token = $this->renew_tokens();
			if ( isset( $new_token['access_token'] ) ) {
				return $new_token['access_token'];
			} else {
				return '';
			}
		}

		// Token still valid.
		return $option['tokens']['access_token'];
	}

	/**
	 * Save tokens (access and refresh) into database
	 *
	 * @param array $token_data access tokens data from Google.
	 *
	 * @return void
	 */
	public function save_tokens( $token_data ) {
		$option           = Advanced_Ads_Network_Gam::get_option();
		$option['tokens'] = $token_data;
		Advanced_Ads_Network_Gam::update_option( $option );
	}

	/**
	 * Revoke tokens then reset all records in database
	 *
	 * @return bool
	 */
	public function revoke_tokens() {
		$gam_option = Advanced_Ads_Network_Gam::get_option();
		$response   = wp_remote_post(
			'https://accounts.google.com/o/oauth2/revoke?token=' . $gam_option['tokens']['refresh_token'],
			[
				'timeout' => 5,
				'header'  => [ 'Content-type' => 'application/x-www-form-urlencoded' ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$gam_option['account']  = [];
		$gam_option['tokens']   = [];
		$gam_option['ad_units'] = [];

		Advanced_Ads_Network_Gam::update_option( $gam_option );
		delete_option( AAGAM_API_KEY_OPTION );
		delete_transient( Advanced_Ads_Gam_Admin::ALL_ADUNITS_TRANSIENT );

		return true;
	}

	/**
	 * Submit authorization code for new tokens (refresh and access)
	 *
	 * @param string $code Authorization code.
	 *
	 * @return array
	 */
	public function submit_oauth_code( $code ) {
		$response = $this->do_post(
			[
				'timeout' => 10,
				'body'    => [
					'code'          => $code,
					'client_id'     => $this->cid,
					'client_secret' => $this->cs,
					'redirect_uri'  => self::API_REDIRECT_URI,
					'grant_type'    => 'authorization_code',
				],
			]
		);

		if ( isset( $response['status'] ) && $response['status'] === false ) {
			$response['error'] = esc_html__( 'Error while submitting code', 'advanced-ads-gam' ) . ' >> ' . $response['error'];

			return $response;
		}

		if ( isset( $response['refresh_token'] ) ) {
			return [
				'status'     => true,
				'token_data' => [
					'expires'       => time() + (int) $response['expires_in'],
					'access_token'  => $response['access_token'],
					'refresh_token' => $response['refresh_token'],
				],
			];
		}

		return [
			'status'        => false,
			'error'         => esc_html__( 'Unknown error while submitting code', 'advanced-ads-gam' ),
			'json_response' => $response,
		];
	}

	/**
	 * Get a GAM API handler (depends on the presence of PHP's SOAP extension)
	 *
	 * @return Advanced_Ads_Gam_Api
	 */
	public function get_gam_api_handler() {
		return Advanced_Ads_Gam_Admin::has_soap() ? new Advanced_Ads_Gam_Api_Soap( $this ) : new Advanced_Ads_Gam_Api_Rest( $this );
	}

	/**
	 * Create and return an instance of the class. Return an error array if there is no access token.
	 *
	 * @return Advanced_Ads_Gam_Oauth2|array
	 */
	public static function get_safe_oauth2() {
		$oauth2 = new self();
		if ( empty( $oauth2->get_access_token() ) ) {
			return [
				'status' => false,
				'error'  => 'no access token',
			];
		}

		return $oauth2;
	}

	/**
	 * Create and return an instance of the class. Send a JSON error response (then dies) if there is no access token.
	 *
	 * @return Advanced_Ads_Gam_Oauth2
	 */
	public static function get_safe_ajax_oauth2() {
		$oauth2 = new self();
		if ( empty( $oauth2->get_access_token() ) ) {
			wp_send_json_error( [ 'error' => 'No access token' ], 401 );
		}

		return $oauth2;
	}

	/**
	 * Get the Google application credentials
	 *
	 * @return string[]
	 */
	public static function get_credentials() {
		return [
			'id'     => '473832510505-vfvg0fonh9uippvk73m8pv4uom42glon.apps.googleusercontent.com',
			'secret' => 'nplF-khrk1gggcdVkFmURnSr',
		];
	}
}
