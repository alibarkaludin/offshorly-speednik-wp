<?php

use AdvancedAds\Utilities\WordPress;

/**
 * AJAX actions handler.
 */
class Advanced_Ads_Gam_Ajax {

	/**
	 * The unique instance
	 *
	 * @var Advanced_Ads_Gam_Ajax unique instance of this class
	 */
	private static $instance;

	/**
	 * Construct the AJAX handler
	 */
	private function __construct() {
		// OAuth2.
		add_action( 'wp_ajax_advads_gamapi_confirm_code', [ $this, 'confirm_api_code' ] );
		add_action( 'wp_ajax_advads_gamapi_revoke', [ $this, 'revoke_token' ] );

		// Google Ad Manager API.
		add_action( 'wp_ajax_advads_gamapi_force_no_soap', [ $this, 'force_no_soap' ] );
		add_action( 'wp_ajax_advads_gamapi_get_key', [ $this, 'get_api_key' ] );
		add_action( 'wp_ajax_advads_gamapi_test_the_api', [ $this, 'test_if_api_enabled' ] );
		add_action( 'wp_ajax_advads_gamapi_getnet', [ $this, 'get_all_networks' ] );
		add_action( 'wp_ajax_advads_gamapi_account_selected', [ $this, 'account_selected' ] );
		add_action( 'wp_ajax_advads_gamapi_search_ads', [ $this, 'search_ads' ] );

		// Importer
		add_action( 'wp_ajax_gam_importable_list', [ $this, 'get_importable_list_markup' ] );
		add_action( 'wp_ajax_advads_gam_import_button', [ $this, 'import_button' ] );
		add_action( 'wp_ajax_advads_gam_importer_units_in_account', [ $this, 'get_units_in_account' ] );
		add_action( 'wp_ajax_gam_import_ads', [ $this, 'launch_import' ] );

		// Ad list
		add_action( 'wp_ajax_advads_gamapi_remove_ad', [ $this, 'remove_ad' ] );
		add_action( 'wp_ajax_advads_gamapi_append_ads', [ $this, 'append_ad_units' ] );
		add_action( 'wp_ajax_advads_gamapi_update_single_ad', [ $this, 'update_single_ad' ] );
		add_action( 'wp_ajax_advads_gam_units_modal', [ $this, 'load_units_modal' ] );
	}

	/**
	 * Get the markup for the ad units modal frame
	 *
	 * @return void
	 */
	public function load_units_modal() {
		self::ajax_checks( 'gam-ad-list' );
		ob_start();
		new Advanced_Ads_Modal( [
			'modal_slug' => 'gam-ad-list',
		] );
		wp_send_json_success(
			[ 'markup' => ob_get_clean() ],
			200
		);
	}

	/**
	 * Forces the add-on to use AA's REST API
	 *
	 * @return void
	 */
	public function force_no_soap() {
		$post_vars                = self::ajax_checks( 'gam-connect' );
		$options                  = Advanced_Ads_Network_Gam::get_option();
		$options['force_no_soap'] = true;
		Advanced_Ads_Network_Gam::update_option( $options );

		$response = $this->fetch_api_key();

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'error' => $response->get_error_message() ], 400 );
		}

		$result = json_decode( $response['body'], true );

		if ( ! isset( $result['key'] ) ) {
			wp_send_json_error( [ 'error' => 'No API key' ], 404 );
		}

		update_option( AAGAM_API_KEY_OPTION, $result['key'] );

		wp_send_json_success( [ 'token_data' => $post_vars['token_data'], ], 200 );
	}

	/**
	 * Add new ads to the stored ad units list.
	 */
	public function append_ad_units() {
		$post_vars     = self::ajax_checks( 'gam-ad-search' );
		$units_strings = $post_vars['units'] ?? [];
		$units         = [];

		foreach ( $units_strings as $string ) {
			$unit = Advanced_Ads_Network_Gam::post_content_to_adunit( $string );
			if ( $unit ) {
				$units[] = $unit;
			}
		}

		Advanced_Ads_Network_Gam::get_instance()->update_ad_units( $units );
		wp_send_json_success( [ 'units' => Advanced_Ads_Network_Gam::get_option()['ad_units'] ], 200 );
	}

	/**
	 * Update data for a stored ad unit.
	 */
	public function update_single_ad() {
		$post_vars = self::ajax_checks( 'gam-ad-list' );
		$results   = Advanced_Ads_Gam_Oauth2::get_safe_ajax_oauth2()->get_gam_api_handler()->get_ads_by( 'id', $post_vars['id'] );

		if ( isset( $results['units'], $results['count'] ) ) {
			// Update ad unit list, add new units if needed.
			Advanced_Ads_Network_Gam::get_instance()->update_ad_units( $results['units'] );

			// Send the updated ad unit list.
			wp_send_json_success( [ 'ad_units' => Advanced_Ads_Network_Gam::get_instance()->get_external_ad_units() ], 200 );
		}

		wp_send_json_error( $results, 400 );
	}

	/**
	 * Remove ads form the stored ad units list.
	 */
	public function remove_ad() {
		$post_vars = self::ajax_checks( 'gam-ad-list' );
		$id        = isset( $post_vars['id'] ) ? sanitize_text_field( $post_vars['id'] ) : '';
		$options   = Advanced_Ads_Network_Gam::get_option();

		foreach ( $options['ad_units'] as $key => $unit ) {
			if ( $unit['id'] === $id ) {
				unset( $options['ad_units'][ $key ] );
			}
		}

		$options['ad_units'] = Advanced_Ads_Gam_Admin::sort_ad_units( $options['ad_units'] );
		update_option( AAGAM_OPTION, $options );
		wp_send_json_success( [ 'ad_units' => $options['ad_units'] ], 200 );
	}

	/**
	 * Search ads by name from the GAM account.
	 */
	public function search_ads() {
		$post_vars = self::ajax_checks( 'gam-ad-search' );
		$search    = isset( $post_vars['search'] ) ? sanitize_text_field( $post_vars['search'] ) : '';
		$results   = Advanced_Ads_Gam_Oauth2::get_safe_ajax_oauth2()->get_gam_api_handler()->get_ads_by( 'name', $search );

		if ( $results['status'] ) {
			wp_send_json_success( [ 'results' => $results ], 200 );
		}

		wp_send_json_error( [ 'results' => $results ], 400 );
	}

	/**
	 * Get importable ads markup
	 */
	public function get_importable_list_markup() {
		self::ajax_checks( 'gam-importer' );
		$network      = Advanced_Ads_Network_Gam::get_instance();
		$external_ads = $network->get_external_ad_units();

		if ( empty( $external_ads ) ) {
			$network->update_external_ad_units();
			$external_ads = $network->get_external_ad_units();
		}

		$importable_ads = [];
		$all_gam_ids    = Advanced_Ads_Gam_Importer::get_instance()->get_all_gam_ids( true );

		foreach ( $external_ads as $ad ) {
			if ( ! in_array( $ad['networkCode'] . '_' . $ad['id'], $all_gam_ids, true ) ) {
				$importable_ads[] = $ad;
			}
		}

		ob_start();
		require_once AAGAM_BASE_PATH . 'admin/views/importer/import-table.php';
		$output = ob_get_clean();

		wp_send_json_success( [ 'html' => $output ] );
	}

	/**
	 * Get ads in account, then return the markup for the import button
	 *
	 * @return void
	 */
	public function import_button() {
		self::ajax_checks( 'gam-importer' );
		$ads = Advanced_Ads_Gam_Admin::get_instance()->get_units_in_account();

		if ( empty( $ads ) ) {
			wp_send_json_success( [] );
		}

		ob_start();
		Advanced_Ads_Gam_Importer::import_button();
		$html = ob_get_clean();
		wp_send_json_success(
			[ 'html' => $html ]
		);
	}

	/**
	 * Launch the successive post insertion
	 */
	public function launch_import() {
		$post_vars = self::ajax_checks( 'gam-importer' );
		$max_time  = time() + ( .75 * absint( ini_get( 'max_execution_time' ) ) );
		$ad_ids    = [];

		if ( is_array( $post_vars['ids'] ) ) {
			// A sub call, ad IDs already in array format.
			$ad_ids = $post_vars['ids'];
		} else {
			// The initial call, parse the serialized ad IDs.
			parse_str( $post_vars['ids'], $id_arr );
			if ( isset( $id_arr['gam-ad-id'] ) && is_array( $id_arr['gam-ad-id'] ) ) {
				$ad_ids = $id_arr['gam-ad-id'];
			}
		}

		// Grab the original ad IDs list if it's a sub call.
		$original_ids      = isset( $post_vars['original_ids'] ) ? $post_vars['original_ids'] : $ad_ids;
		$time_up           = false;
		$imported_ad_count = isset( $post_vars['imported_ad_count'] ) ? (int) $post_vars['imported_ad_count'] : 0;

		while ( isset( $ad_ids[0] ) ) {
			// If we consumed more than 75% of PHP's max_execution_time, go for another AJAX call.
			if ( time() > $max_time ) {
				$time_up = true;
				break;
			}
			$imported = Advanced_Ads_Gam_Importer::get_instance()->import_single_ad( $ad_ids[0] );
			array_shift( $ad_ids );
			if ( $imported !== 0 && ! is_wp_error( $imported ) ) {
				$imported_ad_count++;
			}
		}

		// Exited the WHILE loop because of time restriction.
		if ( $time_up ) {
			// Send all the form data needed for the next AJAX call.
			wp_send_json_success(
				[
					'form_data' => [
						'nonce'             => wp_create_nonce( 'gam-importer' ),
						'ids'               => $ad_ids,
						'original_ids'      => $original_ids,
						'action'            => 'gam_import_ads',
						'imported_ad_count' => $imported_ad_count,
					],
				]
			);
		} else {
			// All ads have been processed. Send the final markup.
			/* translators: amount of ads imported */
			$html   = '<h2>' . sprintf( _n( '%s ad imported.', '%s ads imported.', $imported_ad_count, 'advanced-ads-gam' ), number_format_i18n( $imported_ad_count ) ) . '</h2>';
			$footer = '<a class="button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=advanced_ads' ) ) . '">' . esc_html__( 'Open ad overview', 'advanced-ads-gam' ) . '</a><button class="button advads-modal-close">' . esc_html__( 'Close', 'advanced-ads-gam' ) . '</button>';

			wp_send_json_success(
				[
					'html'      => $html,
					'footer'    => $footer,
					'allGAMAds' => Advanced_Ads_Gam_Admin::get_instance()->get_units_in_account(),
				]
			);
		}
	}

	/**
	 * Fetch API key from AA servers
	 *
	 * @return array|WP_Error
	 */
	private function fetch_api_key() {
		return wp_remote_post(
			AAGAM_NO_SOAP_URL . 'getAPIKey.php',
			[
				'body' => [
					'site' => site_url(),
				],
			]
		);
	}

	/**
	 * Get REST API key
	 */
	public function get_api_key() {
		$response = $this->fetch_api_key();

		if ( is_wp_error( $response ) ) {
			wp_send_json(
				[
					'status' => false,
					'error'  => 'error while getting api key',
					'raw'    => $response->get_error_message(),
				]
			);
		}

		$results = json_decode( $response['body'], true );

		if ( isset( $results['key'] ) ) {
			update_option( AAGAM_API_KEY_OPTION, $results['key'] );
			wp_send_json( [ 'status' => true ] );
		}

		wp_send_json(
			[
				'status' => false,
				'error'  => 'incorrect response while getting api key',
				'raw'    => $response['body'],
			]
		);
	}

	/**
	 * Store network data on 360 account and store token data
	 */
	public function account_selected() {
		$post_vars             = self::ajax_checks( 'gam-connect' );
		$account_index         = $post_vars['index'];
		$extra_data            = json_decode( $post_vars['extra_data'], true );
		$token_data            = $extra_data['token_data'];
		$networks              = $extra_data['networks'];
		$gam_option            = Advanced_Ads_Network_Gam::get_option();
		$gam_option['account'] = $networks[ $account_index ];
		Advanced_Ads_Network_Gam::update_option( $gam_option );
		( new Advanced_Ads_Gam_Oauth2() )->save_tokens( $token_data );
		wp_send_json( [ 'status' => true ] );
	}

	/**
	 * Check if API is enabled on the user's account
	 */
	public function test_if_api_enabled() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce check done by `self::ajax_checks`
		$action       = isset( $_POST['nonce_action'] ) ? sanitize_key( $_POST['nonce_action'] ) : '';
		$post_vars    = self::ajax_checks( $action );
		$root_ad_unit = isset( $post_vars['root'] ) ? $post_vars['root'] : '';
		$network      = isset( $post_vars['network'] ) ? $post_vars['network'] : '';
		$results      = Advanced_Ads_Gam_Oauth2::get_safe_ajax_oauth2()->get_gam_api_handler()->test_the_api( $root_ad_unit, $network );

		if ( $results['status'] ) {
			wp_send_json_success( $results, 200 );
		}

		wp_send_json_error( $results, 400 );
	}

	/**
	 * Get all networks associated with the fresh access token
	 */
	public function get_all_networks() {
		$post_vars = self::ajax_checks( 'gam-connect' );
		$oauth2    = new Advanced_Ads_Gam_Oauth2();
		$token     = isset( $post_vars['token_data'] ) ? $post_vars['token_data']['access_token'] : $oauth2->get_access_token();

		if ( $token === false ) {
			wp_send_json(
				[
					'status' => false,
					'error'  => 'no access token',
				]
			);
		}

		$oauth2->set_access_token( $token );
		$networks = $oauth2->get_gam_api_handler()->get_all_networks( $post_vars['token_data'] );

		if ( $networks['status'] ) {
			wp_send_json_success( [ 'action' => 'reload' ], 200 );
		}

		if ( isset( $networks['action'] ) && $networks['action'] === 'select_account' ) {
			wp_send_json_success( $networks, 200 );
		}

		wp_send_json_error( $networks, 404 );
	}

	/**
	 * Submit authorization code for access tokens
	 */
	public function confirm_api_code() {
		$post_vars = self::ajax_checks( 'gam-connect' );
		self::disable_no_soap();
		wp_send_json( ( new Advanced_Ads_Gam_Oauth2() )->submit_oauth_code( $post_vars['code'] ) );
	}

	/**
	 *  Revoke a refresh token. Also reset options and delete API key if any.
	 */
	public function revoke_token() {
		self::ajax_checks( 'gam-connect' );
		self::disable_no_soap();
		wp_send_json( [ 'status' => ( new Advanced_Ads_Gam_Oauth2() )->revoke_tokens() ] );
	}

	/**
	 * Allow trying SOAP on the next connection attempt
	 *
	 * @return void
	 */
	private static function disable_no_soap() {
		$options = Advanced_Ads_Network_Gam::get_option();
		$options['force_no_soap'] = false;
		Advanced_Ads_Network_Gam::update_option( $options );
	}

	/**
	 * Check nonces and user capability on AJAX POST call
	 *
	 * @param string $action action name to check the nonce against.
	 *
	 * @return array the content of $_POST.
	 */
	private static function ajax_checks( $action ) {
		$post_vars = wp_unslash( $_POST );

		$cap = method_exists( 'AdvancedAds\Utilities\WordPress', 'user_cap' )
			? WordPress::user_cap( 'advanced_ads_manage_options' )
			: Advanced_Ads_Plugin::user_cap( 'advanced_ads_manage_options' );

		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( [ 'error' => esc_html__( 'Not Authorized', 'advanced-ads-gam' ) ], 403 );
		}

		if ( ! isset( $post_vars['nonce'] ) || ! wp_verify_nonce( $post_vars['nonce'], $action ) ) {
			wp_send_json_error( [ 'error' => esc_html__( 'Bad request', 'advanced-ads-gam' ) ], 400 );
		}

		return $post_vars;
	}

	/**
	 * Returns or construct the singleton
	 *
	 * @return Advanced_Ads_Gam_Ajax
	 */
	final public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
