<?php
/**
 * Ad Manager ID setting
 */

$has_token     = Advanced_Ads_Network_Gam::get_instance()->is_account_connected();
$gam_option    = Advanced_Ads_Network_Gam::get_option();
$connect_nonce = wp_create_nonce( 'gam-connect' );
$has_soap      = Advanced_Ads_Gam_Admin::has_soap();
$has_soap_key  = empty( get_option( AAGAM_API_KEY_OPTION ) );
$soap_class    = ! $has_soap && $has_soap_key ? 'nosoapkey' : '';

?>
<div>
	<?php if ( $has_token ) : ?>
		<button class="button-secondary preventDefault"
				id="advads-gam-revoke"><?php esc_html_e( 'Revoke access', 'advanced-ads-gam' ); ?></button>
		<p class="desciption">
			<?php
			printf(
				'<code>[%1$s]%2$s</code>',
				$gam_option['account']['networkCode'],
				$gam_option['account']['isTest'] ? '(' . esc_html__( 'Test account', 'advanced-ads-gam' ) . ')' : ''
			);
			?>
			<strong><?php echo esc_html( $gam_option['account']['displayName'] ); ?></strong>
		</p>
	<?php else : ?>
		<?php if ( Advanced_Ads_Gam_Admin::has_valid_license() ) : ?>
		<button class="preventDefault button-primary <?php echo esc_attr( $soap_class ); ?>" data-nonce="<?php echo esc_attr( $connect_nonce ); ?>"
				id="advads-gam-connect"><?php esc_html_e( 'Connect account', 'advanced-ads-gam' ); ?></button>
		<p class="description"><?php esc_html_e( 'Connect your Google Ad Manager account', 'advanced-ads-gam' ); ?></p>
		<p class="description">
			<?php esc_html_e( 'Please make sure that the API in your GAM account is enabled.', 'advanced-ads-gam' ); ?>
			<a href="<?php echo esc_url( ADVADS_URL ); ?>manual/google-ad-manager-integration-manual/?utm_source=advanced-ads&utm_medium=link&utm_campaign=gam-manual-api#Enable_the_API_in_Google_Ad_Manager" target="_blank" class="advads-manual-link">
				<?php esc_html_e( 'Manual', 'advanced-ads-gam' ); ?>
			</a>
		</p>
		<?php else : ?>
			<button class="preventDefault button disabled"><?php esc_html_e( 'Connect account', 'advanced-ads-gam' ); ?></button>
			<div>
				<p class="card advads-notice-inline advads-error">
					<?php
					printf(
					/* translators: 1: link to License tab, 2: closing anchor tag */
						esc_html__(
							'Please activate %1$syour license%2$s to connect your account.',
							'advanced-ads-gam'
						),
						'<a href="' . esc_url( admin_url( 'admin.php?page=advanced-ads-settings#top#licenses' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php if ( $has_token ) : ?>
	<?php
	// ad units list is empty, check if we can get it (API is enabled).
	if ( empty( $gam_option['ad_units'] ) ) {
		echo '<input type="hidden" value="' . esc_attr( $connect_nonce ) . '" id="gamlistisempty" />';
	}
	?>
	<div id="gamapi-not-enabled">
		<p class="card advads-notice-inline advads-error">
			<?php
			printf(
				/* translators: link to manual page */
				esc_html__( 'Please enable the API option in your Ad Manager account and reload this page. Manual %s', 'advanced-ads-gam' ),
				'<a href="' . esc_url( ADVADS_URL ) . 'manual/google-ad-manager-integration-manual/#Enable_the_API_in_GAM" class="dashicons dashicons-external"></a>'
			);
			?>
		</p>
	</div>
	<?php
	if ( ! Advanced_Ads_Gam_Admin::has_valid_license() ) {
		echo '<div><p class="card advads-notice-inline advads-error">';
		printf(
		/* translators: 1: link to License tab, 2: closing anchor tag */
			esc_html__(
				'Please re-activate %1$syour license%2$s to update the ad unit list.',
				'advanced-ads-gam'
			),
			'<a href="' . admin_url( 'admin.php?page=advanced-ads-settings#top#licenses' ) . '">',
			'</a>'
		);
	}
	echo '</p></div>';
	Advanced_Ads_Gam_Importer::import_button();
	?>
<?php endif; ?>
<div id="gam-settings-overlay"><div></div></div>
