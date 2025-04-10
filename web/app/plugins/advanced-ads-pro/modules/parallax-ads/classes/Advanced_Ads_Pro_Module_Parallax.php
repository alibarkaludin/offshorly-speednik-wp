<?php

/**
 * Parallax class.
 */
class Advanced_Ads_Pro_Module_Parallax {
	/**
	 * Default values for options.
	 *
	 * @const array
	 */
	private const DEFAULT_VALUES = [
		'enabled' => null,
		'height'  => [
			'value' => 200,
			'unit'  => 'px',
		],
	];

	/**
	 * Allow parallax ads on placement types.
	 * Filterable, and then cached in this property.
	 *
	 * @var string[]
	 */
	private $allowed_placement_types;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'advanced-ads-placement-types', [ $this, 'show_options_on_placement' ] );
	}

	/**
	 * Iterate all placement types and add parallax options to allowed placements.
	 *
	 * @param iterable $placement_types Array of placement types array.
	 *
	 * @return iterable
	 */
	public function show_options_on_placement( iterable $placement_types ): iterable {
		foreach ( $this->get_allowed_placement_types() as $placement_type ) {
			if ( array_key_exists( $placement_type, $placement_types ) ) {
				$placement_types[ $placement_type ]['options']['show_parallax'] = true;
			}
		}

		return $placement_types;
	}

	/**
	 * Iterate through all placements to see if there is one that can have the parallax effect.
	 * If found return as early as possible.
	 *
	 * @return bool
	 */
	public function enabled_placement_exists(): bool {
		foreach ( Advanced_Ads::get_instance()->get_model()->get_ad_placements_array() as $placement ) {
			if ( in_array( $placement['type'], $this->get_allowed_placement_types(), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether parallax is allowed on a specific placement type.
	 *
	 * @param string $placement_type The placement type to check against.
	 *
	 * @return bool
	 */
	public function allowed_on_placement( string $placement_type ): bool {
		return in_array( $placement_type, $this->get_allowed_placement_types(), true );
	}

	/**
	 * Return the default values for the parallax option.
	 *
	 * @return array
	 */
	public function get_default_option_values(): array {
		return self::DEFAULT_VALUES;
	}

	/**
	 * Get and filter the allowed placement types for the parallax option.
	 *
	 * @return array
	 */
	private function get_allowed_placement_types(): array {
		if ( ! isset( $this->allowed_placement_types ) ) {
			$allowed_placement_types = [
				'post_content',
			];

			/**
			 * Filter the allowed placement types, to allow the parallax option there.
			 *
			 * @param string[] $allowed_placement_types Array of placement type identifiers.
			 */
			$this->allowed_placement_types = apply_filters( 'advanced-ads-pro-parallax-allowed-placement-types', $allowed_placement_types );
			if ( ! is_array( $this->allowed_placement_types ) ) {
				$this->allowed_placement_types = [];
			}
		}

		return $this->allowed_placement_types;
	}
}
