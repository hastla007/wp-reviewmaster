<?php
/**
 * Dependency-free POT generator (RC-034). Prefer `wp i18n make-pot` (bin/make-pot.sh) when WP-CLI is
 * available; this fallback scans PHP (and block editor JS) for gettext calls with the review-captain domain.
 *
 * Usage: php bin/make-pot.php [output]
 */

$root = dirname( __DIR__ );
$out  = $argv[1] ?? $root . '/languages/review-captain.pot';
$dirs = array( 'review-captain.php', 'uninstall.php', 'src', 'templates', 'blocks' );

$entries = array();
$add     = static function ( string $msgid, ?string $plural, ?string $ctx, string $ref, ?string $comment ) use ( &$entries ): void {
	$key = ( $ctx ?? '' ) . "\x04" . $msgid;
	if ( ! isset( $entries[ $key ] ) ) {
		$entries[ $key ] = array( 'msgid' => $msgid, 'plural' => $plural, 'ctx' => $ctx, 'refs' => array(), 'comments' => array() );
	}
	$entries[ $key ]['refs'][] = $ref;
	if ( $comment ) {
		$entries[ $key ]['comments'][ $comment ] = true;
	}
};

$files = array();
foreach ( $dirs as $d ) {
	$path = $root . '/' . $d;
	if ( is_file( $path ) ) {
		$files[] = $path;
	} elseif ( is_dir( $path ) ) {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( in_array( $f->getExtension(), array( 'php', 'js' ), true ) ) {
				$files[] = $f->getPathname();
			}
		}
	}
}
sort( $files );

$str = '\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"';
$fns = array(
	'single' => array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' ),
	'ctx'    => array( '_x', '_ex', 'esc_html_x', 'esc_attr_x' ),
	'plural' => array( '_n' ),
);
$unq = static fn( $m1, $m2 ) => stripcslashes( '' !== $m1 ? $m1 : $m2 );

foreach ( $files as $file ) {
	$code = file_get_contents( $file );
	$rel  = ltrim( substr( $file, strlen( $root ) ), '/' );
	$line_of = static fn( int $offset ) => substr_count( $code, "\n", 0, $offset ) + 1;
	$translator = static function ( int $offset ) use ( $code ): ?string {
		$before = substr( $code, max( 0, $offset - 300 ), min( 300, $offset ) );
		return preg_match( '/translators:\s*([^\n*]+?)\s*(?:\*\/)?\s*$/s', $before, $m ) ? trim( $m[1] ) : null;
	};
	foreach ( $fns['single'] as $fn ) {
		preg_match_all( '/(?<![\w>$])' . preg_quote( $fn, '/' ) . '\(\s*(?:' . $str . ')\s*,\s*[\'"]review-captain[\'"]\s*\)/', $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		foreach ( $m as $hit ) {
			$add( $unq( $hit[1][0] ?? '', $hit[2][0] ?? '' ), null, null, $rel . ':' . $line_of( $hit[0][1] ), $translator( $hit[0][1] ) );
		}
	}
	foreach ( $fns['ctx'] as $fn ) {
		preg_match_all( '/(?<![\w>$])' . preg_quote( $fn, '/' ) . '\(\s*(?:' . $str . ')\s*,\s*(?:' . $str . ')\s*,\s*[\'"]review-captain[\'"]\s*\)/', $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		foreach ( $m as $hit ) {
			$add( $unq( $hit[1][0] ?? '', $hit[2][0] ?? '' ), null, $unq( $hit[3][0] ?? '', $hit[4][0] ?? '' ), $rel . ':' . $line_of( $hit[0][1] ), $translator( $hit[0][1] ) );
		}
	}
	preg_match_all( '/(?<![\w>$])_n\(\s*(?:' . $str . ')\s*,\s*(?:' . $str . ')\s*,[^,]+,\s*[\'"]review-captain[\'"]\s*\)/', $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
	foreach ( $m as $hit ) {
		$add( $unq( $hit[1][0] ?? '', $hit[2][0] ?? '' ), $unq( $hit[3][0] ?? '', $hit[4][0] ?? '' ), null, $rel . ':' . $line_of( $hit[0][1] ), $translator( $hit[0][1] ) );
	}
}

$esc = static fn( string $s ) => '"' . str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $s ) . '"';
$pot = "# Copyright (C) Captain Suite\n# This file is distributed under the GPL-2.0-or-later.\nmsgid \"\"\nmsgstr \"\"\n"
	. "\"Project-Id-Version: Review Captain\\n\"\n\"Report-Msgid-Bugs-To: https://github.com/hastla007/wp-reviewmaster/issues\\n\"\n"
	. "\"MIME-Version: 1.0\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n"
	. "\"X-Domain: review-captain\\n\"\n\n";
foreach ( $entries as $e ) {
	foreach ( array_keys( $e['comments'] ) as $c ) {
		$pot .= '#. translators: ' . $c . "\n";
	}
	$pot .= '#: ' . implode( ' ', array_slice( array_unique( $e['refs'] ), 0, 10 ) ) . "\n";
	if ( null !== $e['ctx'] ) {
		$pot .= 'msgctxt ' . $esc( $e['ctx'] ) . "\n";
	}
	$pot .= 'msgid ' . $esc( $e['msgid'] ) . "\n";
	if ( null !== $e['plural'] ) {
		$pot .= 'msgid_plural ' . $esc( $e['plural'] ) . "\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
	} else {
		$pot .= "msgstr \"\"\n\n";
	}
}
if ( ! is_dir( dirname( $out ) ) ) {
	mkdir( dirname( $out ), 0775, true );
}
file_put_contents( $out, $pot );
fwrite( STDOUT, count( $entries ) . " strings → $out\n" );
