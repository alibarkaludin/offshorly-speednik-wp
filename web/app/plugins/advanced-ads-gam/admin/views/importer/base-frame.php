<?php
/**
 * Ad importer modal frame
 */

$importable_count = Advanced_Ads_Gam_Importer::get_instance()->has_importable();
$description      = __( 'You can choose which ad units to import.', 'advanced-ads-gam' );

if ( $importable_count !== 0 ) {
	/* translators: amount of ads that can be imported from the Google account */
	$description_count = sprintf( _n( '%d ad unit is not yet imported.', '%d ad units are not yet imported.', $importable_count, 'advanced-ads-gam' ), number_format_i18n( $importable_count ) );
	$description       = $description_count . ' ' . $description;

	if ( $importable_count === 1 ) {
		$description = __( 'One ad unit is not yet imported', 'advanced-ads-gam' );
	}
}

?>
<br />
<p><label for="gam-open-importer"><strong><?php esc_attr_e( 'Import ad units from your account', 'advanced-ads-gam' ); ?></strong></label></p>
<p class="description"><?php echo esc_html( $description ); ?></p>
<a href="#modal-gam-import" id="gam-open-importer" data-import-text="<?php esc_attr_e( 'Import', 'advanced-ads-gam' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'gam-importer' ) ); ?>" class="button"><?php esc_html_e( 'Import', 'advanced-ads-gam' ); ?></a>
<?php

new Advanced_Ads_Modal( [
	'modal_slug'   => 'gam-import',
	'modal_content' => '<p class="centered"><img alt="..." src="' . esc_url( ADVADS_BASE_URL . 'admin/assets/img/loader.gif' ) . '" /></p>',
] );
