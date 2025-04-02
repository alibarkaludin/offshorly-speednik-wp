<?php
/**
 * Class Json Manifest
 *
 * @package WordPress
 * @subpackage Timber
 */

/**
 * Copied this code from Sage for getting assets from manifest.json.
 */
class Starter_Theme_Json_Manifest {

	/**
	 * Manifest Variable Declaration
	 *
	 * @var string
	 */
	private $manifest;

	/**
	 * Class Constructor
	 *
	 * @param string $manifest_path - string path for manifest.
	 */
	public function __construct( $manifest_path ) {
		if ( file_exists( $manifest_path ) ) {
			$this->manifest = json_decode( file_get_contents( $manifest_path ), true ); //phpcs:ignore
		} else {
			$this->manifest = array();
		}
	}

	/**
	 * Function Getter
	 *
	 * @return $manifest
	 */
	public function get() {
		return $this->manifest;
	}

	/**
	 * Function Get Path
	 *
	 * @param string $key - string key.
	 * @param string $default - string/null.
	 * @return $collection
	 */
	// phpcs:ignore
	public function get_ath( $key = '', $default = null ) {

		$collection = $this->manifest;

		if ( is_null( $key ) ) {
			return $collection;
		}

		if ( isset( $collection[ $key ] ) ) {
			return $collection[ $key ];
		}

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! isset( $collection[ $segment ] ) ) {
				return $default;
			} else {
				$collection = $collection[ $segment ];
			}
		}

		return $collection;
	}
}
