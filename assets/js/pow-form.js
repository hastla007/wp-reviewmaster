/**
 * Review Captain – form side of the built-in proof-of-work spam check (SET-RC-11).
 *
 * The solver is assets/js/proof-of-work.js (copied from Form Captain, global window.reviewCaptainPow);
 * this file only connects it to the review, report and question forms. Format and rules:
 * docs/spam-proof-of-work.md.
 *
 * On the first interaction with a form the browser fetches a challenge for the form's scope from
 * admin-ajax.php (uncached, so cached pages work), solves it in the background and writes the
 * solution into the hidden field `_rc_pow`. When the visitor submits before the search is done,
 * submission waits and then continues as a normal POST. Status text goes to an aria-live region;
 * there is nothing to click or solve. No external request, no cookie, no storage.
 */
( function () {
	'use strict';

	var cfg = window.reviewCaptainPowForm || {};
	var i18n = cfg.i18n || {};

	function fetchChallenge( scope ) {
		var body = 'action=' + encodeURIComponent( cfg.action || 'review_captain_pow_challenge' ) +
			'&scope=' + encodeURIComponent( scope );
		return window.fetch( cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		} ).then( function ( r ) {
			return r.json();
		} ).then( function ( json ) {
			if ( ! json || ! json.success || ! json.data ) {
				throw new Error( 'challenge' );
			}
			return json.data;
		} );
	}

	function setup( box ) {
		var form = box.closest( 'form' );
		var input = box.querySelector( 'input[name="_rc_pow"]' );
		var status = box.querySelector( '[role="status"]' );
		var scope = box.getAttribute( 'data-rc-pow-scope' ) || '';
		if ( ! form || ! input || form.getAttribute( 'data-rc-pow-ready' ) ) {
			return;
		}
		form.setAttribute( 'data-rc-pow-ready', '1' );
		var running = null;
		var expires = 0;
		var waiting = false;

		function say( key ) {
			if ( status ) {
				status.textContent = i18n[ key ] || '';
			}
		}

		function fresh() {
			// A minute of margin so a solution does not expire on the way to the server.
			return '' !== input.value && Date.now() / 1000 < expires - 60;
		}

		function start() {
			if ( fresh() ) {
				return Promise.resolve();
			}
			if ( running ) {
				return running;
			}
			input.value = '';
			say( 'working' );
			running = fetchChallenge( scope ).then( function ( ch ) {
				return window.reviewCaptainPow.solve( ch ).then( function ( payload ) {
					if ( ! payload ) {
						throw new Error( 'unsolved' );
					}
					expires = parseInt( ch.expires, 10 ) || 0;
					input.value = payload;
					say( 'ready' );
				} );
			} ).then( function () {
				running = null;
			}, function ( err ) {
				running = null;
				say( 'failed' );
				throw err;
			} );
			return running;
		}

		function warm() {
			start().catch( function () {} );
		}
		form.addEventListener( 'focusin', warm, { once: true } );
		form.addEventListener( 'pointerdown', warm, { once: true } );

		form.addEventListener( 'submit', function ( event ) {
			if ( fresh() ) {
				return;
			}
			event.preventDefault();
			if ( waiting ) {
				return;
			}
			waiting = true;
			say( 'sending' );
			start().then( function () {
				waiting = false;
				// form.submit() skips this handler and keeps the normal POST navigation.
				HTMLFormElement.prototype.submit.call( form );
			}, function () {
				waiting = false;
			} );
		} );
	}

	function init() {
		if ( ! window.fetch || ! window.Promise || ! window.reviewCaptainPow || ! cfg.endpoint ) {
			return; // Without support the server answers with the "JavaScript needed" message.
		}
		var boxes = document.querySelectorAll( '[data-rc-pow]' );
		for ( var i = 0; i < boxes.length; i++ ) {
			setup( boxes[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
