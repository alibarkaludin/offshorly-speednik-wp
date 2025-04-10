<script type="text/html" id="tmpl-advads-async-errors">
	<div class="error <# print( data.inline ? 'notice-error' : 'card advads-notice-block' ); #>">
		<# if ( typeof data.header === 'string' ) { #>
			<h5>{{{data.header}}}</h5>
		<# } #>
		<p>{{{data.body}}}</p>
	</div>
</script>
