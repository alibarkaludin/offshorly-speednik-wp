<?php $search_nonce = wp_create_nonce( 'gam-ad-search' ); ?>
<input type="hidden" id="gam-ad-search-nonce" value="<?php echo esc_attr( $search_nonce ); ?>"/>
<script type="text/html" id="tmpl-gam-ad-search-head">
	<div>
		<a href="#close" class="advads-modal-close">×</a>
		<form action="" method="post" id="advads-gam-search">
			<p><label for="advads-gam-search-input"><?php esc_html_e( 'Enter an ad unit name from your Google Ad Manager account', 'advanced-ads-gam' ); ?></label></p>
			<p>
				<input type="search" value="" id="advads-gam-search-input"/>
				<button class="button button-primary" id="advads-gam-search-button" disabled><?php esc_html_e( 'Search', 'advanced-ads-gam' ); ?></button>
			</p>
		</form>
	</div>
</script>
<script type="text/html" id="tmpl-gam-ad-search-results">
	<form id="gam-search-import">
		<table class="widefat striped">
			<thead>
			<tr>
				<th></th>
				<th><?php esc_attr_e( 'Name', 'advanced-ads-gam' ); ?></th>
				<th><?php esc_attr_e( 'Description', 'advanced-ads-gam' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<# if ( data.units.length === 0 ) { #>
				<tr>
					<td colspan="3"><?php esc_attr_e( 'No ad found matching that name', 'advanced-ads-gam' ); ?></td>
				</tr>
			<# } else { #>
				<# for ( const unit of data.units ) { #>
				<tr>
					<td>
						<# if ( unit.inList ) { #>
						<i class="dashicons dashicons-saved"></i>
						<# } else { #>
						<label><input type="checkbox" value="{{unit.data}}" data-id="{{window.gamAdvancedAdsJS.networkCode}}_{{unit.id}}" name="gam-unit-found[]"/></label>
						<# } #>
					</td>
					<td>{{unit.name}}</td>
					<td>{{unit.description}}</td>
				</tr>
				<# } #>
			<# } #>
			</tbody>
		</table>
	</form>
</script>
<script type="text/html" id="tmpl-gam-ad-search-footer">
	<# if ( data.action === 'imported' ) { #>
	<div class="tablenav bottom">
		<span><?php esc_html_e( 'The listed ad units are already imported.', 'advanced-ads-gam' ); ?></span>
		<a href="#close" type="button" title="Close" class="button button-secondary advads-modal-close"><?php esc_html_e( 'Close', 'advanced-ads-gam' ); ?></a>
	</div>
	<# } else if ( data.action === 'load' ) { #>
	<div class="tablenav bottom">
		<a href="#close" type="button" title="Close" class="button button-secondary advads-modal-close"><?php esc_html_e( 'Close', 'advanced-ads-gam' ); ?></a>
		<button class="button-primary" id="gam-search-load-results"><?php esc_html_e( 'Load into ad list', 'advanced-ads-gam' ); ?></button>
	</div>
	<# } else { #>
	<div class="tablenav bottom">
		<a href="#close" type="button" title="Close" class="button button-secondary advads-modal-close"><?php esc_html_e( 'Close', 'advanced-ads-gam' ); ?></a>
	</div>
	<# } #>
</script>
<script type="text/html" id="tmpl-gam-ad-search-error">
	<div class="card advads-notice-block advads-error"><p>{{{data}}}</p></div>
</script>
