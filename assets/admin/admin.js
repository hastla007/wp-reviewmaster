/* Review Captain – optional progressive enhancement (copy button). Everything works without JS. */
( function () {
	'use strict';
	document.querySelectorAll( '.rc-admin-copy' ).forEach( function ( button ) {
		var target = document.getElementById( button.getAttribute( 'data-target' ) );
		if ( ! target || ! navigator.clipboard ) {
			return;
		}
		button.hidden = false;
		button.addEventListener( 'click', function () {
			var status = button.parentNode.querySelector( '.rc-admin-copy-status' );
			navigator.clipboard.writeText( target.value ).then( function () {
				if ( status ) {
					status.textContent = button.getAttribute( 'data-done' ) || '✓';
				}
			} );
		} );
	} );
	// SET-RC-P5: WordPress moves notices below the page title after load, which drops the autofocus of
	// the settings error list. Focus it again once the page has settled, so keyboard and screen reader
	// users start at the list of fields to fix.
	window.addEventListener( 'load', function () {
		var errors = document.getElementById( 'rc-settings-errors' );
		if ( errors ) {
			errors.focus();
		}
	} );
} )();
