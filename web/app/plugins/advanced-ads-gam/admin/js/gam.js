/**
 * Google Ad Manager network JS for dashboard
 */

class AdvancedAdsNetworkGam extends AdvancedAdsAdNetwork {
	/**
	 * Constructor
	 */
	constructor() {
		super('gam');
		this.name = 'Google Ad Manager';
		this.loaded = false;
		this.domReadyEventCount = 0;
	}

	jsonParse(str) {
		try {
			return JSON.parse(str);
		} catch (e) {
			return null;
		}
	}

	onBlur() {
		console.log('AdvanvedAdsNetworkGam >> onBlur');
	}

	/**
	 * will be called when an ad network is selected (ad type in edit ad)
	 */
	onSelected() {
		window.AdvancedAdsGamGetAdList().updateSelected();
	}

	/**
	 * opens the selector list containing the external ad units
	 */
	openSelector() {
		console.log('AdvanvedAdsNetworkGam >> openSelector');
	}

	/**
	 * returns the network specific id of the currently selected ad unit
	 */
	getSelectedId() {}

	/**
	 * will be called when an external ad unit has been selected from the selector list
	 * @param slotId string the external ad unit id
	 */
	selectAdFromList(slotId) {
		console.log('AdvanvedAdsNetworkGam >> selectAdFromList');
	}

	/**
	 * return the POST params that you want to send to the server when requesting a refresh of the external ad units
	 * (like nonce and action and everything else that is required)
	 */
	getRefreshAdsParameters() {
		console.log('AdvanvedAdsNetworkGam >> getRefreshAdsParameters');
	}

	/**
	 * return the jquery objects for all the custom html elements of this ad type
	 */
	getCustomInputs() {
		console.log('AdvanvedAdsNetworkGam >> getCustomInputs');
	}

	/**
	 * Base4 decode followed by decodeURIComponent
	 *
	 * @param {string} str The string to be decoded.
	 * @returns {null|string}
	 */
	base64Decode( str ) {
		try {
			return decodeURIComponent( atob( str ) );
		} catch ( ex ) {
			return null;
		}
	}

	/**
	 * Base64 encode preceded by encodeURIComponent
	 *
	 * @param {string} str The string to be encoded.
	 * @returns {String}
	 */
	base64Encode( str ) {
		return btoa( encodeURIComponent( str ) );
	}

	/**
	 * Get ad data from post content.
	 *
	 * @return [object] the post data.
	 */
	getAdData() {
		const postContent = this.jsonParse( this.base64Decode( jQuery( 'input[name="advanced_ad\[content\]"]' ).val() ) );

		if ( ! jQuery( '#advads-gam-table tbody tr[data-unitid]' ).length && postContent ) {
			return window.AdvancedAdsGamGetAdSizes().appendFluidSize( postContent );
		}

		let adData = false;

		if ( postContent && postContent.networkCode && postContent.id ) {
			adData = this.jsonParse( this.base64Decode( jQuery( '#advads-gam-table tbody tr[data-unitid="' + postContent.networkCode + '_' + postContent.id + '"]' ).attr( 'data-unitdata' ) ) );
			adData = window.AdvancedAdsGamGetAdSizes().appendFluidSize( adData );
		}
		return adData;
	}

	/**
	 * what to do when the DOM is ready
	 */
	onDomReady() {
		if ( this.domReadyEventCount !== 0 ) {
			return;
		}

		this.domReadyEventCount ++;

		// add a new row to the Ad sizes form
		jQuery( document ).on( 'click', '#advads-gam-ad-sizes .advads-row-new', function () {
			window.AdvancedAdsGamGetAdSizes().loadAdSizesRow( this );
			// remove the (+) icon to prevent adding multiple rows with the same index
			jQuery( this ).hide();
		} );

		// show the + icon when the last line was removed.
		jQuery(document).on('click', '#advads-gam-ad-sizes .advads-tr-remove', function () {
			setTimeout(function () {
				jQuery('#advads-gam-ad-sizes table tr:last-of-type .advads-row-new').show();
			}, 100);
		});
	}

	updateAdContent( encodedAdContent ) {
		jQuery( '#advads-gam-netcode-mismatch' ).hide();
		jQuery( 'input[name="advanced_ad\[content\]"]' ).val( encodedAdContent );
	}

	/**
	 * when you need custom behaviour for ad networks that support manual setup of ad units, override this method
	 */
	onManualSetup() {
		//no console logging. this is optional
		console.log('AdvanvedAdsNetworkGam >> onManualSetup');
	}
}

var AdvancedAdsGamConnect = function () {
	this.$modal = jQuery('#advads-gam-modal');
	this.$currentContent = this.$modal.find('.advads-gam-modal-content-inner').first();

	this.init();

	return this;
};

AdvancedAdsGamConnect.prototype = {

	constructor: AdvancedAdsGamConnect,

	changeContent: function (id) {
		this.$modal.find('.advads-gam-modal-content-inner').css('display', 'none');
		this.$currentContent = this.$modal.find('.advads-gam-modal-content-inner[data-content="' + id + '"]');
		this.$currentContent.css('display', 'block');
	},

	/**
	 * Initialization tasks (on DOM ready)
	 */
	init: function () {
		var $ = window.jQuery;
		var that = this;

		$(document).on('click', '#advads-gam-modal .dashicons-dismiss', function () {
			that.hideAndReload();
		});
		$(document).on('click', '#advads-gam-connect', function () {
			if ($(this).hasClass('nosoapkey')) {
				$('#gam-settings-overlay').css('display', 'block');
				$.ajax({
					url: ajaxurl,
					type: 'post',
					data: {
						action: 'advads_gamapi_get_key',
						nonce: $(this).attr('data-nonce'),
					},
					success: function (response) {
						$('#gam-settings-overlay').css('display', 'none');
						if (response.status) {
							that.openGoogle();
						} else {
							console.log('>> API key error');
							console.log(response);
						}
					},
					error: function (error) {
						console.error(error);
					},
				});
			} else {
				that.openGoogle();
			}
		});

		$(document).on('click', '#advads-gam-revoke', function () {
			that.revokeAccess.apply(that);
		});

		$(document).on('click', '#gam-selected-network', function () {
			that.accountSelected.apply(that);
		});

		$(document).on('paramloaded', '#advanced-ads-ad-parameters', function () {
			that.onParamloaded.apply(that);
		});

		$(document).on('click', '#gam input[type="radio"], #gam input[type="checkbox"]', function () {
			$('#gam .submit input[type="submit"]').focus();
		});

		const authCode = $( '#advads-gam-oauth-code' );

		if ( authCode.length ) {
			this.show();
			this.submitConfirmCode( authCode.val() );
		}

	},

	onParamloaded: function () {
		var $ = jQuery;
		var adType = $('input[name="advanced_ad\[type\]"]:checked').val();

		if ( 'gam' === adType ) {
			$( '#advanced-ads-ad-parameters' ).next( '.advads-option-list' ).css( 'display', 'none' );
			if ( $( '#advads-gam-ad-sizes' ).html().indexOf( 'advads-loader' ) !== - 1 ) {
				AdvancedAdsAdmin.AdImporter.isSetup = false;
				AdvancedAdsAdmin.AdImporter.setup( AdvancedAdsAdmin.AdImporter.adNetwork );
			}
		} else {
			$( '#advanced-ads-ad-parameters' ).next( '.advads-option-list' ).css( 'display', 'block' );
		}
	},

	/**
	 * Show/hide loading overlay
	 */
	overlay: function () {
		var $overlay = this.$currentContent.find('.advads-gam-overlay');
		if ($overlay.is(':visible')) {
			$overlay.css('display', 'none');
		} else {
			$overlay.css('display', 'block');
		}
	},

	/**
	 * Send an ajax request
	 *
	 * @param data data the form data.
	 * @param onSuccess success callback on success
	 * @param onError error callback function on error.
	 */
	ajax: function (data, onSuccess, onError) {
		if ('undefined' == typeof data || 'undefined' == typeof data.action) {
			console.error('AJAX call needs at least an "action" field');
			return;
		}
		data.nonce = this.$modal.attr('data-nonce');
		this.overlay();
		var that = this;
		jQuery.ajax({
			url: ajaxurl,
			type: 'post',
			data: data,
			success: function (response, status, XHR) {
				if ('function' == typeof onSuccess) {
					onSuccess.apply(that, [response, status, XHR]);
				} else {
					console.log(response);
				}
			},
			error: function (request, status, error) {
				if ('function' == typeof onError) {
					onError.apply(that, [request, status, error]);
				} else {
					console.error(error);
				}
			},
		});

	},

	accountSelected: function () {
		var extraData = jQuery('#gam-account-list-data').val(),
		action = 'advads_gamapi_account_selected',
		that = this,
		index = jQuery('#gam-account-list').val();

		this.ajax({
			extra_data: extraData,
			action: action,
			index: index,
		},
			function (response) {
			if (response.status) {
				that.hideAndReload();
			}
		});
	},

	revokeAccess: function () {
		var that = this;
		jQuery('#advads-gam-page-overlay').css('display', 'block');
		this.ajax({
			action: 'advads_gamapi_revoke'
		}, function (response) {
			if (response.status) {
				document.location.reload();
			} else {
				jQuery('#advads-gam-page-overlay').css('display', 'none');
				that.overlay();
			}
		});
	},

	submitConfirmCode: function (code) {
		action = 'advads_gamapi_confirm_code';

		if ('string' != typeof code || '' === code.trim()) {
			return;
		}
		this.ajax({
			action: action,
			code: code
		}, this.confirmCodeResponse);

	},

	/**
	 * Force usage of REST API rather than the installed SOAP module
	 *
	 * @param {Object} tokenData fresh access&refresh tokens
	 */
	forceNoSOAP( tokenData ) {
		const self = this;
		this.ajax( {
				action:     'advads_gamapi_force_no_soap',
				token_data: tokenData,
			},
			function ( response ) {
				self.getNetworks( tokenData );
			},
			function ( response ) {
				console.log( response );
			}
		);
	},

	/**
	 * Get all networks accessible with a given set of tokens
	 *
	 * @param {Object} tokenData fresh access&refresh tokens.
	 */
	getNetworks( tokenData ) {
		this.overlay();
		const self = this;
		this.ajax(
			{
				action:     'advads_gamapi_getnet',
				token_data: tokenData
			},
			function ( response ) {
				if ( response.data.action === 'reload' ) {
					self.hideAndReload();
					return;
				}

				if ( response.data.action === 'select_account' ) {
					self.overlay();
					const $       = jQuery,
						  $select = $( '#gam-account-list' );

					for ( const i in response.data.networks ) {
						$select.append( $( '<option value="' + i + '">[' + response.data.networks[i].networkCode + '] ' + response.data.networks[i].displayName + '</option>' ) );
					}

					$( '#gam-account-list-data' ).val( JSON.stringify( {
						token_data: response.data.token_data,
						networks:   response.data.networks
					} ) );
					self.changeContent( 'select_account' );
				}

			},
			function ( response ) {
				try {
					const code = response.responseJSON.data.error_code;
					if ( code === 'empty_account' ) {
						self.changeContent( code );
					}
					if ( code === 'soap_fault' ) {
						self.changeContent( 'soap_fault' );
						self.forceNoSOAP( tokenData );
					}
				} catch ( e ) {
					console.log( response );
				}
			} );
	},

	/**
	 * Send the response received after successfully submitting an OAuth2 confirmation code
	 *
	 * @param {Object} response Google response containing the token data.
	 */
	confirmCodeResponse: function ( response ) {
		this.getNetworks( response.token_data );
	},

	/**
	 * Show the modal frame
	 */
	show: function () {
		this.$modal.css('display', 'block');
	},

	/**
	 * Hide the modal frame, reload the setting tab
	 */
	hideAndReload: function () {
		window.location.href = this.$modal.attr('data-gamsettings');
	},

	/**
	 * Redirect to Google for authorization.
	 */
	openGoogle: function () {
		window.location.href = decodeURIComponent( this.$modal.attr( 'data-url' ) );
	},
};

window.jQuery(function () {
	const gamAdType = jQuery( '#advanced-ad-type-gam' );

	new AdvancedAdsNetworkGam();

	jQuery( document ).on( 'change', 'input[name^="advanced_ad[output][ad-sizes]"][type="number"]', function () {
		const width = this.value;
		jQuery( this ).closest( 'tr' ).find( 'input' ).each( function () {
			jQuery( this ).attr( 'name', jQuery( this ).attr( 'name' ).replace( /\d+/, width ) );
		} );
	} );

	if ( window.location.href.indexOf( '&new_ad_type=gam' ) !== -1 && gamAdType.length ) {
		gamAdType.trigger( 'click' );
	}

	if ( typeof window.AdvancedAdsGamGetAdList === 'function' ) {
		if ( typeof AAGAM === 'undefined' ) {
			return;
		}
		window.AdvancedAdsGamGetAdList().renderAdList();
	}

	if (window.jQuery('#advads-gam-modal').length) {
		new AdvancedAdsGamConnect();

		// Test if the API is enabled for the freshly connected account
		const emptyListFlag = jQuery( '#gamlistisempty' );
		if ( emptyListFlag.length ) {
			jQuery( document ).trigger( 'gam-late-import', [jQuery( '#gam-settings-overlay' ), emptyListFlag.val(), 'gam-connect'] );
		}
	}

});
