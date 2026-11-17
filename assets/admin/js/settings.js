/**
 * Divi Buddy settings page: tabs, color pickers, size previews, reset buttons, unsaved-changes warning.
 */
( function ( $ ) {
	'use strict';

	var i18n = ( window.DiviBuddy && window.DiviBuddy.i18n ) || {};
	var TAB_KEY = 'dbuddy-active-tab';
	var dirty = false;

	function storageGet( key ) {
		try {
			return window.localStorage.getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	function storageSet( key, value ) {
		try {
			window.localStorage.setItem( key, value );
		} catch ( e ) {}
	}

	function markDirty() {
		dirty = true;
		$( '.dbuddy-unsaved' ).prop( 'hidden', false );
	}

	/* Tabs ---------------------------------------------------------------- */

	function showTab( key ) {
		var $tabs = $( '.dbuddy-tab' );
		if ( ! $tabs.filter( '[data-tab="' + key + '"]' ).length ) {
			key = $tabs.first().data( 'tab' );
		}
		$tabs.each( function () {
			var active = $( this ).data( 'tab' ) === key;
			$( this ).toggleClass( 'is-active', active ).attr( 'aria-selected', active ? 'true' : 'false' );
		} );
		$( '.dbuddy-panel' ).each( function () {
			$( this ).prop( 'hidden', $( this ).data( 'panel' ) !== key );
		} );
		storageSet( TAB_KEY, key );
	}

	/* Previews ------------------------------------------------------------ */

	function updatePreview( $field ) {
		var $preview = $field.find( '.dbuddy-preview' );
		if ( ! $preview.length ) {
			return;
		}
		[ 'desktop', 'mobile' ].forEach( function ( part ) {
			var size = parseFloat( $field.find( 'input[data-part="' + part + '"]' ).val() );
			if ( ! isNaN( size ) ) {
				$preview.find( '.dbuddy-preview-' + part ).css( 'font-size', Math.min( size, 120 ) + 'px' );
			}
		} );
	}

	/* Reset a tab --------------------------------------------------------- */

	function resetField( $field ) {
		var def = $field.data( 'default' ); // jQuery parses the JSON for us.
		var $color = $field.find( '.dbuddy-color' );

		if ( $color.length ) {
			$color.wpColorPicker( 'color', String( def ) );
		} else if ( def !== null && typeof def === 'object' ) {
			$field.find( 'input[data-part="desktop"]' ).val( def.desktop );
			$field.find( 'input[data-part="mobile"]' ).val( def.mobile );
		} else {
			var $input = $field.find( 'select, input' ).first();
			if ( $input.is( 'select' ) && ! $input.find( 'option[value="' + String( def ).replace( /"/g, '\\"' ) + '"]' ).length ) {
				$input.append( $( '<option>' ).val( def ).text( def ) );
			}
			$input.val( String( def ) );
		}
		updatePreview( $field );
	}

	$( function () {
		var $form = $( '#dbuddy-form' );
		if ( ! $form.length ) {
			return;
		}

		// Tabs.
		$( '.dbuddy-tab' ).on( 'click', function () {
			showTab( $( this ).data( 'tab' ) );
		} );
		showTab( storageGet( TAB_KEY ) );

		// Color pickers.
		$( '.dbuddy-color' ).wpColorPicker( {
			change: markDirty,
			clear: markDirty,
		} );

		// Any other change.
		$form.on( 'input change', 'input, select', function () {
			markDirty();
			updatePreview( $( this ).closest( '.dbuddy-field' ) );
		} );

		// Per-tab reset (only changes the form; the user still has to save).
		$( '.dbuddy-reset-tab' ).on( 'click', function () {
			if ( ! window.confirm( i18n.confirmResetTab ) ) {
				return;
			}
			$( '.dbuddy-panel[data-panel="' + $( this ).data( 'tab' ) + '"] .dbuddy-field' ).each( function () {
				resetField( $( this ) );
			} );
			markDirty();
		} );

		// Reset everything (server side).
		$( '#dbuddy-reset-all' ).on( 'submit', function ( event ) {
			if ( ! window.confirm( i18n.confirmResetAll ) ) {
				event.preventDefault();
			}
		} );

		// Warn before leaving with unsaved changes.
		$form.on( 'submit', function () {
			dirty = false;
		} );
		$( window ).on( 'beforeunload', function () {
			if ( dirty ) {
				return i18n.unsaved;
			}
		} );
	} );
} )( jQuery );
