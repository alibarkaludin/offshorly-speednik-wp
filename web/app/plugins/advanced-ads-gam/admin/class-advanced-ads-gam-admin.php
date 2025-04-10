<?php

use AdvancedAds\Utilities\Conditional;
use AdvancedAds\Utilities\WordPress;

/**
 * Dashboard class for GAM
 */
class Advanced_Ads_Gam_Admin {

	/**
	 * The unique instance of this class
	 *
	 * @var Advanced_Ads_Gam_Admin
	 */
	private static $instance;

	/**
	 * Link to plugin page
	 *
	 * @const
	 */
	const PLUGIN_LINK = 'https://wpadvancedads.com/add-ons/google-ad-manager/';

	/**
	 * Where to redirect users after they authorize the application.
	 *
	 * @const
	 */
	const API_REDIRECT_URI = 'https://gam-connect.wpadvancedads.com/oauth.php';

	/**
	 * All GAM ads.
	 *
	 * @var array
	 */
	private $all_gam_ads;

	/**
	 * List of all ad units in the GAM account.
	 *
	 * @var string
	 */
	const ALL_ADUNITS_TRANSIENT = 'advanced-ads-gam-all-units';

	/**
	 * All ad units in the GAM account.
	 *
	 * @var array
	 */
	private $units_in_account;

	/**
	 * Ad unit transient time to live (in seconds).
	 *
	 * @var int
	 */
	const ADUNITS_TRANSIENT_TTL = 900;

	/**
	 * Private constructor
	 */
	private function __construct() {
		add_action( 'admin_footer', [ $this, 'admin_footer' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_script' ] );
		add_action( 'advanced-ads-settings-init', [ $this, 'settings_init' ], 10, 1 );
		add_filter( 'plugin_action_links_' . AAGAM_BASE, [ $this, 'add_plugin_link' ] );
		add_action( 'advanced-ads-submenu-pages', [ $this, 'add_submenu_link' ] );
		add_filter( 'advanced-ads-add-ons', [ $this, 'register_auto_updater' ], 10 );
		add_action( 'save_post_advanced_ads', [ $this, 'save_ad' ] );
	}

	/**
	 * Get the list of ad units in the account. Update it from Google if needed
	 *
	 * @return array
	 */
	public function get_units_in_account() {
		if ( is_array( $this->units_in_account ) ) {
			return $this->units_in_account;
		}

		$units = get_transient( self::ALL_ADUNITS_TRANSIENT );

		if ( is_array( $units ) ) {
			set_transient( self::ALL_ADUNITS_TRANSIENT, $units, self::ADUNITS_TRANSIENT_TTL );
			$this->units_in_account = $units;

			return $units;
		}

		if ( isset( $_POST['nonce_action'], $_POST['nonce'] ) ) {
			$nonce_action = $_POST['nonce_action'] === 'gam-connect' ? 'gam-connect' : 'gam-importer';

			if ( ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), $nonce_action ) ) {
				return [];
			}

			$oauth2   = Advanced_Ads_Gam_Oauth2::get_safe_oauth2();
			$ad_units = $oauth2->get_gam_api_handler()->get_ad_units();

			if ( isset( $ad_units['status'], $ad_units['units'] ) && $ad_units['status'] ) {
				set_transient( self::ALL_ADUNITS_TRANSIENT, $ad_units['units'], self::ADUNITS_TRANSIENT_TTL );

				if ( Advanced_Ads_Gam_Importer::get_importer_limit() >= count( $ad_units['units'] ) ) {
					$options                      = Advanced_Ads_Network_Gam::get_option();
					$options['ad_units']          = self::sort_ad_units( $ad_units['units'] );
					$options['units_update_time'] = time();
					update_option( AAGAM_OPTION, $options );
				}

				return $ad_units['units'];
			}
		}

		return [];
	}

	/**
	 * Save key value targeting post meta
	 *
	 * @param int $post_id the current post ID.
	 */
	public function save_ad( $post_id ) {
		$post_vars = wp_unslash( $_POST );
		$cap = method_exists( 'AdvancedAds\Utilities\WordPress', 'user_cap' )
			? WordPress::user_cap( 'advanced_ads_edit_ads' )
			: Advanced_Ads_Plugin::user_cap( 'advanced_ads_edit_ads' );

		if ( ! current_user_can( $cap ) // only use for ads, no other post type.
			 || ! isset( $post_vars['post_type'] )
			 || Advanced_Ads::POST_TYPE_SLUG != $post_vars['post_type']
			 || ! isset( $post_vars['advanced_ad']['type'] )
			 || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$the_ad                    = new Advanced_Ads_Ad( $post_id );
		$ad_options                = $the_ad->options();
		$ad_options['gam-refresh'] = empty( $post_vars['advanced_ad']['gam-refresh'] ) ? 0 : (int) $post_vars['advanced_ad']['gam-refresh'];

		if ( isset( $post_vars['advanced_ad']['gam'] ) && isset( $post_vars['advanced_ad']['gam']['key'] ) ) {
			$keyval = [];
			foreach ( $post_vars['advanced_ad']['gam']['key'] as $key => $value ) {
				if ( isset( $post_vars['advanced_ad']['gam']['value'] ) && is_array( $post_vars['advanced_ad']['gam']['value'] ) ) {
					$keyval[] = [
						'type'       => $post_vars['advanced_ad']['gam']['type'][ $key ],
						'key'        => $value,
						'value'      => $post_vars['advanced_ad']['gam']['value'][ $key ],
						'onarchives' => $post_vars['advanced_ad']['gam']['onarchives'][ $key ],
					];
				}
			}
			$ad_options['gam-keyval'] = $keyval;
		} elseif ( isset( $ad_options['gam-keyval'] ) ) {
			unset( $ad_options['gam-keyval'] );
		}
		Advanced_Ads_Ad::save_ad_options( $post_id, $ad_options );
	}

	/**
	 * Get key values types with their name and the selector markup
	 *
	 * @return array all key values types.
	 */
	public function get_key_values_types() {
		$kvs = [
			'custom' => [
				'name' => esc_html__( 'Custom key', 'advanced-ads-gam' ),
				'html' => '<input type="text" id="advads-gam-kv-value-input" />',
			],
		];

		$kvs['post_types'] = [
			'name' => esc_html__( 'Post types', 'advanced-ads-gam' ),
			'html' => '<input type="hidden" name="advanced_ad[gam][value][]" value="" /><span class="description">' . sprintf( esc_html( 'Post type slug. E.g., %s or %s.', 'advanced-ads-gam' ), '<code>post</code>', '<code>page</code>' ) . '</span>',
		];

		$kvs['page_slug'] = [
			'name' => esc_html__( 'Page slug', 'advanced-ads-gam' ),
			'html' => '<input type="hidden" name="advanced_ad[gam][value][]" value="" /><span class="description">' . esc_html( 'Slug of the current post, page, or archive.', 'advanced-ads-gam' ) . '</span>',
		];

		$kvs['page_type'] = [
			'name' => esc_html__( 'Page type', 'advanced-ads-gam' ),
			'html' => '<input type="hidden" name="advanced_ad[gam][value][]" value="" /><span class="description">' . sprintf(
				/* translators: 1: 'single', 2: 'archive', 3: 'home', 4: 'blog'  */
				esc_html( '%1$s, %2$s, %3$s (if the front page lists your posts), or %4$s (on the blog page).', 'advanced-ads-gam' ),
				'<code>single</code>',
				'<code>archive</code>',
				'<code>home</code>',
				'<code>blog</code>'
			) . '</span>',
		];

		$kvs['page_id'] = [
			'name' => esc_html__( 'Page ID', 'advanced-ads-gam' ),
			'html' => '<input type="hidden" name="advanced_ad[gam][value][]" value="" /><span class="description">' . esc_html__( 'ID of the current post or page.', 'advanced-ads-gam' ) . '</span>',
		];

		$kvs['placement_id'] = [
			'name' => esc_html__( 'Placement ID', 'advanced-ads-gam' ),
			'html' => '<input type="hidden" name="advanced_ad[gam][value][]" value="" /><span class="description">' . esc_html__( 'ID of the Advanced Ads placement.', 'advanced-ads-gam' ) . '</span>',
		];

		$kvs['postmeta'] = [
			'name' => esc_html__( 'Post meta', 'advanced-ads-gam' ),
			'html' => '<input type="text" id="advads-gam-kv-value-input" /><br><span class="description">' . esc_html__( 'Enter the post meta "meta_key" as a value.', 'advanced-ads-gam' ) . '</span>',
		];

		$kvs['usermeta'] = [
			'name' => esc_html__( 'User meta', 'advanced-ads-gam' ),
			'html' => '<input type="text" id="advads-gam-kv-value-input" /><br><span class="description">' . esc_html__( 'Enter the user meta "meta_key" as a value.', 'advanced-ads-gam' ) . '</span>',
		];

		$kvs['taxonomy'] = [
			'name' => esc_html__( 'Taxonomy', 'advanced-ads-gam' ),
			'html' => '<input type="hidden" name="advanced_ad[gam][value][]" value="" /><span class="description">' . esc_html__( 'Taxonomy of archive pages.', 'advanced-ads-gam' ) . '</span>',
		];

		$kvs['terms'] = [
			'name' => esc_html__( 'Categories/Tags/Terms', 'advanced-ads-gam' ),
			'html' => '<input type="hidden" name="advanced_ad[gam][value][]" value="" /><span class="description">' . esc_html__( 'Any terms on single pages, including categories and tags.', 'advanced-ads-gam' ) . '</span>' .
					  '<p class="description"><input type="checkbox" class="onarchives" name="advanced_ad[gam][onarchives][]" value="1">' . esc_html__( 'send also on archive pages', 'advanced-ads-gam' ) . '</span>',
		];

		return $kvs;
	}

	/**
	 * Add links to the plugins list
	 *
	 * @param array $links array of links for the plugins, adapted when the current plugin is found.
	 *
	 * @return array $links.
	 */
	public function add_plugin_link( $links ) {
		if ( ! is_array( $links ) ) {
			return $links;
		}

		// Add link to GAM settings.
		if ( ! Advanced_Ads_Network_Gam::get_instance()->is_account_connected() ) {
			$connect_link = '<a href="' . admin_url( 'admin.php?page=advanced-ads-settings#top#gam' ) . '">' . esc_html__( 'Connect to GAM', 'advanced-ads-gam' ) . '</a>';
			array_unshift( $links, $connect_link );
		}

		return $links;
	}

	/**
	 * Add menu link to connect to GAM when the connection was not made yet.
	 */
	public function add_submenu_link() {
		if ( Advanced_Ads_Network_Gam::get_instance()->is_account_connected() ) {
			return;
		}

		global $submenu;
		$cap = method_exists( 'AdvancedAds\Utilities\WordPress', 'user_cap' )
			? WordPress::user_cap( 'advanced_ads_manage_options' )
			: Advanced_Ads_Plugin::user_cap( 'advanced_ads_manage_options' );

		if ( current_user_can( $cap ) ) {
			// phpcs:ignore
			$submenu['advanced-ads'][] = array(
				__( 'Connect to GAM', 'advanced-ads-gam' ), // title.
				$cap, // capability.
				admin_url( 'admin.php?page=advanced-ads-settings#top#gam' ),
				__( 'Connect to GAM', 'advanced-ads-gam' ), // not sure what this is, but it is in the API.
			);
		}
	}

	/**
	 * Enqueue scripts on admin pages
	 */
	public function enqueue_script() {
		$screen = get_current_screen();
		if ( Advanced_Ads::POST_TYPE_SLUG === $screen->id ) {
			// If on the ad edit page.
			wp_enqueue_style( 'advanced-ads-gam-ad-edit', AAGAM_BASE_URL . 'admin/css/ad-edit.css', [], AAGAM_VERSION );
			wp_enqueue_script( 'advanced-ads-gam-key-value', AAGAM_BASE_URL . 'admin/js/key-value.js', [ 'jquery' ], AAGAM_VERSION, true );
			wp_enqueue_script( 'advanced-ads-gam-ad-list', AAGAM_BASE_URL . 'admin/js/ad-list.js', [ 'jquery', 'wp-util' ], AAGAM_VERSION );
			wp_enqueue_script( 'advanced-ads-gam-ad-sizes', AAGAM_BASE_URL . 'admin/js/ad-sizes.js', [ 'jquery', 'wp-util' ], AAGAM_VERSION );
			wp_enqueue_script( 'advanced-ads-gam-predefined-sizes', AAGAM_BASE_URL . 'admin/js/predefined-sizes.js', [ 'jquery', 'wp-util' ], AAGAM_VERSION, true );
			wp_enqueue_script( 'advanced-ads-gam-ad-search', AAGAM_BASE_URL . 'admin/js/ad-search.js', [ 'jquery', 'wp-util' ], AAGAM_VERSION, true );

			$key_values_i18n = [
				'type'               => esc_html__( 'Type', 'advanced-ads-gam' ),
				'key'                => esc_html__( 'Key', 'advanced-ads-gam' ),
				'value'              => esc_html__( 'Value', 'advanced-ads-gam' ),
				'termsOnArchives'    => esc_html__( 'Any terms, including categories and tags. Enabled on single and archive pages.', 'advanced-ads-gam' ),
				'termsNotOnArchives' => esc_html__( 'Any terms, including categories and tags. Enabled on single pages.', 'advanced-ads-gam' ),
			];
			wp_localize_script( 'advanced-ads-gam-key-value', 'advadsGamKvsi18n', $key_values_i18n );
			wp_localize_script( 'advanced-ads-gam-predefined-sizes', 'advadsGamPredefinedSizes', self::get_predefined_sizes() );
			wp_localize_script( 'advanced-ads-gam-predefined-sizes', 'advadsGamSizesI18n', [ 'default' => esc_html__( 'default', 'advanced-ads-gam' ) ] );
		}

		$is_screen = false;
		if ( method_exists( 'AdvancedAds\Utilities\Conditional', 'is_screen_advanced_ads' ) ) {
			$is_screen = Conditional::is_screen_advanced_ads();
		} elseif ( method_exists( 'Advanced_Ads_Admin', 'screen_belongs_to_advanced_ads' ) ) {
			$is_screen = Advanced_Ads_Admin::screen_belongs_to_advanced_ads();
		}

		if ( $is_screen ) {
			wp_enqueue_style( 'advanced-ads-gam/settings', AAGAM_BASE_URL . 'admin/css/settings.css', [], AAGAM_VERSION );
			wp_enqueue_script( 'advanced-ads-gam/importer', AAGAM_BASE_URL . 'admin/js/ad-importer.js', [ 'jquery', 'wp-util', 'wp-i18n' ], AAGAM_VERSION, true );
			wp_set_script_translations( 'advanced-ads-gam/importer', 'advanced-ads-gam' );

			$cap = method_exists( 'AdvancedAds\Utilities\WordPress', 'user_cap' )
				? WordPress::user_cap( 'advanced_ads_manage_options' )
				: Advanced_Ads_Plugin::user_cap( 'advanced_ads_manage_options' );

			wp_add_inline_script(
				'advanced-ads-gam/importer',
				'var advads_gam_importer_data = ' . wp_json_encode( [
					'allGAMAds'           => Advanced_Ads_Gam_Importer::get_instance()->get_all_gam_ids( true ),
					'allAdUnits'          => get_transient( self::ALL_ADUNITS_TRANSIENT ),
					'adCountAtConnection' => Advanced_Ads_Gam_Importer::get_ad_count_at_connection(),
					'hasLicense'          => self::has_valid_license(),
					'maxCount'            => Advanced_Ads_Gam_Importer::get_importer_limit(),
					'nonce'               => current_user_can( $cap ) ? wp_create_nonce( 'gam-importer' ) : false,
				] ) . ';'
			);
		}
	}

	/**
	 * Print admin footer scripts
	 */
	public function admin_footer() {
		$is_screen = false;
		if ( method_exists( 'AdvancedAds\Utilities\Conditional', 'is_screen_advanced_ads' ) ) {
			$is_screen = Conditional::is_screen_advanced_ads();
		} elseif ( method_exists( 'Advanced_Ads_Admin', 'screen_belongs_to_advanced_ads' ) ) {
			$is_screen = Advanced_Ads_Admin::screen_belongs_to_advanced_ads();
		}

		if ( $is_screen ) {
			require_once AAGAM_BASE_PATH . 'admin/views/async-errors.php';
			$screen = get_current_screen();
			if ( $screen->id === Advanced_Ads::POST_TYPE_SLUG ) {
				require_once AAGAM_BASE_PATH . 'admin/views/ad-list-rows.php';
				require_once AAGAM_BASE_PATH . 'admin/views/ad-search.php';
			}
			require_once AAGAM_BASE_PATH . 'admin/views/gam-connect.php';
		}
	}

	/**
	 * All GAM ad ids in the format "networkCode_id".
	 *
	 * @param bool $include_trash (optional) include trashed ads.
	 *
	 * @return array array with post objects.
	 */
	public function get_all_gam_ads( $include_trash = false ) {
		if ( $this->all_gam_ads === null ) {
			global $wpdb;
			$ad_model          = new Advanced_Ads_Model( $wpdb );
			$this->all_gam_ads = $ad_model->get_ads(
				[
					'post_status' => [ 'publish', 'future', 'draft', 'pending', 'trash' ],
					'meta_query'  => [
						[
							'key'     => 'advanced_ads_ad_options',
							'value'   => 's:4:"type";s:3:"gam"',
							'compare' => 'LIKE',
						],
					],
				]
			);
		}
		if ( $include_trash !== true ) {
			$all_ads = [];
			foreach ( $this->all_gam_ads as $key => $value ) {
				if ( $value->post_status !== 'trash' ) {
					$all_ads[ $key ] = $value;
				}
			}

			return $all_ads;
		}

		return $this->all_gam_ads;
	}

	/**
	 * Add settings to settings page
	 */
	public function settings_init() {
		// Add license key field to license section.
		add_settings_field(
			'gam-license',
			__( 'Google Ad Manager Integration', 'advanced-ads-gam' ),
			[ $this, 'render_settings_license_callback' ],
			'advanced-ads-settings-license-page',
			'advanced_ads_settings_license_section'
		);
	}

	/**
	 * Render license key section
	 */
	public function render_settings_license_callback() {
		$licenses       = get_option( ADVADS_SLUG . '-licenses', [] );
		$license_key    = isset( $licenses['gam'] ) ? $licenses['gam'] : '';
		$license_status = get_option( AAGAM_SETTINGS . '-license-status', false );
		$index          = 'gam';
		$plugin_name    = AAGAM_PLUGIN_NAME;
		$options_slug   = AAGAM_SETTINGS;
		$plugin_url     = self::PLUGIN_LINK;

		// Template in main plugin.
		include ADVADS_BASE_PATH . 'admin/views/setting-license.php';
	}

	/**
	 * Register plugin for the auto updater in the base plugin
	 *
	 * @param array $plugins plugins that are already registered for auto updates.
	 *
	 * @return array $plugins
	 */
	public function register_auto_updater( array $plugins = [] ) {
		$plugins['gam'] = [
			'name'         => AAGAM_PLUGIN_NAME,
			'version'      => AAGAM_VERSION,
			'path'         => AAGAM_BASE_PATH . 'advanced-ads-gam.php',
			'options_slug' => AAGAM_SETTINGS,
		];

		return $plugins;
	}

	/**
	 * Sort ad units list alphabetically by name
	 *
	 * @param array $units the ad unit list.
	 *
	 * @return array
	 */
	public static function sort_ad_units( $units ) {
		usort( $units, static function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return $units;
	}

	/**
	 * Check if SOAP extension is enabled
	 *
	 * @return bool
	 */
	public static function has_soap() {
		return Advanced_Ads_Network_Gam::get_option()['force_no_soap'] ? false : class_exists( 'SoapClient' );
	}

	/**
	 * Check if there is a valid license for the add-on
	 *
	 * @return bool TRUE if there is a valid license.
	 */
	public static function has_valid_license() {
		return Advanced_Ads_Admin_Licenses::get_instance()->get_license_status( 'advanced-ads-gam' ) === 'valid';
	}

	/**
	 * Returns a list of GAM predefined ad sizes.
	 *
	 * @return array size strings.
	 */
	public static function get_predefined_sizes() {
		return include AAGAM_BASE_PATH . 'includes/predefined-sizes.php';
	}

	/**
	 * Returns or construct the singleton
	 *
	 * @return Advanced_Ads_Gam_Admin
	 */
	final public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
