/**
 * Self-hosted proof-of-work solver (SET-FC-05 a). No dependencies, no network, no cookies.
 * Format and rules: docs/spam-proof-of-work.md. Other plugins copy this file and rename the
 * global (window.reviewCaptainPow).
 *
 * solve( challenge ) → Promise<string|null>: base64 JSON payload for the hidden field, or null.
 * Works without a secure context (own SHA-256); uses Web Crypto when available. Runs in small
 * slices so the page stays responsive; there is nothing for the visitor to do.
 * ES2019.
 */
( function () {
	'use strict';

	var K = [
		0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
		0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
		0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
		0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
		0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
		0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
		0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
		0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
	];

	/** SHA-256 of an ASCII string as lowercase hex. */
	function sha256( ascii ) {
		var H = [ 0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19 ];
		var len = ascii.length;
		var words = [];
		var i;
		for ( i = 0; i < len; i++ ) {
			words[ i >> 2 ] |= ( ascii.charCodeAt( i ) & 0xff ) << ( 24 - ( i % 4 ) * 8 );
		}
		words[ len >> 2 ] |= 0x80 << ( 24 - ( len % 4 ) * 8 );
		var total = ( ( ( len + 8 ) >> 6 ) + 1 ) * 16;
		for ( i = words.length; i < total; i++ ) {
			words[ i ] = words[ i ] | 0;
		}
		words[ total - 1 ] = len * 8;
		var w = new Array( 64 );
		for ( var j = 0; j < total; j += 16 ) {
			var a = H[ 0 ], b = H[ 1 ], c = H[ 2 ], d = H[ 3 ], e = H[ 4 ], f = H[ 5 ], g = H[ 6 ], h = H[ 7 ];
			for ( i = 0; i < 64; i++ ) {
				if ( i < 16 ) {
					w[ i ] = words[ j + i ] | 0;
				} else {
					var x = w[ i - 15 ], y = w[ i - 2 ];
					var s0 = ( ( x >>> 7 ) | ( x << 25 ) ) ^ ( ( x >>> 18 ) | ( x << 14 ) ) ^ ( x >>> 3 );
					var s1 = ( ( y >>> 17 ) | ( y << 15 ) ) ^ ( ( y >>> 19 ) | ( y << 13 ) ) ^ ( y >>> 10 );
					w[ i ] = ( w[ i - 16 ] + s0 + w[ i - 7 ] + s1 ) | 0;
				}
				var S1 = ( ( e >>> 6 ) | ( e << 26 ) ) ^ ( ( e >>> 11 ) | ( e << 21 ) ) ^ ( ( e >>> 25 ) | ( e << 7 ) );
				var t1 = ( h + S1 + ( ( e & f ) ^ ( ~e & g ) ) + K[ i ] + w[ i ] ) | 0;
				var S0 = ( ( a >>> 2 ) | ( a << 30 ) ) ^ ( ( a >>> 13 ) | ( a << 19 ) ) ^ ( ( a >>> 22 ) | ( a << 10 ) );
				var t2 = ( S0 + ( ( a & b ) ^ ( a & c ) ^ ( b & c ) ) ) | 0;
				h = g;
				g = f;
				f = e;
				e = ( d + t1 ) | 0;
				d = c;
				c = b;
				b = a;
				a = ( t1 + t2 ) | 0;
			}
			H[ 0 ] = ( H[ 0 ] + a ) | 0;
			H[ 1 ] = ( H[ 1 ] + b ) | 0;
			H[ 2 ] = ( H[ 2 ] + c ) | 0;
			H[ 3 ] = ( H[ 3 ] + d ) | 0;
			H[ 4 ] = ( H[ 4 ] + e ) | 0;
			H[ 5 ] = ( H[ 5 ] + f ) | 0;
			H[ 6 ] = ( H[ 6 ] + g ) | 0;
			H[ 7 ] = ( H[ 7 ] + h ) | 0;
		}
		var out = '';
		for ( i = 0; i < 8; i++ ) {
			out += ( '00000000' + ( H[ i ] >>> 0 ).toString( 16 ) ).slice( -8 );
		}
		return out;
	}

	function payload( challenge, number ) {
		return window.btoa( JSON.stringify( {
			algorithm: challenge.algorithm,
			challenge: challenge.challenge,
			number: number,
			salt: challenge.salt,
			signature: challenge.signature,
		} ) );
	}

	function solve( challenge ) {
		if ( ! challenge || challenge.algorithm !== 'SHA-256' || typeof challenge.salt !== 'string' || ! /^[0-9a-f]{64}$/.test( challenge.challenge || '' ) ) {
			return Promise.resolve( null );
		}
		var max = Math.min( parseInt( challenge.maxnumber, 10 ) || 0, 1000000 );
		var n = 0;
		return new Promise( function ( resolve ) {
			function slice() {
				var end = Math.min( n + 2000, max );
				for ( ; n <= end; n++ ) {
					if ( sha256( challenge.salt + n ) === challenge.challenge ) {
						resolve( payload( challenge, n ) );
						return;
					}
				}
				if ( n > max ) {
					resolve( null );
					return;
				}
				setTimeout( slice, 0 );
			}
			slice();
		} );
	}

	window.reviewCaptainPow = { solve: solve, sha256: sha256 };
}() );
