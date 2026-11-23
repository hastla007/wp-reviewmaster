/**
 * Review Captain – built-in proof-of-work spam check (SET-RC-11).
 *
 * Port of Form Captain's assets/src/proof-of-work.js (wp-formmaster commit ca81401): same data
 * format, see docs/spam-proof-of-work.md. Own SHA-256 (no crypto.subtle), so it also works on
 * sites without HTTPS. No external request, no cookie, no storage.
 *
 * Flow: on the first interaction with a form the browser fetches a challenge from admin-ajax.php
 * (uncached, so cached pages work), searches the number in small slices (the page stays
 * responsive) and writes the solution into the hidden field. When the visitor submits before the
 * search is done, submission waits and then continues automatically. Status text goes to an
 * aria-live region; there is nothing to click or solve.
 */
( function () {
	'use strict';

	var cfg = window.reviewCaptainPow || {};
	var i18n = cfg.i18n || {};

	/* ---- SHA-256 (FIPS 180-4) for ASCII input, hex output. ---- */
	var K = [
		0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
		0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
		0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
		0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
		0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
		0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
		0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
		0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
	];
	var W = new Int32Array( 64 );

	function sha256( str ) {
		var len = str.length;
		var words = ( ( len + 9 + 63 ) >> 6 ) << 4;
		var m = new Int32Array( words );
		var i;
		for ( i = 0; i < len; i++ ) {
			m[ i >> 2 ] |= ( str.charCodeAt( i ) & 0xff ) << ( 24 - ( ( i & 3 ) << 3 ) );
		}
		m[ len >> 2 ] |= 0x80 << ( 24 - ( ( len & 3 ) << 3 ) );
		m[ words - 1 ] = len * 8;
		var h0 = 0x6a09e667, h1 = 0xbb67ae85, h2 = 0x3c6ef372, h3 = 0xa54ff53a;
		var h4 = 0x510e527f, h5 = 0x9b05688c, h6 = 0x1f83d9ab, h7 = 0x5be0cd19;
		for ( var off = 0; off < words; off += 16 ) {
			var a = h0, b = h1, c = h2, d = h3, e = h4, f = h5, g = h6, h = h7, t1, t2, x, y;
			for ( i = 0; i < 64; i++ ) {
				if ( i < 16 ) {
					W[ i ] = m[ off + i ];
				} else {
					x = W[ i - 15 ];
					y = W[ i - 2 ];
					W[ i ] = ( ( ( x >>> 7 ) | ( x << 25 ) ) ^ ( ( x >>> 18 ) | ( x << 14 ) ) ^ ( x >>> 3 ) ) +
						W[ i - 7 ] +
						( ( ( y >>> 17 ) | ( y << 15 ) ) ^ ( ( y >>> 19 ) | ( y << 13 ) ) ^ ( y >>> 10 ) ) +
						W[ i - 16 ] | 0;
				}
				t1 = h + ( ( ( e >>> 6 ) | ( e << 26 ) ) ^ ( ( e >>> 11 ) | ( e << 21 ) ) ^ ( ( e >>> 25 ) | ( e << 7 ) ) ) +
					( ( e & f ) ^ ( ~e & g ) ) + K[ i ] + W[ i ] | 0;
				t2 = ( ( ( a >>> 2 ) | ( a << 30 ) ) ^ ( ( a >>> 13 ) | ( a << 19 ) ) ^ ( ( a >>> 22 ) | ( a << 10 ) ) ) +
					( ( a & b ) ^ ( a & c ) ^ ( b & c ) ) | 0;
				h = g;
				g = f;
				f = e;
				e = d + t1 | 0;
				d = c;
				c = b;
				b = a;
				a = t1 + t2 | 0;
			}
			h0 = h0 + a | 0;
			h1 = h1 + b | 0;
			h2 = h2 + c | 0;
			h3 = h3 + d | 0;
			h4 = h4 + e | 0;
			h5 = h5 + f | 0;
			h6 = h6 + g | 0;
			h7 = h7 + h | 0;
		}
		var out = '';
		var hs = [ h0, h1, h2, h3, h4, h5, h6, h7 ];
		for ( i = 0; i < 8; i++ ) {
			out += ( '0000000' + ( hs[ i ] >>> 0 ).toString( 16 ) ).slice( -8 );
		}
		return out;
	}

	/* ---- Challenge + search ---- */
	function fetchChallenge() {
		var body = 'action=' + encodeURIComponent( cfg.action || 'review_captain_pow_challenge' );
		return window.fetch( cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		} ).then( function ( r ) {
			return r.json();
		} ).then( function ( json ) {
			if ( ! json || ! json.success || ! json.data || 'SHA-256' !== json.data.algorithm ) {
				throw new Error( 'challenge' );
			}
			return json.data;
		} );
	}

	function search( ch ) {
		return new Promise( function ( resolve, reject ) {
			var n = 0;
			var max = parseInt( ch.maxnumber, 10 ) || 0;
			function slice() {
				var stop = Math.min( max, n + 4000 );
				for ( ; n <= stop; n++ ) {
					if ( sha256( ch.salt + n ) === ch.challenge ) {
						resolve( n );
						return;
					}
				}
				if ( n > max ) {
					reject( new Error( 'unsolved' ) );
					return;
				}
				window.setTimeout( slice, 0 );
			}
			slice();
		} );
	}

	function encode( ch, number ) {
		return window.btoa( JSON.stringify( {
			algorithm: 'SHA-256',
			challenge: ch.challenge,
			number: number,
			salt: ch.salt,
			signature: ch.signature
		} ) );
	}

	/* ---- Per form ---- */
	function setup( box ) {
		var form = box.closest( 'form' );
		var input = box.querySelector( 'input[name="rc_pow"]' );
		var status = box.querySelector( '[role="status"]' );
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
			// Keep a minute of margin so a solution does not expire on the way to the server.
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
			running = fetchChallenge().then( function ( ch ) {
				expires = parseInt( ch.expires, 10 ) || ( Date.now() / 1000 + 600 );
				return search( ch ).then( function ( number ) {
					input.value = encode( ch, number );
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
		if ( ! window.fetch || ! window.Promise || ! cfg.endpoint ) {
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
