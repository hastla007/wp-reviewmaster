/**
 * Review Captain – click-to-load for third-party embeds (TEC-013).
 *
 * Only enqueued when EmbedPolicy::click_to_load() rendered a placeholder. No dependencies,
 * no third-party request before the visitor clicks. Without JS the fallback link works.
 */
( function () {
	'use strict';

	var ALLOWED = [
		'youtube-nocookie.com',
		'www.youtube-nocookie.com',
		'player.vimeo.com',
		'widget.trustpilot.com',
		'widgets.trustedshops.com'
	];

	function load( placeholder ) {
		var src = placeholder.getAttribute( 'data-rc-embed-src' ) || '';
		var url;
		try {
			url = new URL( src );
		} catch ( e ) {
			return;
		}
		if ( 'https:' !== url.protocol || ( url.port && '443' !== url.port ) || url.username || url.password || ALLOWED.indexOf( url.hostname ) === -1 ) {
			return;
		}
		var frame = document.createElement( 'iframe' );
		frame.src = url.href;
		frame.title = placeholder.getAttribute( 'data-rc-embed-title' ) || '';
		frame.setAttribute( 'sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups' );
		frame.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		frame.setAttribute( 'allow', 'fullscreen; picture-in-picture' );
		frame.setAttribute( 'loading', 'lazy' );
		frame.className = 'rc-embed-frame';
		placeholder.replaceWith( frame );
		frame.focus();
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target && event.target.closest ? event.target.closest( '.rc-embed-placeholder__load' ) : null;
		if ( ! button ) {
			return;
		}
		var placeholder = button.closest( '.rc-embed-placeholder' );
		if ( placeholder ) {
			event.preventDefault();
			load( placeholder );
		}
	} );
}() );
