<?php
/**
 * Plugin metadata checks for CI (RC-033): header fields, version consistency, text domain usage.
 */
$root   = dirname( __DIR__ );
$main   = file_get_contents( $root . '/review-captain.php' );
$readme = file_get_contents( $root . '/readme.txt' );
$errors = array();
foreach ( array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain', 'License' ) as $h ) {
	if ( ! preg_match( '/^\s*\*\s*' . preg_quote( $h, '/' ) . ':\s*(.+)$/m', $main ) ) {
		$errors[] = "Missing header: $h";
	}
}
preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main, $hv );
preg_match( "/define\( 'REVIEW_CAPTAIN_VERSION', '([^']+)' \)/", $main, $cv );
if ( ( $hv[1] ?? '' ) !== ( $cv[1] ?? '' ) ) {
	$errors[] = 'Header version and REVIEW_CAPTAIN_VERSION differ';
}
// Public contract version (RC-2, suite spec R-3.1): SemVer, independent of the plugin version.
if ( ! preg_match( "/define\( 'REVIEW_CAPTAIN_CONTRACT_VERSION', '\d+\.\d+\.\d+' \);/", $main ) ) {
	$errors[] = 'REVIEW_CAPTAIN_CONTRACT_VERSION missing or not MAJOR.MINOR.PATCH';
}
if ( ! preg_match( '/^\s*\*\s*Text Domain:\s*review-captain\s*$/mi', $main ) ) {
	$errors[] = 'Text domain must be review-captain';
}
foreach ( array( 'Requires at least', 'Requires PHP', 'Stable tag', 'License' ) as $h ) {
	if ( ! preg_match( '/^' . preg_quote( $h, '/' ) . ':/m', $readme ) ) {
		$errors[] = "readme.txt missing: $h";
	}
}
// Minimum platform (SET-RC-P6): one value everywhere – plugin header, readme.txt, the runtime
// constants, Support\Requirements, composer.json and the required (non-experimental) CI matrix.
$meta = static function ( string $pattern, string $text ): string {
	return preg_match( $pattern, $text, $m ) ? trim( $m[1] ) : '';
};
$requirements = (string) file_get_contents( $root . '/src/Support/Requirements.php' );
$composer     = json_decode( (string) file_get_contents( $root . '/composer.json' ), true );
$ci           = is_file( $root . '/.github/workflows/ci.yml' ) ? (string) file_get_contents( $root . '/.github/workflows/ci.yml' ) : '';
$php_min      = array(
	'review-captain.php header'  => $meta( '/^\s*\*\s*Requires PHP:\s*(\S+)/m', $main ),
	'readme.txt'                 => $meta( '/^Requires PHP:\s*(\S+)/m', $readme ),
	'REVIEW_CAPTAIN_MIN_PHP'     => $meta( "/define\( 'REVIEW_CAPTAIN_MIN_PHP', '([^']+)' \)/", $main ),
	'Requirements::MIN_PHP'      => $meta( "/const MIN_PHP\s*=\s*'([^']+)'/", $requirements ),
	'composer.json require.php'  => $meta( '/^>=\s*(\S+)$/', (string) ( $composer['require']['php'] ?? '' ) ),
	'ci.yml required PHP matrix' => $meta( "/^\s*php:\s*\[\s*'([^']+)'/m", $ci ),
);
$wp_min = array(
	'review-captain.php header' => $meta( '/^\s*\*\s*Requires at least:\s*(\S+)/m', $main ),
	'readme.txt'                => $meta( '/^Requires at least:\s*(\S+)/m', $readme ),
	'REVIEW_CAPTAIN_MIN_WP'     => $meta( "/define\( 'REVIEW_CAPTAIN_MIN_WP', '([^']+)' \)/", $main ),
);
$platform = array(
	'PHP'       => $php_min,
	'WordPress' => $wp_min,
);
foreach ( $platform as $what => $values ) {
	if ( in_array( '', $values, true ) || 1 !== count( array_unique( $values ) ) ) {
		$list = array();
		foreach ( $values as $where => $value ) {
			$list[] = $where . ' = ' . ( '' === $value ? '(missing)' : $value );
		}
		$errors[] = "Minimum $what differs or is missing: " . implode( ', ', $list );
	}
}
if ( $meta( '/^Stable tag:\s*(\S+)/m', $readme ) !== ( $hv[1] ?? '' ) ) {
	$errors[] = 'readme.txt Stable tag differs from the plugin version';
}
// Every translation call in src/ and templates/ must use the review-captain domain.
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) );
$files = iterator_to_array( $it );
if ( is_dir( $root . '/templates' ) ) {
	$files = array_merge( $files, iterator_to_array( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/templates' ) ) ) );
}
foreach ( $files as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}
	$code = file_get_contents( $file->getPathname() );
	if ( preg_match_all( "/\b(?:__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e|esc_html_x)\(\s*'(?:[^'\\\\]|\\\\.)*'\s*(?:,\s*'(?:[^'\\\\]|\\\\.)*'\s*)?(?:,\s*[^,)]+)?\s*,\s*'([^']+)'\s*\)/", $code, $m ) ) {
		foreach ( $m[1] as $domain ) {
			if ( 'review-captain' !== $domain ) {
				$errors[] = $file->getPathname() . ": wrong text domain '$domain'";
			}
		}
	}
}
if ( $errors ) {
	fwrite( STDERR, implode( PHP_EOL, $errors ) . PHP_EOL );
	exit( 1 );
}
echo "Plugin metadata OK\n";
