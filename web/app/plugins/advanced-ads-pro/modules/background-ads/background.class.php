<?php
/**
 * Background ads class.
 */
class Advanced_Ads_Pro_Module_Background_Ads {

	/**
	 * All placements, cached in this property.
	 *
	 * @var array[]
	 */
	private $placements;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->placements = get_option( 'advads-ads-placements', [] );
		add_action( 'wp_footer', [ $this, 'footer_injection' ], 20 );
		add_action( 'wp_head', [ $this, 'initialise_rotating_click_listener' ] );

		// Register output change hook.
		add_filter( 'advanced-ads-output-final', [ $this, 'ad_output' ], 20, 3 );
	}

	/**
	 * Creates abort controller to reset the click event listeners for rotating ads.
	 *
	 * @return void
	 */
	public function initialise_rotating_click_listener() {
		if ( ! $this->contains_background_placement() ) {
			return;
		}
		wp_register_script( 'advanced-ads-pro/background-ads', '', [], AAP_VERSION, false );
		wp_enqueue_script( 'advanced-ads-pro/background-ads' );
		wp_add_inline_script( 'advanced-ads-pro/background-ads', 'let abort_controller = new AbortController();' );
	}

	/**
	 * Echo the placement output into the footer.
	 *
	 * @return void
	 */
	public function footer_injection() {
		foreach ( $this->placements as $placement_id => $placement ) {
			if ( isset( $placement['type'] ) && $placement['type'] === 'background' ) {
				// display the placement content with placement options
				$options = $placement['options'] ?? [];
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- this is our own code, escaping will break it
				echo Advanced_Ads_Select::get_instance()->get_ad_by_method( $placement_id, 'placement', $options );
			}
		}
	}

	/**
	 * Check if a background placement exists.
	 *
	 * @return bool
	 */
	protected function contains_background_placement() : bool {
		foreach ( $this->placements as $placement ) {
			if ( isset( $placement['type'] ) && $placement['type'] === 'background' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Change ad output.
	 *
	 * @param string          $output Ad output.
	 * @param Advanced_Ads_Ad $ad Ad object.
	 * @param array           $output_options Output options.
	 * @return string
	 */
	public function ad_output( $output, $ad, $output_options ) {
		if ( ! isset( $ad->args['placement_type'] ) || $ad->args['placement_type'] !== 'background' ) {
			return $output;
		}

		if ( ! isset( $ad->type ) || $ad->type !== 'image' ) {
			return $output;
		}

		$background_color = isset( $ad->args['bg_color'] ) ? sanitize_text_field( $ad->args['bg_color'] ) : false;

		// Get prefix and generate new body class.
		$prefix = Advanced_Ads_Plugin::get_instance()->get_frontend_prefix();
		$class  = $prefix . 'body-background';

		// Get the ad image.
		$image = wp_get_attachment_image_src( (int) $ad->output['image_id'], 'full' );
		if ( ! $image ) {
			return $output;
		}
		list( $image_url, $image_width, $image_height ) = $image;
		if ( empty( $image_url ) ) {
			return $output;
		}

		$selector = apply_filters( 'advanced-ads-pro-background-selector', 'body' );
		$is_amp   = function_exists( 'advads_is_amp' ) && advads_is_amp();
		$link     = ! empty( $ad->url ) ? $ad->url : '';
		/**
		 * Filter the background placement URL.
		 *
		 * @param string $link The URL.
		 * @param Advanced_Ads_Ad $ad The current ad object.
		 */
		$link = (string) apply_filters( 'advanced-ads-pro-background-url', $link, $ad );

		if ( method_exists( 'Advanced_Ads_Tracking_Util', 'get_target' ) ) {
			$target = Advanced_Ads_Tracking_Util::get_target( $ad, true );
		} else {
			$options = Advanced_Ads::get_instance()->options();
			$target  = isset( $options['target-blank'] ) ? '_blank' : '';
		}
		$target = $target !== '' ? $target : '_self';

		ob_start();
		?>
		<style>
			<?php echo $selector; ?> {
				background: url(<?php echo $image_url; ?>) no-repeat fixed;
				background-size: 100% auto;
			<?php if ( $background_color ) : ?>
				background-color: <?php echo $background_color; ?>;
			<?php endif; ?>
			}
			<?php if ( $link && ! $is_amp ) : ?>
				<?php
					/**
					 * We should not use links and other tags that should have cursor: pointer as direct children of the $selector.
					 * That is, we need a nested container (e.g. body > div > a) to make it work correctly.
					 */
				?>
				<?php echo $selector; ?> { cursor: pointer; }
				<?php echo $selector; ?> > * { cursor: default; }
			<?php endif; ?>
		</style>
		<?php
		/**
		 * Don't load any javascript on amp.
		 * Javascript output can be prevented by disabling click tracking and empty url field on ad level.
		 */
		if ( ! $is_amp ) :
			?>
			<script>
				( window.advanced_ads_ready || document.readyState === 'complete' ).call( null, function () {
					// Remove all existing click event listeners and recreate the controller.
					abort_controller.abort();
					abort_controller = new AbortController();
					document.querySelector( '<?php echo esc_attr( $selector ); ?>' ).classList.add( '<?php echo esc_attr( $class ); ?>' );
					<?php if ( $link ) : ?>
					// Use event delegation because $selector may be not in the DOM yet.
					document.addEventListener( 'click', function ( e ) {
						if ( e.target.matches( '<?php echo $selector; ?>' ) ) {
							<?php
							$script = '';
							/**
							 * Add additional script output.
							 *
							 * @param string          $script The URL.
							 * @param Advanced_Ads_Ad $ad     The current ad object.
							 */
							$script = (string) apply_filters( 'advanced-ads-pro-background-click-matches-script', $script, $ad );
							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- this is our own JS code, escaping will break it
							echo $script;
							?>
							// Open url in new tab.
							window.open( '<?php echo esc_url( $link ); ?>', '<?php echo esc_attr( $target ); ?>' );
						}
					}, { signal: abort_controller.signal } );
					<?php endif; ?>
				} );
			</script>
		<?php endif; ?>
		<?php

		// add content of Custom Code option here since the normal hook can’t be used.
		$output_options = $ad->options( 'output' );

		if ( ! empty( $output_options['custom-code'] ) ) {
			echo $output_options['custom-code'];
		}

		return ob_get_clean();
	}
}
