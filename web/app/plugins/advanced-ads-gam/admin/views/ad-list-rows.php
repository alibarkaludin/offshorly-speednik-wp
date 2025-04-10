<script type="text/html" id="tmpl-gam-ad-list-rows">
	<?php $disabled = Advanced_Ads_Gam_Admin::has_valid_license() ? '' : 'disabled'; ?>
	<# if ( data.length ) { #>
		<# _.each( data, function( unit ) { #>
			<tr data-unitid="{{gamAdvancedAdsJS.networkCode}}_{{unit.id}}" data-unitdata="{{{window.AAGAM.base64Encode(JSON.stringify(unit,false,false))}}}">
				<td>{{unit.name}}</td>
				<td>{{unit.description}}</td>
				<td>{{unit.adUnitCode}}</td>
				<td>
					<p class="alignright">
						<i class="dashicons dashicons-update <?php echo esc_attr( $disabled ); ?>" title="<?php esc_attr_e( 'Update ad unit', 'advanced-ads-gam' ); ?>"></i>
						<i class="dashicons dashicons-remove <?php echo esc_attr( $disabled ); ?>" title="<?php esc_attr_e( 'Remove ad unit', 'advanced-ads-gam' ); ?>"></i>
					</p>
				</td>
			</tr>
		<# }) #>
	<# } else { #>
		<tr>
			<td colspan="4">
				<p id="advads-gam-primary-search-button" class="description text-center">
					<a href="#modal-gam-ad-search" class="button button-primary <?php echo esc_attr( $disabled ); ?>"><i class="dashicons dashicons-search"></i><?php esc_html_e( 'Search ad units', 'advanced-ads-gam' ); ?></a>
				</p>
			</td>
		</tr>
	<# } #>
</script>
