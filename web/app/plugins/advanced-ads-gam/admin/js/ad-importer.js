(function ($) {
	"use strict";

	var DEFAULT_BODY = null;
	var DEFAULT_FOOTER = null;
	let testingTheApi = false;

	/**
	 * Disable/enable action buttons on the modal's footer.
	 */
	function primaryButtonsEnabled(enabled) {
		if ('undefined' == typeof enabled) {
			enabled = true;
		}
		$('#modal-gam-import .advads-modal-footer button.button').prop('disabled', !enabled);
	}

	// Open importer button.
	$(document).on('click', '#gam-open-importer', function () {
		if (null === DEFAULT_BODY) {
			DEFAULT_BODY = $('#modal-gam-import .advads-modal-body').html();
			DEFAULT_FOOTER = $('#modal-gam-import .advads-modal-footer').html();
		} else {
			$('#modal-gam-import .advads-modal-body').html(DEFAULT_BODY);
			$('#modal-gam-import .advads-modal-footer').html(DEFAULT_FOOTER);
		}
		primaryButtonsEnabled( false );
		const payload = {
			nonce:  $( '#gam-open-importer' ).attr( 'data-nonce' ),
			action: 'gam_importable_list'
		};
		$.ajax( {
			type:    'post',
			url:     ajaxurl,
			data:    payload,
			success: function ( response ) {
				primaryButtonsEnabled( true );
				$( '#modal-gam-import .advads-modal-body' ).html( response.data.html );
			},
			error:   function ( response ) {
				primaryButtonsEnabled( true );
				console.error( response );
			}
		} );

	});

	// Update external ad unit lists (after successful account connection) then show the import button.
	$( document ).on( 'advads-gam-load-import-button', function () {
		const lateImport = $( '#gam-late-import-button' );
		if ( ! lateImport.length ) {
			return;
		}
		const overlay = $( '#gam-settings-overlay' ).show();
		const payload = {
			nonce:        lateImport.attr( 'data-nonce' ),
			action:       'advads_gam_import_button',
			nonce_action: 'gam-importer'
		};
		$.ajax( {
			type:    'post',
			url:     ajaxurl,
			data:    payload,
			success: function ( response ) {
				try {
					lateImport.replaceWith( $( response.data.html ) );
					prepareImportButton();
				} catch ( e ) {
					// No importable ad found. Do not show the button.
				}
				overlay.hide();
			},
			error:   function ( response ) {
				overlay.hide();
				console.error( response );
			}
		} );
	} );

	// After a click on a close importer button/icon: Restore URL fragments.
	$(document).on('click', '#modal-gam-import .advads-modal-close, #modal-gam-import .advads-modal-close-background', function () {
		$('#modal-gam-import .advads-modal-body').html(DEFAULT_BODY);
	});

	/**
	 * Change on table body checkboxes
	 *
	 * @param ckb the checkbox.
	 */
	function bodyCheckbox(ckb) {
		if (ckb.prop('checked')) {
			ckb.closest('tr').addClass('checked');
		} else {
			ckb.closest('tr').removeClass('checked');
		}
		if (!$('#modal-gam-import tbody input[type="checkbox"]:not(:checked)').length) {
			// All ticked
			$('#modal-gam-import thead input[type="checkbox"]').prop('checked', true);
		} else {
			// Not all ticked
			$('#modal-gam-import thead input[type="checkbox"]').prop('checked', false);
		}
		if (!$('#modal-gam-import tbody input[type="checkbox"]:checked').length) {
			// All un-ticked
			$('#gam-import-form .import').prop('disabled', true);
		} else {
			// At least one ticked
			$('#gam-import-form .import').prop('disabled', false);
		}
	}

	// Click on tbody checkbox
	$(document).on('change', '#modal-gam-import tbody input[type="checkbox"]', function () {
		bodyCheckbox($(this));
	});

	// Change checkbox values on click on the containing row
	$(document).on('click', '#modal-gam-import tbody tr', function (ev) {
		if ($(ev.target).attr('type') && 'checkbox' === $(ev.target).attr('type')) {
			return;
		}
		var ckb = $(this).find('input[type="checkbox"]');
		bodyCheckbox(ckb.prop('checked', !ckb.prop('checked')));
	});

	// Click on thead checkbox
	$(document).on('change', '#modal-gam-import thead input[type="checkbox"]', function () {
		var ckb = $(this);
		if (ckb.prop('checked')) {
			$('#modal-gam-import tbody input[type="checkbox"]').prop('checked', true).trigger('change');
		} else {
			$('#modal-gam-import tbody input[type="checkbox"]').prop('checked', false).trigger('change')
			$('#modal-gam-import-form .import').prop('disabled', true);
		}
	});

	/**
	 * Handle response from the import AJAX call
	 *
	 * @param {Object} response response object sent back by `wp_send_json_success`
	 */
	function importResponse( response ) {
		let data = {};
		try {
			data = response.data;
		} catch ( e ) {

		}

		if ( typeof data.html !== 'undefined' ) {
			// All done, show markup in the modal frame.
			primaryButtonsEnabled( true );
			$( '#modal-gam-import .advads-modal-body' ).html( data.html );
			$( '#modal-gam-import .advads-modal-footer .tablenav' ).html( data.footer );
			return;
		}

		// Perform the next AJAX call.
		$.ajax( {
			type:    'post',
			url:     ajaxurl,
			data:    data.form_data,
			success: function ( response ) {
				importResponse( response );
			},
			error:   function ( response ) {
				console.error( response );
			}
		} );
	}

	// Launch the import process
	$(document).on('click', '#modal-gam-import #gam-start-import', function (ev) {
		ev.preventDefault();
		var ids = $('#gam-import-form').serialize();
		var nonce = $('#gam-open-importer').attr('data-nonce');

		$('#modal-gam-import .advads-modal-body').html(DEFAULT_BODY);
		primaryButtonsEnabled(false);
		const payload = {
			nonce: nonce,
			ids: ids,
			action: 'gam_import_ads',
		}
		$.ajax( {
			type:    'post',
			url:     ajaxurl,
			data:    payload,
			success: function ( response ) {
				importResponse( response );
			},
			error:   function ( response ) {
				$( '#modal-gam-import .advads-modal-footer .button-primary' ).remove();
				console.error( response );
			}
		} );
	});

	// Test the API
	$( document ).on( 'gam-late-import', function ( ev, overlay, nonce, nonceAction ) {
		testTheApi( overlay, nonce, nonceAction );
	} );

	/**
	 * Move the importer modal content out of any FORM.
	 */
	function prepareImportButton() {
		$( '#wpwrap' ).append( $( '#modal-gam-import' ) );

		// Add the import button (once).
		if ( $( '#gam-start-import' ).length ) {
			return;
		}

		$( '#modal-gam-import .advads-modal-footer .tablenav' ).append(
			$( '<button />' ).attr(
				{
					'class': 'button button-primary',
					id:      'gam-start-import'
				}
			).text(
				$( '#gam-open-importer' ).attr( 'data-import-text' )
			)
		);
	}

	/**
	 * Test if the API is enabled
	 *
	 * @param {jQuery} overlay loading overlay
	 * @param {string} nonce nonce value
	 * @param {string} nonceAction action to check the nonce against
	 */
	function testTheApi( overlay, nonce, nonceAction ) {
		if ( testingTheApi ) {
			return;
		}
		testingTheApi = true;
		overlay.show();
		const payload = {
			action:       'advads_gamapi_test_the_api',
			nonce:        nonce,
			nonce_action: nonceAction,
			root:         gamAdvancedAdsJS.rootAdUnit,
			network:      gamAdvancedAdsJS.networkCode
		};
		$.ajax( {
			url:  ajaxurl,
			type: 'post',
			data: payload,
			success: function ( response ) {
				testingTheApi = false;
				overlay.hide();
				let count;
				try {
					count = parseInt( response.data.count );
				} catch ( e ) {
					count = 0;
				}
				if ( count && advads_gam_importer_data.maxCount >= count ) {
					$( '#wpwrap' ).trigger( 'advads-gam-load-import-button' );
				}
			},
			error:   function ( response ) {
				testingTheApi = false;
				overlay.hide();
				try {
					const error = response.responseJSON.data.error;
					if ( error.indexOf( 'NETWORK_API_ACCESS_DISABLED' ) !== - 1 ) {
						$( '#gamapi-not-enabled' ).css( 'display', 'block' );
					}
				} catch ( EX ) {
					console.error( response );
				}
			}
		} );
	}

	/**
	 * On DOM ready.
	 */
	$( function () {
		if ( $( '#modal-gam-import' ).length ) {
			prepareImportButton();
		}

		const lateImport = $( '#gam-late-import-button' );
		if ( lateImport.length ) {
			testTheApi( $( '#gam-settings-overlay' ), lateImport.attr( 'data-nonce' ), 'gam-importer' );
		}
	} );

})(window.jQuery);
