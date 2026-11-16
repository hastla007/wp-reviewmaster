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
} )();
