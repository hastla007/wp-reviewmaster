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

	// RC-077: official script widgets (Trusted Shops trustbadge). Only this host and path shape.
	var SCRIPT_HOSTS = [ 'widgets.trustedshops.com' ];
	var SCRIPT_PATH = /^\/js\/X[0-9A-F]{32}\.js$/;

	function loadScript( placeholder, src ) {
		var url;
		try {
			url = new URL( src );
		} catch ( e ) {
			return;
		}
		if ( 'https:' !== url.protocol || ( url.port && '443' !== url.port ) || url.username || url.password || url.search || SCRIPT_HOSTS.indexOf( url.hostname ) === -1 || ! SCRIPT_PATH.test( url.pathname ) ) {
			return;
		}
		var script = document.createElement( 'script' );
		script.src = url.href;
		script.async = true;
		script.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		document.body.appendChild( script );
		var fallback = placeholder.querySelector( '.rc-embed-placeholder__fallback' );
		var notice = placeholder.querySelector( '.rc-embed-placeholder__notice' );
		var button = placeholder.querySelector( '.rc-embed-placeholder__load' );
		if ( button ) {
			button.remove();
		}
		if ( notice ) {
			notice.remove();
		}
		placeholder.classList.add( 'is-loaded' );
		if ( fallback ) {
			fallback.focus();
		}
	}

	function load( placeholder ) {
		var scriptSrc = placeholder.getAttribute( 'data-rc-embed-script' );
		if ( scriptSrc ) {
			loadScript( placeholder, scriptSrc );
			return;
		}
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
