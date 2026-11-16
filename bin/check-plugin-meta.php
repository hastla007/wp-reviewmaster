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
if ( ! preg_match( '/^\s*\*\s*Text Domain:\s*review-captain\s*$/mi', $main ) ) {
	$errors[] = 'Text domain must be review-captain';
}
foreach ( array( 'Requires at least', 'Requires PHP', 'Stable tag', 'License' ) as $h ) {
	if ( ! preg_match( '/^' . preg_quote( $h, '/' ) . ':/m', $readme ) ) {
		$errors[] = "readme.txt missing: $h";
	}
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
