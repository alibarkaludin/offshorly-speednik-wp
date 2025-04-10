<?php

/**
 * Frontend output of the parallax option.
 */
class Advanced_Ads_Pro_Module_Parallax_Frontend {
	/**
	 * The injected parallax class.
	 *
	 * @var Advanced_Ads_Pro_Module_Parallax
	 */
	private $parallax;

	/**
	 * The generated frontend prefix.
	 *
	 * @var string
	 */
	private $frontend_prefix;

	/**
	 * Check if we have already generated the style.
	 *
	 * @var bool
	 */
	private $style_generated = false;

	/**
	 * Array that will be filled with placement options and passed to the frontend.
	 *
	 * @var array
	 */
	private $placements = [];

	/**
	 * Handle for the frontend script.
	 *
	 * @const string
	 */
	private const SCRIPT_HANDLE = 'advads-parallax-script';

	/**
	 * Name for the window object that holds localized data.
	 *
	 * @const string
	 */
	private const LOCALIZE_OBJECT_NAME = 'advads_parallax_placement_options';

	/**
	 * Static storage to collect wrapped ads.
	 *
	 * @var array
	 */
	private static $wrapped_output = [];
	/**
	 * @var string
	 */
	private $script_debug;

	/**
	 * Constructor.
	 *
	 * @param Advanced_Ads_Pro_Module_Parallax $parallax The parallax class.
	 */
	public function __construct( Advanced_Ads_Pro_Module_Parallax $parallax ) {
		$this->parallax        = $parallax;
		$this->frontend_prefix = Advanced_Ads_Plugin::get_instance()->get_frontend_prefix();
		$this->script_debug    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		add_filter( 'advanced-ads-output-wrapper-options', [ $this, 'add_ad_wrapper' ], 10, 2 );
		add_filter( 'advanced-ads-group-refresh-enabled', [ $this, 'disable_group_refresh' ], 10, 2 );
		add_action( 'wp_footer', [ $this, 'output_placement_options' ] );
	}

	/**
	 * If the ad is in a parallax placement, add another wrapper.
	 *
	 * @param iterable        $wrapper_options Array with the current wrapper options.
	 * @param Advanced_Ads_Ad $ad              The ad object to be rendered.
	 *
	 * @return iterable
	 */
	public function add_ad_wrapper( iterable $wrapper_options, Advanced_Ads_Ad $ad ): iterable {
		if (
			! isset( $ad->args['placement_type'], $ad->args['parallax']['enabled'] )
			|| ! $this->parallax->allowed_on_placement( $ad->args['placement_type'] )
			|| ! $this->ad_has_image( $ad )
		) {
			return $wrapper_options;
		}

		if ( empty( $wrapper_options['id'] ) ) {
			$wrapper_options['id'] = Advanced_Ads_Plugin::get_instance()->get_frontend_prefix() . wp_rand();
		}

		$id = $wrapper_options['id'];

		// if we have already wrapped the ad, change the id. Otherwise, register it.
		if ( array_key_exists( $ad->id, self::$wrapped_output ) ) {
			$id .= random_int( 1, 100 );
		} else {
			self::$wrapped_output[ $ad->id ] = null;
		}

		$wrapper_options['class'][] = $this->frontend_prefix . 'parallax-content';
		$parallax_options           = $ad->args['parallax'];
		$this->placements[ $id ]    = $parallax_options;
		$global_style               = $this->generate_global_style();
		$placement_style            = $this->generate_placement_style( $id, $parallax_options );
		$additional_output          = '';

		// this is a cache-busting AJAX call.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- we're only comparing strings, no security implications.
		if ( wp_doing_ajax() && $_POST['action'] === 'advads_ad_select' ) {
			$script_url            = plugins_url( 'assets/js/parallax-ads' . $this->script_debug . '.js', __DIR__ );
			$localized_object_name = self::LOCALIZE_OBJECT_NAME;
			$localized_data        = wp_json_encode( $this->get_localized_script_data() );
			$additional_output     = <<<A_CB_SCRIPT
<script>
	(()=>{
		window.{$localized_object_name} = window.{$localized_object_name} || {$localized_data};

		if (document.getElementById('{$this->frontend_prefix}'+'parallax-acb-script') !== null) {
			return;
		}
		const script = document.createElement('script');
		script.id = '{$this->frontend_prefix}'+'parallax-acb-script';
		script.src = '{$script_url}';
		document.head.appendChild(script);

		const style = document.createElement('style');
		style.innerText = '{$global_style}';
		document.head.appendChild(style);
	})();
</script>
A_CB_SCRIPT;
		} else {
			if ( did_action( 'wp_enqueue_scripts' ) ) {
				$this->enqueue_script();
			} else {
				add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ] );
			}

			if ( $global_style !== '' ) {
				printf( '<style>%s</style>', esc_html( $global_style ) );
			}
		}

		$additional_output .= sprintf( '<style>%s</style>', $placement_style );
		$this->filter_output( $ad, $id, $additional_output );

		return $wrapper_options;
	}

	/**
	 * Inline the parallax CSS. Generate from PHP to use the frontend prefix.
	 *
	 * @return string
	 */
	private function generate_global_style(): string {
		if ( $this->style_generated ) {
			return '';
		}

		$this->style_generated = true;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- frontend prefix is already escaped
		return <<<CSS
.{$this->frontend_prefix}parallax-container {
	position: relative;
}

.{$this->frontend_prefix}parallax-clip {
	position: absolute;
	top: 0;
	right: 0;
	bottom: 0;
	left: 0;
	clip-path: inset(0);
	clip: rect(auto, auto, auto, auto);
	overflow: hidden;
}

.{$this->frontend_prefix}parallax-inner {
	position: fixed;
	visibility: hidden;
	width: 100%;
	display: flex;
	justify-content: center;
	will-change: transform;
	left: 50%;
	transform: translateX(-50%);
}

.{$this->frontend_prefix}parallax-content {
	height: 100%;
}
.{$this->frontend_prefix}parallax-content * {
	height: 100%;
}

.{$this->frontend_prefix}parallax-content {
	overflow: hidden;
}

.{$this->frontend_prefix}parallax-content img,
.{$this->frontend_prefix}parallax-content iframe,
.{$this->frontend_prefix}parallax-content video,
.{$this->frontend_prefix}parallax-content embed
 {
	object-fit: cover;
	object-position: center;
	max-width: 100%;
	height: auto;
}
CSS;
	}

	/**
	 * Return placement-specific CSS string.
	 *
	 * @param string   $id               Generated ID for the ad.
	 * @param iterable $parallax_options Array of placement options.
	 *
	 * @return string
	 */
	private function generate_placement_style( string $id, iterable $parallax_options ): string {
		$parallax_container_id = $this->frontend_prefix . 'parallax-container-' . $id;
		$css_selectors         = [
			'container' => '#' . $parallax_container_id,
			'inner'     => sprintf( '#%s .%sparallax-inner', $parallax_container_id, $this->frontend_prefix ),
			'content'   => '#' . $id,
		];

		$css_directives = [
			'container' => [
				'height' => $parallax_options['height']['value'] . $parallax_options['height']['unit'],
			],
			'inner'     => [
				'top'    => 0,
			],
			'content'   => [],
		];

		$css_string = '';

		foreach ( $css_directives as $key => $directives ) {
			if ( ! array_key_exists( $key, $css_selectors ) || empty( $directives ) ) {
				continue;
			}

			$directives_string = '';
			foreach ( $directives as $directive => $value ) {
				$directives_string .= "$directive:$value;";
			}
			$css_string .= sprintf( '%s {%s}', $css_selectors[ $key ], $directives_string );
		}

		return $css_string;
	}

	/**
	 * Enqueue the frontend JavaScript.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		if ( wp_script_is( self::SCRIPT_HANDLE ) ) {
			return;
		}

		$dependencies = [];
		if ( wp_script_is( 'advanced-ads-pro/cache_busting' ) ) {
			$dependencies[] = 'advanced-ads-pro/cache_busting';
		}
		wp_enqueue_script( self::SCRIPT_HANDLE, plugins_url( 'assets/js/parallax-ads' . $this->script_debug . '.js', __DIR__ ), $dependencies, AAP_VERSION, true );
	}

	/**
	 * Output the collected placement options to pass to JS.
	 *
	 * @return void
	 */
	public function output_placement_options(): void {
		if ( wp_script_is( self::SCRIPT_HANDLE ) ) {
			wp_localize_script( self::SCRIPT_HANDLE, self::LOCALIZE_OBJECT_NAME, $this->get_localized_script_data() );
		}
	}

	/**
	 * Filter the ad output by adding parallax-specific wrappers.
	 *
	 * @param Advanced_Ads_Ad $ad                The ad object passed from the placement.
	 * @param string          $wrapper_id        The ID for this placement wrapper.
	 * @param string          $additional_output String (script/style) to add after placement.
	 *
	 * @return void
	 */
	private function filter_output( Advanced_Ads_Ad $ad, string $wrapper_id, string $additional_output ): void {
		$additional_output = preg_replace( '/\s+/', ' ', $additional_output );
		/**
		 * Add a wrapper for the parallax effect around the existing ad wrapper.
		 *
		 * @param string          $output   The current ad output string.
		 * @param Advanced_Ads_Ad $inner_ad The ad object to compare against the ad from the placement.
		 *
		 * @return string
		 */
		add_filter( 'advanced-ads-ad-output', function( $output, Advanced_Ads_Ad $inner_ad ) use ( $ad, $additional_output, $wrapper_id ) {
			if ( $ad->id !== $inner_ad->id ) {
				return $output;
			}

			// save the raw output, to re-use it, if the same ad is added in two different parallax placements.
			if ( self::$wrapped_output[ $ad->id ] === null ) {
				self::$wrapped_output[ $ad->id ] = $output;
			}
			$output = self::$wrapped_output[ $ad->id ];

			return <<<OUTPUT
<div class="{$this->frontend_prefix}parallax-container" id="{$this->frontend_prefix}parallax-container-{$wrapper_id}">
	<div class="{$this->frontend_prefix}parallax-clip">
		<div class="{$this->frontend_prefix}parallax-inner">
			$output
		</div>
	</div>
</div>
{$additional_output}
OUTPUT;
		}, 10, 2 );
	}

	/**
	 * Get the data for localizing a script. Either via `wp_localize_script` or in the AJAX call.
	 *
	 * @return array
	 */
	private function get_localized_script_data(): array {
		return [
			'classes'    => [
				'prefix'    => $this->frontend_prefix,
				'container' => 'parallax-container-',
				'clip'      => 'parallax-clip',
				'inner'     => 'parallax-inner',
			],
			'placements' => $this->placements,
		];
	}

	/**
	 * Whether the current ad has an image in its content.
	 * This should also match valid <picture> tags, since they include on `<img>` tag, cf. https://developer.mozilla.org/en-US/docs/Web/HTML/Element/picture
	 *
	 *
	 * @param Advanced_Ads_Ad $ad The current ad object.
	 *
	 * @return bool
	 */
	private function ad_has_image( Advanced_Ads_Ad $ad ): bool {
		if ( $ad->type === 'image' ) {
			return true;
		}

		// Match an opening "<img" tag followed by exactly one whitespace character and see if there is a "src" attribute before the closing ">"
		return (bool) preg_match( '/<img\s[^>]+?src=/i', $ad->content );
	}

	/**
	 * Disable the group refresh if parallax is enabled.
	 *
	 * @param bool               $enabled Whether the group refresh is enabled.
	 * @param Advanced_Ads_Group $group   The current group object.
	 *
	 * @return bool
	 */
	public function disable_group_refresh( bool $enabled, Advanced_Ads_Group $group ): bool {
		if ( ! $enabled || ! isset( $group->ad_args['placement_type'], $group->ad_args['parallax']['enabled'] ) ) {
			return $enabled;
		}

		return false;
	}
}
