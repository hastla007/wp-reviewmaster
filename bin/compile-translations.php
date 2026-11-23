<?php
/**
 * Compile languages/*.po into .mo and block-editor JSON files without WP-CLI (RC-034, RC-138).
 * Also checks completeness and placeholder consistency against the POT.
 *
 *   php bin/compile-translations.php           # check + write .mo/.json
 *   php bin/compile-translations.php --check   # check only (CI): .mo/.json up to date, placeholders consistent
 *
 * JSON file names follow WordPress: review-captain-{locale}-{md5(relative script path)}.json.
 */

$root  = dirname( __DIR__ );
$check = in_array( '--check', $argv, true );

/**
 * Parse a PO/POT file.
 *
 * @return array<int, array{id: string, plural: ?string, str: list<string>, refs: list<string>, fuzzy: bool}>
 */
function rc_parse_po( string $file ): array {
	$entries = array();
	$cur     = null;
	$field   = null;
	$push    = static function () use ( &$cur, &$entries ) {
		if ( null !== $cur && isset( $cur['id'] ) ) {
			$entries[] = $cur;
		}
	};
	$new     = static fn() => array( 'refs' => array(), 'plural' => null, 'str' => array(), 'fuzzy' => false );
	foreach ( file( $file, FILE_IGNORE_NEW_LINES ) as $line ) {
		if ( '' === trim( $line ) ) {
			$push();
			$cur   = null;
			$field = null;
			continue;
		}
		$cur ??= $new();
		if ( str_starts_with( $line, '#:' ) ) {
			$cur['refs'] = array_merge( $cur['refs'], preg_split( '/\s+/', trim( substr( $line, 2 ) ) ) );
		} elseif ( str_starts_with( $line, '#,' ) && str_contains( $line, 'fuzzy' ) ) {
			$cur['fuzzy'] = true;
		} elseif ( preg_match( '/^(msgid|msgid_plural|msgstr(?:\[(\d+)\])?) "(.*)"$/', $line, $m ) ) {
			$val = stripcslashes( $m[3] );
			if ( 'msgid' === $m[1] ) {
				$field = 'id';
				$cur['id'] = $val;
			} elseif ( 'msgid_plural' === $m[1] ) {
				$field = 'plural';
				$cur['plural'] = $val;
			} else {
				$field = (int) ( '' === ( $m[2] ?? '' ) ? 0 : $m[2] );
				$cur['str'][ $field ] = $val;
			}
		} elseif ( str_starts_with( $line, '"' ) && null !== $field ) {
			$val = stripcslashes( substr( $line, 1, -1 ) );
			if ( is_int( $field ) ) {
				$cur['str'][ $field ] .= $val;
			} else {
				$cur[ $field ] .= $val;
			}
		}
	}
	$push();
	return $entries;
}

/** Write a GNU MO file. */
function rc_mo( array $map ): string {
	ksort( $map, SORT_STRING );
	$n      = count( $map );
	$ids    = '';
	$strs   = '';
	$idtab  = array();
	$strtab = array();
	foreach ( $map as $id => $str ) {
		$idtab[]  = array( strlen( $id ), strlen( $ids ) );
		$ids     .= $id . "\0";
		$strtab[] = array( strlen( $str ), strlen( $strs ) );
		$strs    .= $str . "\0";
	}
	$o_ids  = 28;
	$o_strs = $o_ids + 8 * $n;
	$o_data = $o_strs + 8 * $n;
	$out    = pack( 'V7', 0x950412de, 0, $n, $o_ids, $o_strs, 0, $o_data );
	foreach ( $idtab as $e ) {
		$out .= pack( 'V2', $e[0], $o_data + $e[1] );
	}
	foreach ( $strtab as $e ) {
		$out .= pack( 'V2', $e[0], $o_data + strlen( $ids ) + $e[1] );
	}
	return $out . $ids . $strs;
}

function rc_placeholders( string $s ): array {
	preg_match_all( '/%(?:\d+\$)?[sdfu%]/', $s, $m );
	$p = $m[0];
	sort( $p );
	return $p;
}

$pot     = rc_parse_po( $root . '/languages/review-captain.pot' );
$pot_ids = array();
foreach ( $pot as $e ) {
	if ( '' !== $e['id'] ) {
		$pot_ids[ $e['id'] ] = $e;
	}
}
$errors = array();
if ( $check ) {
	// SET-RC-P5: the POT must list every string of the code, else new texts stay untranslated unnoticed.
	$tmp = tempnam( sys_get_temp_dir(), 'rc-pot' );
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/make-pot.php' ) . ' ' . escapeshellarg( $tmp ) . ' 2>&1', $unused_output, $status );
	$fresh = 0 === $status ? rc_parse_po( $tmp ) : array();
	unlink( $tmp );
	$fresh_ids = array();
	foreach ( $fresh as $e ) {
		if ( '' !== $e['id'] ) {
			$fresh_ids[ $e['id'] ] = $e['plural'];
		}
	}
	$pot_plural = array_map( static fn( $e ) => $e['plural'], $pot_ids );
	if ( 0 !== $status ) {
		$errors[] = 'bin/make-pot.php failed';
	} elseif ( $fresh_ids !== array_intersect_key( $pot_plural, $fresh_ids ) || array_diff_key( $pot_plural, $fresh_ids ) ) {
		foreach ( array_diff_key( $fresh_ids, $pot_plural ) as $id => $unused ) {
			$errors[] = 'not in POT: ' . substr( $id, 0, 60 );
		}
		$errors[] = 'languages/review-captain.pot is out of date (run php bin/make-pot.php, then translate the new strings)';
	}
}
foreach ( glob( $root . '/languages/review-captain-*.po' ) as $po_file ) {
	$locale  = substr( basename( $po_file, '.po' ), strlen( 'review-captain-' ) );
	$entries = rc_parse_po( $po_file );
	$header  = '';
	$map     = array();
	$js      = array();
	$seen    = array();
	foreach ( $entries as $e ) {
		if ( '' === $e['id'] ) {
			$header = $e['str'][0] ?? '';
			continue;
		}
		$seen[ $e['id'] ] = true;
		if ( ! isset( $pot_ids[ $e['id'] ] ) ) {
			continue; // Obsolete entry: ignored.
		}
		if ( $e['fuzzy'] || '' === implode( '', $e['str'] ) ) {
			$errors[] = "$locale: untranslated: " . substr( $e['id'], 0, 60 );
			continue;
		}
		foreach ( $e['str'] as $i => $str ) {
			$src = ( $i > 0 && null !== $e['plural'] ) ? $e['plural'] : $e['id'];
			if ( rc_placeholders( $src ) !== rc_placeholders( $str ) ) {
				$errors[] = "$locale: placeholders differ: " . substr( $e['id'], 0, 60 );
			}
		}
		$key         = null === $e['plural'] ? $e['id'] : $e['id'] . "\0" . $e['plural'];
		$map[ $key ] = implode( "\0", $e['str'] );
		foreach ( $pot_ids[ $e['id'] ]['refs'] as $ref ) {
			$path = preg_replace( '/:\d+$/', '', $ref );
			if ( str_ends_with( $path, '.js' ) ) {
				$js[ $path ][ $e['id'] ] = $e['str'];
			}
		}
	}
	foreach ( array_diff_key( $pot_ids, $seen ) as $id => $unused ) {
		$errors[] = "$locale: missing: " . substr( $id, 0, 60 );
	}
	$map[''] = $header;
	$files   = array( $root . "/languages/review-captain-$locale.mo" => rc_mo( $map ) );
	preg_match( '/Plural-Forms:\s*([^\n]+)/', $header, $pf );
	foreach ( $js as $path => $messages ) {
		ksort( $messages );
		$data = array(
			'translation-revision-date' => '',
			'generator'                 => 'review-captain/bin/compile-translations.php',
			'source'                    => $path,
			'domain'                    => 'review-captain',
			'locale_data'               => array(
				'review-captain' => array( '' => array( 'domain' => 'review-captain', 'lang' => $locale, 'plural-forms' => trim( $pf[1] ?? 'nplurals=2; plural=(n != 1);' ) ) ) + $messages,
			),
		);
		$files[ $root . "/languages/review-captain-$locale-" . md5( $path ) . '.json' ] = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
	}
	foreach ( $files as $file => $content ) {
		if ( $check ) {
			if ( ! is_file( $file ) || file_get_contents( $file ) !== $content ) {
				$errors[] = 'out of date: ' . basename( $file ) . ' (run php bin/compile-translations.php)';
			}
		} else {
			file_put_contents( $file, $content );
		}
	}
	echo "$locale: " . ( count( $map ) - 1 ) . ' messages, ' . count( $js ) . " JSON file(s)\n";
}
if ( $errors ) {
	fwrite( STDERR, implode( PHP_EOL, array_slice( $errors, 0, 50 ) ) . PHP_EOL . count( $errors ) . " problem(s)\n" );
	exit( 1 );
}
echo "Translations OK\n";
