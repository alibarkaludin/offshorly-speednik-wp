( function ( $ ) {
	'use strict';

	var END_TH = null;

	/**
	 * Get the select box with all sizes (that does not exist yet at the instant of the click).
	 */
	function predefSizesSelectMarkup() {
		const sizes = advadsGamPredefinedSizes;
		let markup  = '<select id="advads-gam-predef-sizes-select">';

		let existingSizes = [];
		$( '.advads-gam-ad-sizes-table-container thead th' )
			.not( ':first' ).not( ':last' ).map( function () {
			existingSizes.push( $( this ).attr( 'data-size' ) );
		} );

		for ( var i in sizes ) {
			if ( existingSizes.indexOf( i ) === - 1 ) {
				markup += '<option value="' + i + '">' + sizes[i] + '</option>';
			}
		}

		markup += '</select>';
		return markup;
	}

	// Click on the "Add size" icon.
	$( document ).on( 'click', '.advads-gam-ad-sizes-table-container .advads-add-predefsize-column', function () {
		if ( END_TH === null && $( this ).parents( 'thead' ).length ) {
			END_TH = $( this ).parent().html();
		}
		const selectMarkup = predefSizesSelectMarkup();
		const originalMk64 = btoa( $( this ).parent().html() );
		const cancelIcon   = '<span class="dashicons dashicons-dismiss cancel-predefsize" title="' + gamAdvancedAdsJS.i18n.cancel + '" data-revertmk64="' + originalMk64 + '"></span>';
		$( this ).replaceWith( $( selectMarkup + cancelIcon ) );
	} );

	// Cancel adding size.
	$( document ).on( 'click', '.cancel-predefsize', function () {
		const originMk = atob( $( this ).attr( 'data-revertmk64' ) );
		$( this ).parent().html( originMk );
	} );

	// Add new size.
	$( document ).on( 'change', '#advads-gam-predef-sizes-select', function () {
		let newSize = $( this ).val();
		if ( newSize === '' ) {
			return;
		}
		let currentSizes = [];
		$( '.advads-gam-ad-sizes-table-container table thead th' ).each( function () {
			if ( $( this ).attr( 'data-size' ) ) {
				currentSizes.push( $( this ).attr( 'data-size' ) );
			}
		} );
		currentSizes.push( newSize );

		const adSizes = window.AdvancedAdsGamGetAdSizes();
		adSizes.sortByWidth( currentSizes );

		// Get HTML for a single table header.
		const headHtml = ( wp.template( 'gam-ad-sizes-table-header' ) )( {size: newSize} );

		// Insert the new cells in the index according to the place of size in the currentSizes array.
		$( headHtml ).insertAfter( '.advads-gam-ad-sizes-table-container thead th:nth-of-type(' + ( currentSizes.indexOf( newSize ) + 1 ) + ')' );

		// Insert new size in footer (AMP).
		if ( $( '.advads-gam-ad-sizes-table-container tfoot' ).length ) {
			const footerClone = $( ( wp.template( 'gam-ad-sizes-table-footer' ) )( {size: newSize} ) );
			if ( newSize === 'fluid' ) {
				footerClone.append( '<strong>*</strong>' );
			}
			footerClone.insertAfter( '.advads-gam-ad-sizes-table-container tfoot th:nth-of-type(' + ( currentSizes.indexOf( newSize ) + 1 ) + ')' );
		}
		$( '.advads-gam-ad-sizes-table-container tbody tr' ).each( function () {
			const cellHtml = ( wp.template( 'gam-ad-sizes-table-cell' ) )( {width: $( this ).find( '.screen-width-input' ).val(), size: newSize} );
			$( cellHtml ).insertAfter( $( this ).find( 'td:nth-of-type(' + ( currentSizes.indexOf( newSize ) + 1 ) + ')' ) );
		} );

		// Close the size select input.
		$( '.cancel-predefsize' ).trigger( 'click' );

		adSizes.checkOriginalSizes();
		adSizes.checkLastSize();

		if ( advads_gam_amp.hasAMP && newSize === 'fluid' ) {
			$( '#advads-amp-fluid-notice' ).show();
		}

	} );

	// Remove a size
	$( document ).on( 'click', '.advads-remove-size-column', function () {
		// Do not remove if it's the last size, and the ad originally has at least one size.
		if ( $( this ).closest( 'table' ).find( 'thead th' ).length === 3 && window.AdvancedAdsGamGetAdSizes().getAdUnitSizes( window.AAGAM.getAdData(), true ).length ) {
			return;
		}

		if ( $( this ).closest( 'th' ).attr( 'data-size' ) === 'fluid' ) {
			$( '#advads-amp-fluid-notice' ).hide();
		}

		// Normal screen icon.
		let index = $( this ).closest( 'th' ).index();

		// Small screen (Responsive tables)
		if ( index === - 1 ) {
			index = $( this ).closest( 'td' ).index();
		}
		index ++;
		$( '.advads-gam-ad-sizes-table-container table thead th:nth-child(' + index +
			'),.advads-gam-ad-sizes-table-container table tbody td:nth-child(' + index +
			'),.advads-gam-ad-sizes-table-container table tfoot th:nth-child(' + index + ')' ).remove();

		const adSizes = window.AdvancedAdsGamGetAdSizes();
		adSizes.checkOriginalSizes();
		adSizes.checkLastSize();
	} );

	// Revert ad sizes to the ones in post content.
	$( document ).on( 'click', '#advads-gam-sizes-reset', function ( ev ) {
		ev.preventDefault();
		const adSizes = window.AdvancedAdsGamGetAdSizes();
		adSizes.loadAdSizes( adSizes.getAdUnitSizes( window.AAGAM.getAdData(), true ), true );
	} );

} )( window.jQuery );
