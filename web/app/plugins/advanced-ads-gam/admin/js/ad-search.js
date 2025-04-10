( function ( $, wp ) {
	'use strict';

	let nonce = null;

	$( document ).on( 'click', '.advads-gam-open-ad-search', function ( ev ) {
		ev.stopPropagation();
		ev.preventDefault();

		if ( $( this ).hasClass( 'disabled' ) ) {
			return;
		}

		populateModal();
	} );

	$( document ).on( 'keyup', '#advads-gam-search-input', function () {
		$( '#advads-gam-search-button' ).prop( 'disabled', $( '#advads-gam-search-input' ).val().trim().length < 2 );
	} );

	$( document ).on( 'submit', '#advads-gam-search', function () {
		searchAds( $( '#advads-gam-search-input' ).val() );
		return false;
	} );

	$( document ).on( 'click', '#gam-search-load-results', function () {
		const ads = collectAdToLoad();
		if ( ! window.AdvancedAdsGamGetAdList().isEmpty( ads ) ) {
			appendAdUnits( ads );
		}
	} );

	/**
	 * Get ad data to be added to the ad unit list variable.
	 *
	 * @returns {Array}
	 */
	function collectAdToLoad() {
		let ads = {};
		$( '#modal-gam-ad-list tbody input[name="gam-unit-found\[\]"]:checked' ).each( function () {
			const el             = $( this );
			ads[el.data( 'id' )] = el.val();
		} );
		return ads;
	}

	/**
	 * Append (or update) ad units to the ad unit list variable and in the database.
	 *
	 * @param {string[]} ads the ad units to be added in JSON string format.
	 */
	function appendAdUnits( ads ) {
		const modal = AdvancedAdsGamGetAdList().modal, ids = Object.keys( ads );
		ads         = Object.values( ads );
		modal.toggleOverlay();
		wp.ajax.send( 'advads_gamapi_append_ads', {
			data:    {
				nonce: nonce,
				units: ads
			},
			success: function ( data ) {
				modal.toggleOverlay( false );
				window.gamAdvancedAdsJS.adUnitList = data.units;
				AdvancedAdsGamGetAdList().populateModal( ids );
				const adNotFound = $( '#advads-gam-adunit-not-found' );
				if ( adNotFound.length ) {
					const adsById = getAdUnitListById();
					if ( typeof adsById[adNotFound.attr( 'data-id' )] !== 'undefined' ) {
						adNotFound.remove();
					}
				}
				// window.AdvancedAdsGamGetAdList().renderAdList();
			},
			error:   function ( response ) {
				modal.toggleOverlay( false );
				AdvancedAdsGamGetAdList().modal.hide();
				console.error( response );
			}
		} );
	}

	/**
	 * Search ads by name on the GAM account.
	 *
	 * @param {string} search the partial name to search for.
	 */
	function searchAds( search ) {
		if ( search.trim().length < 2 ) {
			return;
		}

		const modal = AdvancedAdsGamGetAdList().modal;
		modal.toggleOverlay();
		wp.ajax.send( 'advads_gamapi_search_ads', {
			data:    {
				nonce:  nonce,
				search: search
			},
			success: function ( data ) {
				modal.toggleOverlay( false );
				const adListHelper = AdvancedAdsGamGetAdList();
				let unitsFound     = [];

				for ( const id in data.results.units ) {
					unitsFound.push( {
						id:          id,
						data:        adListHelper.encodeUnitData( data.results.units[id] ),
						name:        data.results.units[id].name,
						description: data.results.units[id].description,
						inList:      Boolean( adListHelper.getStoredAdunitById( id ) )
					} );
				}

				adListHelper.modal.setBody( adListHelper.renderTemplate( 'gam-ad-search-results', {units: unitsFound} ) );
				let footerMarkup = ( wp.template( 'gam-ad-search-footer' ) )( {action: 'init'} );

				if ( data.results.count !== 0 ) {
					let unitsInlist = 0;
					for ( let ad of window.gamAdvancedAdsJS.adUnitList ) {
						if ( typeof data.results.units[ad.id] !== 'undefined' ) {
							unitsInlist ++;
						}
					}
					footerMarkup = ( wp.template( 'gam-ad-search-footer' ) )( {action: unitsInlist === parseInt( data.results.count, 10 ) ? 'imported' : 'load'} );
				}

				adListHelper.modal.setFooter( footerMarkup );
			},
			error:   function ( response ) {
				modal.toggleOverlay( false );
				console.error( response );
			}
		} );
	}

	/**
	 * Get the stored ad unit list indexed by ad slot ID.
	 *
	 * @returns {Object}
	 */
	function getAdUnitListById() {
		let list = {};
		for ( const ad of window.gamAdvancedAdsJS.adUnitList ) {
			list[ad.id] = ad;
		}
		return list;
	}

	/**
	 * Populate the ad search modal frame
	 */
	function populateModal() {
		const adList = AdvancedAdsGamGetAdList();
		adList.modal.setHeader( adList.renderTemplate( 'gam-ad-search-head' ) );
		adList.modal.setBody( '' );
		adList.modal.setFooter( adList.renderTemplate( 'gam-ad-search-footer', {action: 'init'} ) );
		adList.modal.setContent( 'ad-search' );

		setTimeout( function () {
			$( '#advads-gam-search-input' ).focus();
		}, 350 );

		if ( ! nonce ) {
			nonce = $( '#gam-ad-search-nonce' ).val();
		}
	}

} )( window.jQuery, window.wp );
