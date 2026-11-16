/**
 * Click-to-load for external embeds (TEC-013). No request to the third party before the visitor clicks.
 * Only URLs rendered by the server (already checked against the host allowlist) are used.
 */
( function () {
	'use strict';
	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest ? e.target.closest( '.rc-embed-load' ) : null;
		if ( ! button ) {
			return;
		}
		var box = button.closest( '.rc-embed-placeholder' );
		var url = box && box.getAttribute( 'data-rc-embed' );
		if ( ! url || url.indexOf( 'https://' ) !== 0 ) {
			return;
		}
		var frame = document.createElement( 'iframe' );
		frame.src = url;
		frame.title = box.getAttribute( 'data-rc-title' ) || '';
		frame.className = 'rc-embed';
		frame.loading = 'lazy';
		frame.referrerPolicy = 'strict-origin-when-cross-origin';
		frame.allowFullscreen = true;
		box.replaceWith( frame );
		frame.focus();
	} );
}() );
