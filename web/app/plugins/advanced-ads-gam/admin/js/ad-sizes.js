( function ( $ ) {
	'use strict';

	let sizeInstance;

	const adSizes = function () {};

	adSizes.prototype = {
		constructor: adSizes,

		/**
		 * Hide the remove icon if there is only one size remaining, and the ad originally has at least one size.
		 */
		checkLastSize: function () {
			const removeSizeIcon = $( '.advads-gam-ad-sizes-table-container table .advads-remove-size-column' );
			if ( $( '.advads-gam-ad-sizes-table-container table thead th[data-size]' ).length === 1 && this.getAdUnitSizes( AAGAM.getAdData(), true ).length ) {
				removeSizeIcon.addClass( 'last-size' );
			} else {
				removeSizeIcon.removeClass( 'last-size' );
			}
		},

		/**
		 * Load sizes of the ad unit into an array
		 *
		 * @param {Object} adData ad unit to retrieve sizes from.
		 * @param {Boolean} [dataOnly=false] whether to use adData only or ad checkbox states.
		 * @returns {string[]}
		 */
		getAdUnitSizes: function ( adData, dataOnly ) {
			if ( typeof dataOnly === 'undefined' ) {
				dataOnly = false;
			}

			adData = this.appendFluidSize( adData );

			if ( ! adData || ! adData.adUnitSizes ) {
				if ( ! $.isEmptyObject( window.advads_gam_stored_ad_sizes_json ) && ! dataOnly ) {
					const sizes = [];
					for ( let i in window.advads_gam_stored_ad_sizes_json ) {
						if ( typeof window.advads_gam_stored_ad_sizes_json[i].sizes !== 'undefined' && typeof window.advads_gam_stored_ad_sizes_json[i].sizes.length !== 'undefined' ) {
							for ( let j in window.advads_gam_stored_ad_sizes_json[i].sizes ) {
								if ( sizes.indexOf( window.advads_gam_stored_ad_sizes_json[i].sizes[j] ) === - 1 ) {
									sizes.push( window.advads_gam_stored_ad_sizes_json[i].sizes[j] );
								}
							}
						}
					}
					this.checkOriginalSizes();
					return sizes;
				}
				return [];
			}

			let adUnitSizes = [];

			// Handle ad units with just one size and those with more than one
			if ( adData.adUnitSizes.fullDisplayString ) {
				adUnitSizes.push( adData.adUnitSizes.fullDisplayString );
			} else {
				adData.adUnitSizes.forEach(
					function ( size ) {
						if ( size.fullDisplayString ) {
							adUnitSizes.push( size.fullDisplayString );
						}
					}
				);
			}

			const originalSizes = JSON.parse( JSON.stringify( adUnitSizes ) );

			if ( dataOnly || window.location.href.indexOf( 'post-new.php' ) !== - 1 ) {
				return adUnitSizes;
			}

			let deletedSizes = $.isEmptyObject( window.advads_gam_stored_ad_sizes_json ) ? [] : JSON.parse( JSON.stringify( adUnitSizes ) );

			for ( let i in window.advads_gam_stored_ad_sizes_json ) {
				for ( let j in window.advads_gam_stored_ad_sizes_json[i].sizes ) {
					if ( adUnitSizes.indexOf( window.advads_gam_stored_ad_sizes_json[i].sizes[j] ) === - 1 ) {
						// Saved size not found in ad data - added manually.
						adUnitSizes.push( window.advads_gam_stored_ad_sizes_json[i].sizes[j] );
					}
					let index = deletedSizes.indexOf( window.advads_gam_stored_ad_sizes_json[i].sizes[j] );
					if ( index !== - 1 ) {
						deletedSizes.splice( index, 1 );
					}
				}
			}

			if ( deletedSizes.length !== 0 ) {
				for ( const size of deletedSizes ) {
					let index = adUnitSizes.indexOf( size );
					if ( index !== - 1 ) {
						adUnitSizes.splice( index, 1 );
					}
				}
			}

			if ( window.advads_gam_amp.hasAMP && typeof window.advads_gam_amp.sizes !== 'undefined' ) {
				for ( let i in window.advads_gam_amp.sizes ) {
					if ( adUnitSizes.indexOf( window.advads_gam_amp.sizes[i] ) === - 1 ) {
						adUnitSizes.push( window.advads_gam_amp.sizes[i] );
					}
				}
			}

			for ( const size of originalSizes ) {
				if ( adUnitSizes.indexOf( size ) === - 1 ) {
					adUnitSizes.push( size );
				}
			}

			this.checkOriginalSizes();
			this.sortByWidth( adUnitSizes );
			return adUnitSizes;
		},

		/**
		 * Append fluid size to regular sizes list of the ad data in a way that looks like Google's data. Creates that list if needed
		 *
		 * @param {Object} adData ad unit data
		 * @returns {Object}
		 */
		appendFluidSize: function ( adData ) {
			if ( ! adData ) {
				return;
			}

			if ( typeof adData.hasFluidSize !== 'undefined' ) {
				return adData;
			}

			if ( adData.isFluid ) {

				// The ad also have regular fixed size
				if ( adData.adUnitSizes ) {

					// Only one regular size
					if ( adData.adUnitSizes.fullDisplayString ) {
						const fullString = adData.adUnitSizes.fullDisplayString;
						// @formatter:off
						delete( adData.adUnitSizes.fullDisplayString );
						// @formatter:on
						adData.adUnitSizes = [
							{
								'size':              adData.adUnitSizes.size,
								'fullDisplayString': fullString
						},
							{
								'size':              {},
								'fullDisplayString': 'fluid'
						}
						];
					} else {

						// add 'fluid' to other regular sizes
						adData.adUnitSizes.push( {
							size:              {},
							fullDisplayString: 'fluid'
						} );
					}
				} else {

					// just fluid, no regular sizes
					adData.adUnitSizes = {
						size:              {},
						fullDisplayString: 'fluid'
					};
				}
				adData['hasFluidSize'] = true;
			}

			return adData;
		},

		/**
		 * Hide|show reset ad sizes link depending on the current sizes
		 */
		checkOriginalSizes: function () {
			const resetLink     = $( '#advads-gam-sizes-reset' );
			const originalSizes = this.getAdUnitSizes( AAGAM.getAdData(), true );
			let currentSizes    = [];

			$( '.advads-gam-ad-sizes-table-container thead th[data-size]' ).each( function () {
				currentSizes.push( $( this ).attr( 'data-size' ) );
			} );

			if ( currentSizes.length !== originalSizes.length ) {
				resetLink.show();
				return;
			}

			for ( const size of currentSizes ) {
				if ( originalSizes.indexOf( size ) === - 1 ) {
					resetLink.show();
					return;
				}
			}

			resetLink.hide();
		},

		/**
		 * Sort ad sizes array by width
		 *
		 * @param {String[]} sizeArray array of ad sizes to sort
		 * @returns {String[]}
		 */
		sortByWidth: function ( sizeArray ) {
			return sizeArray.sort( function ( a, b ) {
				if ( a.indexOf( 'x' ) === - 1 ) {
					return 1;
				}

				if ( b.indexOf( 'x' ) === - 1 ) {
					return - 1;
				}

				return parseInt( a.split( 'x' )[0] ) < parseInt( b.split( 'x' )[0] ) ? - 1 : + 1;
			} );
		},

		/**
		 * Generate a table for the Ad sizes parameter from the ad content
		 *
		 * @param {String} adUnitSizes array of ad sizes
		 * @param {Boolean} [useCurrentSizes=false] whether to use checkboxes to define the sizes
		 */
		loadAdSizes: function ( adUnitSizes, useCurrentSizes ) {
			if ( ! $( '#tmpl-gam-ad-sizes-table-footer' ).length ) {
				return;
			}

			if ( typeof useCurrentSizes === 'undefined' ) {
				useCurrentSizes = false;
			}

			const adSizesDiv = $( '#advads-gam-ad-sizes' );
			adSizesDiv.removeClass( 'hidden' );
			$( '.advads-gam-ad-sizes-notice-missing-sizes' ).addClass( 'hidden' );
			const adData = AAGAM.getAdData();

			if ( typeof adUnitSizes === 'undefined' || ! adUnitSizes ) {
				adUnitSizes = this.getAdUnitSizes( adData );
			}

			const originalSizes = this.getAdUnitSizes( AAGAM.getAdData(), true );

			// Fill the template and load it in the reserved place
			let data = {
				header:           adUnitSizes,
				fluidNoticeStyle: typeof advads_gam_amp !== 'undefined' && advads_gam_amp.hasAMP && adUnitSizes.indexOf( 'fluid' ) !== - 1 ? '' : 'display:none;',
				originalSizes:    originalSizes
			};

			adSizesDiv.find( '.advads-gam-ad-sizes-table-container' ).html( ( wp.template( 'gam-ad-sizes-table' ) )( data ) );
			const rows      = useCurrentSizes ? this.getAdUnitSizesRows( adData, adUnitSizes ) : this.getAdUnitSizesRows( adData );
			const tableBody = adSizesDiv.find( '.advads-gam-ad-sizes-table-container tbody' );
			_.each( rows, function ( row, screenWidth ) {
				tableBody.append( ( wp.template( 'gam-ad-sizes-table-row' ) )( {
					row:           row,
					screenWidth:   screenWidth,
					originalSizes: originalSizes
				} ) );
			} );

			// AMP sizes.
			if ( advads_gam_amp.hasAMP ) {
				const ampSizesTmpl = wp.template( 'gam-amp-ad-sizes' );
				const ampSizes     = adUnitSizes;
				let ampChecked     = {};

				for ( const size of ampSizes ) {
					// For new ads, checks all sizes on AMP. Look into settings otherwise
					if ( document.location.href.indexOf( 'post-new.php' ) !== - 1 || advads_gam_amp.sizes.indexOf( size ) !== - 1 ) {
						ampChecked[size] = true;
					}
				}

				const ampArgs = {
					sizes:   ampSizes,
					checked: ampChecked
				};

				adSizesDiv.find( '.advads-gam-ad-sizes-table-container table' ).append( $( ampSizesTmpl( ampArgs ) ) );
			}
			this.checkLastSize();
		},

		/**
		 * Load a new line into the Ad sizes option. We just copy the previous line including the options
		 *
		 * @param el DOM node in the current row
		 */
		loadAdSizesRow: function ( el ) {
			const originalSizes = this.getAdUnitSizes( AAGAM.getAdData(), true );
			const tr            = $( el ).closest( 'tr' );
			const screenWidth   = parseInt( tr.find( '.screen-width-input' ).val() ) + 1;
			const row           = {};

			tr.find( 'input[type="checkbox"]' ).each( function () {
				row[$( this ).val()] = $( this ).prop( 'checked' );
			} );

			tr.after( ( wp.template( 'gam-ad-sizes-table-row' ) )( {
				row:           row,
				screenWidth:   screenWidth,
				originalSizes: originalSizes
			} ) );

			// Set focus to the min width field of the next line
			tr.next().find( 'input[type="number"]' ).focus();
		},

		/**
		 * Load the rows for the Ad Sizes option with ad units
		 *
		 * @param adData data of current ad
		 * @param adUnitSizes available ad sizes
		 * @returns {{}}
		 */
		getAdUnitSizesRows: function ( adData, adUnitSizes ) {
			// Iterate through the rows based on the stored values
			if ( typeof adUnitSizes === 'undefined' ) {
				adUnitSizes = this.getAdUnitSizes( adData );
			}

			if ( ! adUnitSizes ) {
				return [];
			}

			// If the ad was never saved with Ad sizes before, we enable all checkboxes
			const enableAll = 'undefined' === typeof advads_gam_stored_ad_sizes_json || jQuery.isEmptyObject( advads_gam_stored_ad_sizes_json );

			// Load the stored values or use a default that set up a new line.
			const savedSizes = ! enableAll ? advads_gam_stored_ad_sizes_json : {
				0: {
					width: 0
				}
			};

			let rows = {};

			for ( let width in savedSizes ) {
				rows[width] = {};
				adUnitSizes.forEach(
					function ( adSizeString ) {
						// True if the option exists and was set before or if it was not saved yet
						rows[width][adSizeString] = savedSizes[width]['sizes'] && savedSizes[width]['sizes'].includes( adSizeString ) || enableAll;
					}
				);
			}

			return rows;
		},
	};

	/**
	 * Returns the unique ad size handler instance
	 *
	 * @returns {adSizes}
	 */
	window.AdvancedAdsGamGetAdSizes = function () {
		if ( typeof sizeInstance === 'undefined' ) {
			sizeInstance = new adSizes();
		}
		return sizeInstance;
	};

} )( window.jQuery );
