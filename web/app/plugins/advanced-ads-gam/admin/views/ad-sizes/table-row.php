<script type="text/html" id="tmpl-gam-ad-sizes-table-row">
	<tr>
		<td class="advads-ad-parameters-option-list-min-width" data-th="<?php esc_html_e( 'min. screen width', 'advanced-ads-gam' ); ?>">
			<# if ( parseInt(data.screenWidth) === 0 ) { #>
			<input type="hidden" class="screen-width-input" name="advanced_ad[output][ad-sizes][0][width]" value="0"/>
			{{window.advadsGamSizesI18n.default}}
			<# } else { #>
			<input type="number" class="screen-width-input" min="0" max="3000" name="advanced_ad[output][ad-sizes][{{data.screenWidth}}][width]" value="{{data.screenWidth}}"/>px
			<# } #>
		</td>
		<# _.each( data.row, function( checked, size ) { #>
		<td data-th="{{size}}" class="advads-option-buttons">
			<input type="checkbox" name="advanced_ad[output][ad-sizes][{{data.screenWidth}}][sizes][]" value="{{size}}" <# if( checked ) { #> checked="checked" <# } #>/>
			<# if ( data.originalSizes.indexOf( size ) === -1 ) { #>
			<span class="dashicons dashicons-remove advads-remove-size-column advads-hide-above-medium-screen" title="<?php esc_html_e( 'remove size', 'advanced-ads-gam' ); ?>"></span>
			<# } #>
		</td>
		<# }) #>
		<td class="advads-option-buttons">
			<span class="dashicons dashicons-plus advads-add-predefsize-column advads-hide-above-medium-screen" title="<?php esc_html_e( 'new ad size', 'advanced-ads-gam' ); ?>"></span>
			<span class="dashicons dashicons-plus advads-row-new" title="<?php esc_html_e( 'add screen width', 'advanced-ads-gam' ); ?>"></span>
			<span class="dashicons dashicons-trash advads-tr-remove" title="<?php esc_html_e( 'delete', 'advanced-ads-gam' ); ?>"></span>
			<span class="advads-loader hidden"></span>
		</td>
	</tr>
</script>
