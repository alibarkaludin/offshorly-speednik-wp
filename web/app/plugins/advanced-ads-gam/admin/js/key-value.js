/**
 * Key value targeting UI
 *
 */
(function ($) {
	"use strict";

	/**
	 * Sanitize value field
	 *
	 * @link https://support.google.com/admanager/answer/9796369#name-versus-display-name
	 *
	 * @param {String} str the original value.
	 * @returns {string}
	 */
	function sanitizeValue(str) {
		// Only alphanumeric chars|can not contain space (actually allowed but makes front end implantation simpler)
		str = str.toLowerCase().replace( /[^0-9a-z_]/ig, '_' );

		return str;
	}

	/**
	 * Sanitize post meta value field (meta_key)
	 *
	 * @link https://support.google.com/admanager/answer/9796369#name-versus-display-name
	 *
	 * @param {String} str the original value.
	 * @returns {string}
	 */
	function sanitizeMeta(str) {
		// Only alphanumeric chars|can not contain space (actually allowed but makes front end implantation simpler)
		str = str.toLowerCase().replace( /[^0-9a-z_-\s]/ig, '_' );

		return str;
	}

	/**
	 * Sanitize key field
	 *
	 * @link https://support.google.com/admanager/answer/9796369#name-versus-display-name
	 *
	 * @param {String} str the original key value.
	 * @returns {string}
	 */
	function sanitizeKey(str) {
		// Only alphanumeric chars|can not contain space.
		str = str.toLowerCase().replace( /[^0-9a-z_]/ig, '_' );

		// can not start with a number.
		if ( ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'].indexOf( str.charAt( 0 ) ) !== - 1 ) {
			str = '_' + str;
		}

		return str;
	}

	/**
	 * Add a row of key-value
	 *
	 * @param {String} type the key value type.
	 * @param {String} key the key (non sanitized).
	 * @param {String} value the value (non sanitized).
	 * @param {Boolean} archives whether to send category/tags/terms on archive pages.
	 */
	function addKVPair(type, key, value, archives) {
		let html = '<tr>';
		if ( typeof value === 'undefined' ) {
			value = false;
		}
		html += '<td data-th="' + advadsGamKvsi18n.type + '">' + gamAdvancedAdsJS.kvTypes[type].name + '<input type="hidden" name="advanced_ad[gam][type][]" value="' + type + '"></td>' +
			'<td data-th="' + advadsGamKvsi18n.key + '"><code>' + sanitizeKey( key ) + '</code><input type="hidden" name="advanced_ad[gam][key][]" value="' + sanitizeKey( key ) + '"></td>';

		if ( value ) {
			html += '<td data-th="' + advadsGamKvsi18n.value + '">' +
				'<code>' + value + '</code>' +
				'<input type="hidden" name="advanced_ad[gam][value][]" value="' + value + '">' +
				'</td>';
		} else if ( gamAdvancedAdsJS.kvTypes[type].html.indexOf( 'onarchives' ) !== 1 ) {
			const start = '<input type="hidden" name="advanced_ad[gam][value][]" value="" /><span class="description">';
			const end   = '</span><input type="hidden" name="advanced_ad[gam][onarchives][]"';
			const mkup  = archives
				? start + advadsGamKvsi18n['termsOnArchives'] + end + 'value="1" />'
				: start + advadsGamKvsi18n['termsNotOnArchives'] + end + 'value="0" />';
			html += '<td data-th="' + advadsGamKvsi18n.value + '">' + mkup + '</td>';
		} else {
			html += '<td data-th="' + advadsGamKvsi18n.value + '">' + gamAdvancedAdsJS.kvTypes[type].html + '<input type="hidden" name="advanced_ad[gam][onarchives][]" value="0" /></td>';
		}

		html += '<td><i class="dashicons dashicons-dismiss" title="' + gamAdvancedAdsJS.i18n['remove'] + '"></i></td></tr>';
		$( '#advads-gam-keyvalue-table tbody' ).append( $( html ) );
		if ( ['custom', 'postmeta', 'usermeta'].indexOf( type ) === - 1 ) {
			$( '#advads-gam-kv-type option[value="' + type + '"]' ).remove();
		}
		$( '#advads-gam-kv-type' ).val( 'custom' ).trigger( 'change' );
		enableKVPairBtn( false );
		$( '#advads-gam-keyvalue-inputs p.description' ).empty();
	}

	/**
	 * Enable (or disable) the add key-value pair button (on duplicate)
	 *
	 * @param {Boolean} [enable=true] Whether to enable or disable the button.
	 */
	function enableKVPairBtn( enable ) {
		if ( 'undefined' == typeof enable ) {
			enable = true;
		}
		const button = $( '#advads-gam-add-kvpair' );
		if ( false === enable ) {
			button.removeClass( 'button-primary' ).addClass( 'button-secondary' ).prop( 'disabled', true );
		} else {
			button.addClass( 'button-primary' ).removeClass( 'button-secondary' ).prop( 'disabled', false );
		}
	}

	/**
	 * Sanitize key or value entry
	 *
	 * @param {jQuery} input the text field.
	 * @param {String} type type of entry to sanitize.
	 */
	function sanitizeEntry( input, type ) {
		const entry   = input.val();
		let safeEntry = type === 'key' ? sanitizeKey( entry ) : sanitizeValue( entry );

		if ( ['postmeta', 'usermeta'].indexOf( $( '#advads-gam-kv-type' ).val() ) !== - 1 ) {
			safeEntry = sanitizeMeta( entry );
		}
		enableKVPairBtn( safeEntry !== '' );
		if ( safeEntry === '' ) {
			return;
		}
		input.siblings( 'p' ).remove();
		if ( safeEntry === entry ) {
			return;
		}
		input.after( $( '<p class="description">' + gamAdvancedAdsJS.i18n['willBeCreatedAs'] + ' <code>' + safeEntry + '</code></p>' ) );
	}

	// Events binding.

	// "enter" key event
	$(document).on('keydown', '#advads-gam-kv-key-input, #advads-gam-kv-value-input', function (ev) {
		if ( ev.key === 'Enter' ) {
			ev.preventDefault();
			if ( $( this ).val() === '' ) {
				return;
			}
			$( '#advads-gam-add-kvpair' ).trigger( 'click' );
		}
	});

	// Remove a key value pair.
	$(document).on('click', '#advads-gam-keyvalue-div table tbody tr .dashicons-dismiss', function () {
		var type = $(this).closest('tr').find('input[name="advanced_ad\[gam\]\[type\]\[\]"]').val();
		if ( ['custom', 'postmeta', 'usermeta'].indexOf( type ) === - 1 ) {
			$('#advads-gam-kv-type').append('<option value="' + type + '">' + gamAdvancedAdsJS.kvTypes[type].name + '</option>');
		}
		$(this).closest('tr').remove();
	});

	// Add key button.
	$(document).on('click', '#advads-gam-add-kvpair', function (ev) {
		ev.preventDefault();
		const keyInput = $( '#advads-gam-kv-key-input' );
		const key        = keyInput.val();
		if ( ! key ) {
			return;
		}
		const valueInput = $( '#advads-gam-kv-value-input' );
		const typeInput  = $( '#advads-gam-kv-type' );
		let value        = null;
		if ( valueInput.length ) {
			value = valueInput.val();
			if ( ! value ) {
				return;
			}
			value = ['postmeta', 'usermeta'].indexOf( typeInput.val() ) !== - 1 ? sanitizeMeta( value ) : sanitizeValue( value );
			valueInput.val( '' );
		}
		keyInput.val( '' );
		let archives         = false;
		const onArchiveInput = $( '#advads-gam-kv-value-td .onarchives' );
		if ( onArchiveInput.length && onArchiveInput.prop( 'checked' ) ) {
			archives = true;
		}
		addKVPair( typeInput.val(), key, value, archives );
	});

	// key type change.
	$(document).on('change', '#advads-gam-kv-type', function () {
		var type = $(this).val();
		var valueColumn = gamAdvancedAdsJS.kvTypes[type].html;
		$('#advads-gam-kv-value-td').html(valueColumn);
	});

	// Key input change.
	$(document).on('keyup', '#advads-gam-kv-key-input', function () {
		sanitizeEntry( $( this ), 'key' );
	} );

	// Custom key value change.
	$( document ).on( 'keyup', '#advads-gam-kv-value-input', function () {
		$( '#advads-gam-add-kvpair' ).prop( 'disabled', true );
		sanitizeEntry( $( this ), 'value' );
	} );

})(window.jQuery);
