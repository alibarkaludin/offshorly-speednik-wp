( function ( $, wp ) {
	'use strict';

	/**
	 * Tool class using AA modal frame
	 */
	class Modal {
		/**
		 * Constructor
		 *
		 * @param {string} markup modal dialog's HTML
		 */
		constructor( markup ) {
			this.$el = $( markup );
		}

		setContent( content ) {
			this.$el.find( '.advads-modal-content' ).data( 'content', content );
		}

		/**
		 * Toggle display property of an overlaying div that covers the entire modal frame content - To be used during AJAX calls
		 *
		 * @param {boolean} show Whether to show it.
		 */
		toggleOverlay( show ) {
			if ( typeof show === 'undefined' ) {
				show = true;
			}

			$( '#gam-adlist-overlay' ).css( 'display', show ? 'block' : 'none' );
		}

		/**
		 * Set/replace header markup
		 *
		 * @param {string} html markup to be placed in the header.
		 */
		setHeader( html ) {
			this.$el.find( '.advads-modal-header' ).html( html );
		}

		/**
		 * Set/replace body markup
		 *
		 * @param {string} html markup to be placed in the body.
		 */
		setBody( html ) {
			this.$el.find( '.advads-modal-body' ).html( html );
		}

		/**
		 * Set/replace footer markup
		 *
		 * @param {string} html markup to be placed in the footer.
		 */
		setFooter( html ) {
			this.$el.find( '.advads-modal-footer' ).html( html );
		}

		/**
		 * Show the modal frame
		 */
		show() {
			window.location.hash = 'modal-gam-ad-list';
		}

		/**
		 * Hide the modal frame
		 */
		hide() {
			window.location.hash = 'close';
		}
	}

	/**
	 * Ad list helper constructor
	 */
	const adList = function () {
		this.nonce       = null;
		this.currentUnit = {};
		this.modal       = null;
		this.bindEvListners( this );
	};

	// The unique list helper instance.
	let listInstance;

	adList.prototype = {
		constructor: adList,

		/**
		 * Populate the different sections
		 *
		 * @param {array} newAdIds internal id-s of newly added ads that need to be highlighted.
		 * @returns {Modal}
		 */
		populateModal( newAdIds ) {
			if ( typeof newAdIds !== 'object' || ! newAdIds.length ) {
				newAdIds = [];
			}
			this.modal.setHeader( this.renderTemplate( 'gam-unitlist-header' ) );
			this.modal.setFooter( this.renderTemplate( 'gam-unitlist-footer' ) );
			this.modal.setBody( this.renderTemplate(
				'gam-unitlist-body',
				{
					ad_list:       window.gamAdvancedAdsJS.adUnitList,
					current:       this.currentUnit,
					valid_license: window.gamAdvancedAdsJS.hasGamLicense,
					new_ads:       newAdIds
				}
			) );
			const tbody = $( '#advads-gam-table' ).find( 'tbody' );
			tbody.prepend( tbody.find( 'tr.new' ) );
			this.modal.setContent( 'ad-list' );
			return this.modal;
		},

		/**
		 * Bind all ad list related event listeners
		 *
		 * @param {adList} self
		 */
		bindEvListners( self ) {
			// Click on on of the the select ad unit buttons.
			$( document ).on( 'click', '#show-gam-ad-list', function ( ev ) {
				ev.preventDefault();
				self.populateModal().show();
			} );

			// Get fresh ad unit data from Google.
			$( document ).on( 'click', '#refresh-current-unit', function ( ev ) {
				ev.preventDefault();
				if ( $( this ).hasClass( 'disabled' ) ) {
					return;
				}
				self.updateSingleAd( self.currentUnit.id );
			} );

			// Updtate stored (in post content) ad unit data to the new (and different) one from Google.
			$( document ).on( 'click', '#advads-gam-current-unit-updated button', function ( ev ) {
				ev.preventDefault();
				window.AAGAM.updateAdContent( self.encodeUnitData( self.getStoredAdunitById( self.currentUnit.id ) ) );
				$( '#publish' ).trigger( 'click' );
			} );

			// Select a new active unit from the ad unit list.
			$( document ).on( 'click', '#advads-gam-table tbody tr', function () {
				self.updateSelected( $( this ).data( 'unitid' ).split( '_' )[1] );
			} );

			// Remove an unit from the ad unit list.
			$( document ).on( 'click', '#advads-gam-table tbody .dashicons-remove', function ( ev ) {
				const icon = $( this );
				if ( icon.hasClass( 'disabled' ) ) {
					return;
				}
				ev.stopPropagation();
				self.removeAdUnit( icon.closest( 'tr' ).data( 'unitid' ).split( '_' )[1] );
			} );

			// After a change of ad type.
			$( document ).on( 'paramloaded', '#advanced-ads-ad-parameters', function () {
				if ( $( '#advanced-ad-type-gam' ).is( ':checked' ) ) {
					if ( ! self.modal ) {
						self.initModal();
					}
					self.renderAdList();
				}
			} );

			$( document ).on( 'click', '#modal-gam-ad-list a[href="#close"]', function ( ev ) {
				if ( $( this ).closest( '.advads-modal-content' ).data( 'content' ) === 'ad-list' ) {
					return;
				}
				ev.preventDefault();
				self.populateModal();
			} );
		},

		/**
		 * Shortcut for `( wp.template( id ) )( data )`
		 *
		 * @param {string} id template ID as described in https://codex.wordpress.org/Javascript_Reference/wp.template
		 * @param {any} data the interpolation variable as described in https://codex.wordpress.org/Javascript_Reference/wp.template
		 * @returns {string}
		 */
		renderTemplate( id, data ) {
			if ( typeof data === 'undefined' ) {
				data = null;
			}
			return ( wp.template( id ) )( data );
		},

		/**
		 * Render the ad unit section of the Ad Parameters meta-box
		 */
		renderAdList: function () {
			if ( this.isEmpty( this.currentUnit ) ) {
				this.currentUnit = window.AAGAM.jsonParse( decodeURIComponent( $( '#gam-adlist-onparamloaded-current' ).text().trim() ) );
			}

			window.AdvancedAdsGamGetAdSizes().loadAdSizes();

			if ( ! this.accountIsConnected() ) {
				return;
			}

			const wrapper = $( '#current-ad-unit' );

			if ( wrapper.hasClass( 'disabled' ) ) {
				return;
			}

			wrapper.empty();

			if ( ! this.isEmpty( this.currentUnit ) ) {
				wrapper.append( this.renderTemplate( 'gam-current-unit', {unit: this.currentUnit} ) );
			}

			wrapper.append( this.renderTemplate(
				'gam-unitlist-buttons',
				{
					valid_license: window.gamAdvancedAdsJS.hasGamLicense,
					has_unit_data: ! this.isEmpty( this.currentUnit )
				}
			) );

			window.AdvancedAdsGamGetAdSizes().loadAdSizes();
			const updatedUnitNotice = $( '#advads-gam-current-unit-updated' ), storedAdUnit = this.getStoredAdunitById( this.currentUnit.id );

			// Unit in post content not found in ad list
			if ( storedAdUnit === false && window.gamAdvancedAdsJS.networkCode === this.currentUnit.networkCode ) {
				wrapper.append( this.renderTemplate( 'gam-unit-not-in-list', {unitId: this.currentUnit.id, unitName: this.currentUnit.name} ) );
			}

			// Unit in post content has be updated in the GAM account.
			if ( storedAdUnit === false || ( this.encodeUnitData( this.currentUnit ) === this.encodeUnitData( storedAdUnit ) ) ) {
				updatedUnitNotice.addClass( 'hidden' );
			} else {
				updatedUnitNotice.removeClass( 'hidden' );
			}
		},

		/**
		 * Get ad unit data from the stored ad unit list given an ad unit id (GAM unit ID)
		 *
		 * @param {string} id the unit ID.
		 * @returns {false|object}
		 */
		getStoredAdunitById( id ) {
			for ( const ad of window.gamAdvancedAdsJS.adUnitList ) {
				if ( ad.id === id ) {
					return ad;
				}
			}

			return false;
		},

		/**
		 * Returns `true` if there is a connected account
		 *
		 * @returns {boolean}
		 */
		accountIsConnected() {
			return window.gamAdvancedAdsJS.networkCode.trim() !== '';
		},

		/**
		 * Update the ad list and active unit markup as well as the post content input whenever an ad list row is clicked on
		 *
		 * @param {string} id the ad unit ID (GAM unit id).
		 */
		updateSelected: function ( id ) {
			if ( ! this.modal ) {
				return;
			}

			this.currentUnit = this.decodeUnitData( this.modal.$el.find( 'tr[data-unitid="' + window.gamAdvancedAdsJS.networkCode + '_' + id + '"]' ).data( 'unitdata' ) );
			window.AAGAM.updateAdContent( this.encodeUnitData( this.currentUnit ) );
			this.renderAdList();
			this.modal.hide();
			const adSizes = window.AdvancedAdsGamGetAdSizes();
			adSizes.loadAdSizes( adSizes.getAdUnitSizes( this.currentUnit ), true );
		},

		/**
		 * Remove one single ad unit from the list.
		 *
		 * @param {string} id the ad unit ID (GAM unit id).
		 */
		removeAdUnit( id ) {
			this.modal.toggleOverlay();
			const self = this;
			wp.ajax.send( 'advads_gamapi_remove_ad', {
				data:    {
					nonce: self.nonce,
					id:    id
				},
				success: function ( data ) {
					window.gamAdvancedAdsJS.adUnitList = data.ad_units;
					self.populateModal();
					self.modal.toggleOverlay( false );
					self.renderAdList();
				},
				error:   function ( response ) {
					self.toggleAdParamOverlay( false );
					console.error( response );
				}
			} );
		},

		/**
		 * Update data on for single ad unit.
		 *
		 * @param {string} id the ad unit ID (GAM unit id).
		 */
		updateSingleAd( id ) {
			this.toggleAdParamOverlay();
			const self = this;
			wp.ajax.send( 'advads_gamapi_update_single_ad', {
				data:    {
					nonce: this.nonce,
					id:    id
				},
				success: function ( data ) {
					self.toggleAdParamOverlay( false );
					window.gamAdvancedAdsJS.adUnitList = data.ad_units;
					window.AdvancedAdsGamGetAdList().renderAdList();
				},
				error:   function ( response ) {
					self.toggleAdParamOverlay( false );
					console.error( response );
				}
			} );
		},

		/**
		 * Check if an array or object is empty
		 *
		 * @returns {boolean}
		 * @param {Array|Object} obj the variable to be tested.
		 */
		isEmpty( obj ) {
			return ! Object.keys( obj ).length;
		},

		/**
		 * Load the modal frame markup and place it in the page.
		 */
		initModal() {
			if ( ! this.accountIsConnected() || this.modal ) {
				return;
			}

			if ( ! this.nonce ) {
				this.nonce = $( '#gam-adlist-nonce' ).text().trim();
			}

			const self = this;
			wp.ajax.send(
				'advads_gam_units_modal',
				{
					data:    {
						nonce: this.nonce
					},
					success: function ( response ) {
						if ( self.modal ) {
							return;
						}
						self.modal = new Modal( response.markup );
						$( '#wpwrap' ).append( self.modal.$el );
						self.modal.$el.find( '.advads-modal-content' ).append( $( self.renderTemplate( 'gam-adlist-overlay' ) ) );
					},
					error:   function ( error ) {
						console.error( error );
					}
				}
			);
		},

		/**
		 * Get an encoded string (follows the format of the post content) from an unit data
		 *
		 * @param {object} adUnit ad unit data
		 * @returns {String}
		 */
		encodeUnitData( adUnit ) {
			return btoa( encodeURIComponent( JSON.stringify( adUnit, null, null ) ) );
		},

		/**
		 * Decode a post content formatted string into an ad unit data object
		 *
		 * @param {object} data post content like data.
		 * @returns {any}
		 */
		decodeUnitData( data ) {
			return window.AAGAM.jsonParse( decodeURIComponent( atob( data ) ) );
		},

		/**
		 * Toggle visibility of an overlaying div that covers the entire Ad Parameters meta-box's content
		 *
		 * @param {boolean} show Whether to show the overlay
		 */
		toggleAdParamOverlay( show ) {
			if ( typeof show === 'undefined' ) {
				show = true;
			}

			$( '#advads-gam-ads-list-overlay' ).css( 'display', show ? 'block' : 'none' );
		}
	};

	/**
	 * Returns the unique adList instance
	 *
	 * @returns {adList}
	 */
	window.AdvancedAdsGamGetAdList = function () {
		if ( typeof listInstance === 'undefined' ) {
			listInstance = new adList();
		}

		return listInstance;
	};

	// DOM ready.
	$( function () {
		// If the ad type on load is GAM, construct the object load the modal frame markup immediately.
		if ( $( '#advanced-ad-type-gam' ).prop( 'checked' ) ) {
			window.AdvancedAdsGamGetAdList().initModal();
		}

		if ( window.location.hash === '#modal-gam-ad-list' ) {
			window.location.hash = 'close';
		}
	} );
} )( window.jQuery, window.wp );
