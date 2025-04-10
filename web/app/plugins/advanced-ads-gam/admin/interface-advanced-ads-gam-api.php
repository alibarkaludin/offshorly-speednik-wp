<?php

/**
 * Handle all communication with Google Ad Manager servers.
 */
interface Advanced_Ads_Gam_Api {
	/**
	 * Get a list of all network associated with the newly obtained token data
	 *
	 * @param array $token_data All token data.
	 *
	 * @return array
	 */
	public function get_all_networks( $token_data );

	/**
	 * Test if API access is enabled on the account. Stores the ad units list in the database if there are less than Advanced_Ads_Gam_Importer::AD_COUNT_LIMIT ads
	 *
	 * @param string $root_ad_unit the account's root ad unit (to be excluded from the list).
	 * @param string $network_code current connected network code.
	 *
	 * @return array
	 */
	public function test_the_api( $root_ad_unit, $network_code );

	/**
	 * Get all ad units in the current network
	 *
	 * @return array
	 */
	public function get_ad_units();

	/**
	 * Get ad units by name or id
	 *
	 * @param string $what  Property on which we do the search (name or id).
	 * @param string $value value of "$what".
	 *
	 * @return array
	 */
	public function get_ads_by( $what, $value );
}
