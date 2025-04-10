<script type="text/html" id="tmpl-gam-ad-sizes-table">
	<table class="widefat striped advads-option-table advads-option-table-responsive">
		<thead>
		<tr>
			<th><?php esc_html_e( 'min. screen width', 'advanced-ads-gam' ); ?></th>
			<# _.each( data.header, function( size, index ) { #>
			<th class="advads-option-buttons" data-size="{{size}}">
				{{size}}
				<# if ( data.originalSizes.indexOf( size ) === -1 ) { #>
				<span class="dashicons dashicons-remove advads-remove-size-column" title="<?php esc_attr_e( 'remove size', 'advanced-ads-gam' ); ?>"></span>
				<# } #>
			</th>
			<# }) #>
			<th class="advads-option-buttons">
				<span class="dashicons dashicons-plus advads-add-predefsize-column" title="<?php esc_html_e( 'new ad size', 'advanced-ads-gam' ); ?>"></span>
			</th>
		</tr>
		</thead>
		<tbody>
		</tbody>
	</table>

	<p class="description" id="advads-amp-fluid-notice" style="{{{data.fluidNoticeStyle}}}">* <?php esc_html_e( 'Ad units with the fluid size selected on AMP pages only work when placed below the fold.', 'advanced-ads-gam' ); ?></p>
	<p><a href="#" id="advads-gam-sizes-reset"><?php esc_html_e( 'Reset ad sizes', 'advanced-ads-gam' ); ?></a></p>
</script>
