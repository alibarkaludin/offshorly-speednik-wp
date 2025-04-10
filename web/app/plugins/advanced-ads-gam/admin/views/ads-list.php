<?php
/**
 * Ad unit list in the ad parameters meta box
 */

global $post;
$network       = Advanced_Ads_Network_Gam::get_instance();
$ads_list      = $network->get_external_ad_units();
$gam_option    = Advanced_Ads_Network_Gam::get_option();
$ad_unit_data  = [];
$valid_license = Advanced_Ads_Gam_Admin::has_valid_license();

if ( $post && $post->post_content ) {
	$ad_unit_data = $network->post_content_to_adunit( $post->post_content );
}

?>

<?php if ( ! $network->is_account_connected() ) : ?>
	<?php if ( ! empty( $ad_unit_data ) ) : ?>
		<span class="label"><?php esc_html_e( 'Ad unit', 'advanced-ads-gam' ); ?></span>
		<div id="advads-gam-ad-units">
			<div id="current-ad-unit"></div>
			<table id="advads-gam-table" class="widefat striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'advanced-ads-gam' ); ?></th>
					<?php if ( ! empty( $ad_unit_data['description'] ) ) : ?>
						<th><?php esc_html_e( 'Description', 'advanced-ads-gam' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( 'Ad Unit Code', 'advanced-ads-gam' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td>
						<a href="<?php echo esc_url( 'https://admanager.google.com/' . $ad_unit_data['networkCode'] . '#inventory/ad_unit/detail/ad_unit_id=' . $ad_unit_data['id'] ); ?>" target="_blank" class="advads-external-link">
							<?php echo esc_html( $ad_unit_data['name'] ); ?>
						</a>
					</td>
					<?php if ( $ad_unit_data['description'] ) : ?>
						<td><?php echo esc_html( $ad_unit_data['description'] ); ?></td>
					<?php endif; ?>
					<td><code><?php echo esc_html( $ad_unit_data['adUnitCode'] ); ?></code></td>
				</tr>
				</tbody>
			</table>
			<div class="card advads-notice-block advads-card-full-width advads-error">
				<?php if ( ! $valid_license ) : ?>
					<p>
						<?php
						printf(
						// translators: link to license tab of the settings page.
							esc_html__( 'Please activate %s to connect your account.', 'advanced-ads-gam' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=advanced-ads-settings#top#licenses' ) ) . '">' . esc_html__( 'your license', 'advanced-ads-gam' ) . '</a>'
						);
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'You need to connect to a Google Ad Manager account', 'advanced-ads-gam' ); ?></p>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=advanced-ads-settings#top#gam' ) ); ?>" class="button-primary"><?php esc_html_e( 'Connect', 'advanced-ads-gam' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<hr>
	<?php else : ?>
		<span class="label"><?php esc_html_e( 'Ad unit', 'advanced-ads-gam' ); ?></span>
		<div id="advads-gam-ad-units">
			<div class="card advads-notice-block advads-card-full-width advads-error">
				<p><?php esc_html_e( 'You need to connect to a Google Ad Manager account', 'advanced-ads-gam' ); ?></p>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=advanced-ads-settings#top#gam' ) ); ?>" class="button-primary"><?php esc_html_e( 'Connect', 'advanced-ads-gam' ); ?></a></p>
			</div>
		</div>
		<hr>
	<?php endif; ?>
<?php else : ?>
	<span class="label"><?php esc_html_e( 'Ad unit', 'advanced-ads-gam' ); ?></span>
	<div id="advads-gam-ad-units">
		<div id="current-ad-unit"></div>
		<?php if ( ! $valid_license ) : ?>
			<div class="card advads-notice-block advads-error">
				<p>
					<?php
					printf(
					// translators: 1: opening anchor tag. 2: closing tag
						esc_html__( 'Please activate %1$syour license%2$s to connect your account.', 'advanced-ads-gam' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=advanced-ads-settings#top#licenses' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>
		<div id="advads-gam-current-unit-updated" class="card advads-notice-block advads-card-full-width advads-error hidden">
			<p><?php esc_html_e( 'The selected ad unit has changed in your GAM account. Please re-save this ad to apply the new changes.', 'advanced-ads-gam' ); ?></p>
			<p>
				<button class="button-primary"><?php esc_html_e( 'Update ad', 'advanced-ads-gam' ); ?></button>
			</p>
		</div>
		<?php if ( isset( $ad_unit_data['networkCode'] ) && $ad_unit_data['networkCode'] !== $gam_option['account']['networkCode'] ) : ?>
			<div class="card advads-notice-block advads-idea" id="advads-gam-netcode-mismatch">
				<h3><?php esc_html_e( 'The selected ad is not from the currently connected account. You can still use it though.', 'advanced-ads-gam' ); ?></h3>
				<p>
					<strong><?php esc_html_e( 'Network code', 'advanced-ads-gam' ); ?>:</strong>&nbsp;<code><?php echo esc_html( $ad_unit_data['networkCode'] ); ?></code>
					<strong><?php esc_html_e( 'Ad unit name', 'advanced-ads-gam' ); ?>:</strong>&nbsp;<code><?php echo esc_html( $ad_unit_data['name'] ); ?></code>
				</p>
			</div>
		<?php endif; ?>
	</div>
	<hr>
<?php endif; ?>
<script type="text/html" id="tmpl-gam-adlist-overlay">
	<div id="gam-adlist-overlay">
		<div>
			<div><p><img alt="" src="<?php echo esc_url( ADVADS_BASE_URL . 'admin/assets/img/loader.gif' ); ?>"/></p></div>
		</div>
	</div>
</script>
<script type="text/html" id="tmpl-gam-unitlist-header">
	<a href="#close" class="advads-modal-close" title="<?php esc_attr_e( 'Cancel', 'advanced-ads-gam' ); ?>>">×</a>
</script>
<script type="text/html" id="tmpl-gam-unitlist-footer">
	<div class="tablenav bottom">
		<a href="#close" type="button" title="<?php esc_attr_e( 'Close', 'advanced-ads-gam' ); ?>" class="button button-secondary advads-modal-close">
			<?php esc_html_e( 'Close', 'advanced-ads-gam' ); ?>
		</a>
	</div>
</script>
<script type="text/html" id="tmpl-gam-unitlist-buttons">
	<a href="#" class="button <# print( data.has_unit_data ? '' : 'button-primary' ); #>" id="show-gam-ad-list">
		<i class="dashicons dashicons-search"></i>
		<# if ( data.has_unit_data ) { #>
		<?php esc_html_e( 'Select a different ad unit', 'advanced-ads-gam' ); ?>
		<# } else { #>
		<?php esc_html_e( 'Select an ad unit', 'advanced-ads-gam' ); ?>
		<# } #>
	</a>
	<# if ( data.has_unit_data ) { #>
	<a href="#" id="refresh-current-unit" class="button <# print ( data.valid_license === 'yes' ? '' : 'disabled' ); #>">
		<i class="dashicons dashicons-update"></i>
		<?php esc_html_e( 'Refresh ad unit data', 'advanced-ads-gam' ); ?>
	</a>
	<# } #>
</script>
<script type="text/html" id="tmpl-gam-unitlist-body">
	<table id="advads-gam-table" class="widefat striped">
		<thead>
		<tr>
			<th><?php esc_html_e( 'Name', 'advanced-ads-gam' ); ?></th>
			<th><?php esc_html_e( 'Description', 'advanced-ads-gam' ); ?></th>
			<th><?php esc_html_e( 'Ad Unit Code', 'advanced-ads-gam' ); ?></th>
			<th>
				<a href="#" class="advads-gam-open-ad-search button alignright <# print( data.valid_license === 'yes' ? '' : 'disabled' ); #>">
					<i class="dashicons dashicons-search"></i><span>Search ad units</span>
				</a>
			</th>
		</tr>
		</thead>
		<tbody>
		<# for ( const ad of data.ad_list ) { #>
		<#
		const selected = data.current.id === ad.id && data.current.networkCode === ad.networkCode ? 'selected' : '';
		const newAd = data.new_ads.indexOf( ad.networkCode + '_' + ad.id ) !== -1 ? ' new' : '';
		#>
		<tr data-unitid="{{ad.networkCode}}_{{ad.id}}"
			data-unitdata="{{window.AdvancedAdsGamGetAdList().encodeUnitData(ad)}}" class="<# print(selected + newAd); #>">
			<td>
				<# if ( newAd !== '' ) { #>
				<i class="dashicons dashicons-star-filled"></i>
				<# } #>
				{{ad.name}}
			</td>
			<td>{{ad.description}}</td>
			<td>{{ad.adUnitCode}}</td>
			<td>
				<p class="alignright">
					<i class="dashicons dashicons-remove <# print( data.valid_license === 'yes' ? '' : 'disabled' ); #>" title="<?php esc_attr_e( 'Remove from available ad units', 'advanced-ads-gam' ); ?>"></i>
				</p>
			</td>
		</tr>
		<# } #>
		</tbody>
	</table>
</script>
<script type="text/html" id="tmpl-gam-current-unit">
	<table class="widefat striped">
		<thead>
		<tr>
			<th><?php esc_html_e( 'Name', 'advanced-ads-gam' ); ?></th>
			<# if ( data.unit.description.length ) { #>
			<th><?php esc_html_e( 'Description', 'advanced-ads-gam' ); ?></th>
			<# } #>
			<th><?php esc_html_e( 'Ad Unit Code', 'advanced-ads-gam' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<td>
				<input type="hidden" id="current-unit-internal-id" value="{{data.unit.networkCode}}_{{data.unit.id}}">
				<a href="https://admanager.google.com/{{data.unit.networkCode}}#inventory/ad_unit/detail/ad_unit_id={{data.unit.id}}" target="_blank" class="advads-external-link">
					{{data.unit.name}}
				</a>
			</td>
			<# if ( data.unit.description.length ) { #>
			<td>{{data.unit.description}}</td>
			<# } #>
			<td><code>{{data.unit.adUnitCode}}</code></td>
		</tr>
		</tbody>
	</table>
</script>
<script type="text/html" id="gam-adlist-nonce"><?php echo esc_html( wp_create_nonce( 'gam-ad-list' ) ); ?></script>
<script type="text/html" id="gam-adlist-onparamloaded-current"><?php echo rawurlencode( wp_json_encode( $ad_unit_data ) ); ?></script>
<div id="advads-gam-ads-list-overlay">
	<div>
		<div>
			<div><img alt="loading" src="<?php echo esc_url( AAGAM_BASE_URL . 'admin/img/loader.gif' ); ?>"/></div>
		</div>
	</div>
</div>
