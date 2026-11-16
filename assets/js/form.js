/**
 * Progressive enhancement for the review form: submits via REST with a fresh nonce (works with page caches).
 * Without JavaScript the form posts to admin-post.php.
 */
( function () {
	'use strict';
	var cfg = window.reviewCaptainForm;
	if ( ! cfg || ! window.fetch || ! window.FormData ) {
		return;
	}
	document.querySelectorAll( '.rc-form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			if ( ! form.checkValidity() ) {
				return; // Let the browser show validation messages.
			}
			e.preventDefault();
			var live = form.querySelector( '.rc-form__live' );
			var button = form.querySelector( 'button[type="submit"]' );
			button.disabled = true;
			fetch( cfg.nonceUrl, { credentials: 'omit', cache: 'no-store' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( n ) {
					var body = new FormData( form );
					body.set( '_rc_nonce', n.nonce );
					return fetch( cfg.endpoint, { method: 'POST', body: body, credentials: 'omit' } );
				} )
				.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, json: j }; } ); } )
				.then( function ( res ) {
					if ( res.ok && res.json.success ) {
						form.reset();
						live.textContent = cfg.thanks;
						live.className = 'rc-form__live rc-notice rc-notice--ok';
					} else {
						var msgs = res.json && res.json.errors ? Object.values( res.json.errors ) : [];
						live.textContent = msgs.length ? msgs.join( ' ' ) : cfg.error;
						live.className = 'rc-form__live rc-notice rc-notice--error';
					}
				} )
				.catch( function () {
					live.textContent = cfg.error;
					live.className = 'rc-form__live rc-notice rc-notice--error';
				} )
				.finally( function () { button.disabled = false; } );
		} );
	} );
}() );
