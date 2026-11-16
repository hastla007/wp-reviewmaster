<?php
/**
 * Generates docs/hooks.md: reference of all do_action()/apply_filters() calls with prefix
 * review_captain_ (name, type, parameters, file:line). Development tool, not shipped.
 *
 * Usage:
 *   php bin/list-hooks.php            Print Markdown to STDOUT.
 *   php bin/list-hooks.php --write    Write docs/hooks.md.
 *   php bin/list-hooks.php --check    Exit 1 if docs/hooks.md is outdated (CI).
 *
 * Hook names given as class constants (self::HOOK, Foo::ACTION) are resolved from the constant
 * declarations in the scanned files. Parameters come from the @param lines of the docblock
 * directly above the call; without docblock the argument expressions are listed.
 *
 * @package ReviewCaptain
 */

declare(strict_types=1);

$root    = dirname( __DIR__ );
$dirs    = array( 'src', 'templates', 'blocks' );
$files   = array();
$prefix  = 'review_captain_';
$targets = array(
	'do_action'                => 'action',
	'do_action_ref_array'      => 'action',
	'do_action_deprecated'     => 'action (veraltet)',
	'apply_filters'            => 'filter',
	'apply_filters_ref_array'  => 'filter',
	'apply_filters_deprecated' => 'filter (veraltet)',
);

foreach ( $dirs as $dir ) {
	if ( ! is_dir( $root . '/' . $dir ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$files[] = $file->getPathname();
		}
	}
}
foreach ( array( 'review-captain.php', 'uninstall.php' ) as $single ) {
	if ( is_file( $root . '/' . $single ) ) {
		$files[] = $root . '/' . $single;
	}
}
sort( $files );

/**
 * Pass 1: class constants with string values, keyed by short class name and FQCN.
 */
$constants = array();
$tokens_by_file = array();
foreach ( $files as $path ) {
	$tokens                  = token_get_all( (string) file_get_contents( $path ) );
	$tokens_by_file[ $path ] = $tokens;
	$namespace               = '';
	$class                   = null;
	$count                   = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		$t = $tokens[ $i ];
		if ( ! is_array( $t ) ) {
			continue;
		}
		if ( T_NAMESPACE === $t[0] ) {
			$namespace = '';
			for ( $j = $i + 1; $j < $count && ';' !== $tokens[ $j ] && '{' !== $tokens[ $j ]; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_STRING, T_NAME_QUALIFIED ), true ) ) {
					$namespace .= $tokens[ $j ][1];
				}
			}
		} elseif ( in_array( $t[0], array( T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM ), true ) ) {
			for ( $j = $i + 1; $j < $count; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
					$class = $tokens[ $j ][1];
					break;
				}
				if ( '(' === $tokens[ $j ] || '{' === $tokens[ $j ] ) {
					break; // Anonymous class or ::class.
				}
			}
		} elseif ( T_CONST === $t[0] && null !== $class ) {
			// const NAME = 'value';
			$name  = null;
			$value = null;
			for ( $j = $i + 1; $j < $count && ';' !== $tokens[ $j ]; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] && null === $name ) {
					$name = $tokens[ $j ][1];
				} elseif ( is_array( $tokens[ $j ] ) && T_CONSTANT_ENCAPSED_STRING === $tokens[ $j ][0] && null === $value ) {
					$value = stripcslashes( substr( $tokens[ $j ][1], 1, -1 ) );
				} elseif ( '=' !== $tokens[ $j ] && ! ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) && null !== $name && null !== $value ) {
					$value = null; // Expression, not a plain string.
					break;
				}
			}
			if ( null !== $name && null !== $value ) {
				$constants[ $class ][ $name ]                                   = $value;
				$constants[ ltrim( $namespace . '\\' . $class, '\\' ) ][ $name ] = $value;
			}
		}
	}
}

/**
 * Pass 2: hook calls.
 */
$hooks = array();
foreach ( $tokens_by_file as $path => $tokens ) {
	$rel   = substr( $path, strlen( $root ) + 1 );
	$count = count( $tokens );
	$class = null;
	$uses  = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$t = $tokens[ $i ];
		if ( is_array( $t ) && T_USE === $t[0] && null === $class ) {
			$fq = '';
			for ( $j = $i + 1; $j < $count && ';' !== $tokens[ $j ]; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ), true ) ) {
					$fq .= $tokens[ $j ][1];
				}
			}
			$fq                                        = ltrim( $fq, '\\' );
			$uses[ substr( strrchr( '\\' . $fq, '\\' ), 1 ) ] = $fq;
			continue;
		}
		if ( is_array( $t ) && in_array( $t[0], array( T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM ), true ) ) {
			for ( $j = $i + 1; $j < $count; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
					$class = $tokens[ $j ][1];
					break;
				}
				if ( '(' === $tokens[ $j ] || '{' === $tokens[ $j ] ) {
					break;
				}
			}
			continue;
		}
		if ( ! is_array( $t ) || T_STRING !== $t[0] || ! isset( $targets[ strtolower( $t[1] ) ] ) ) {
			continue;
		}
		// Skip method calls / definitions (->do_action, function apply_filters).
		$prev = $i - 1;
		while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
			--$prev;
		}
		if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR ), true ) ) {
			continue;
		}
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			++$j;
		}
		if ( '(' !== ( $tokens[ $j ] ?? null ) ) {
			continue;
		}
		// Split top-level arguments.
		$args  = array( '' );
		$depth = 0;
		for ( $k = $j + 1; $k < $count; $k++ ) {
			$tok  = $tokens[ $k ];
			$text = is_array( $tok ) ? $tok[1] : $tok;
			if ( in_array( $text, array( '(', '[', '{' ), true ) || ( is_array( $tok ) && in_array( $tok[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
			} elseif ( in_array( $text, array( ')', ']', '}' ), true ) ) {
				if ( 0 === $depth ) {
					break;
				}
				--$depth;
			} elseif ( ',' === $text && 0 === $depth ) {
				$args[] = '';
				continue;
			}
			if ( is_array( $tok ) && in_array( $tok[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$args[ count( $args ) - 1 ] .= $text;
		}
		$args = array_values( array_filter( array_map( static fn( string $a ): string => trim( (string) preg_replace( '/\s+/', ' ', $a ) ), $args ), static fn( string $a ): bool => '' !== $a ) );
		if ( ! $args ) {
			continue;
		}
		// Resolve the hook name.
		$first = $args[0];
		$name  = null;
		if ( preg_match( '/^([\'"])(.*)\1$/s', $first, $m ) ) {
			$name = $m[2];
		} elseif ( preg_match( '/^(self|static|[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)::([A-Z0-9_]+)$/', $first, $m ) ) {
			$owner = 'self' === $m[1] || 'static' === $m[1] ? $class : ltrim( $m[1], '\\' );
			if ( null !== $owner && isset( $uses[ $owner ] ) ) {
				$owner = $uses[ $owner ];
			}
			$short = null === $owner ? null : substr( (string) strrchr( '\\' . $owner, '\\' ), 1 );
			$name  = $constants[ $owner ][ $m[2] ] ?? ( null !== $short ? ( $constants[ $short ][ $m[2] ] ?? null ) : null );
			if ( null !== $name ) {
				$first = $first . ' = ' . $name;
			}
		}
		if ( null === $name || ! str_starts_with( $name, $prefix ) ) {
			continue;
		}
		// Docblock directly above the statement (same statement, at most 60 tokens back).
		$params = array();
		for ( $b = $i - 1; $b >= 0 && $b > $i - 60; $b-- ) {
			$tok = $tokens[ $b ];
			if ( is_array( $tok ) && T_DOC_COMMENT === $tok[0] ) {
				if ( preg_match_all( '/@param\s+(.+?)\s+(&?(?:\.\.\.)?\$\w+)[ \t]*(.*)$/m', $tok[1], $pm, PREG_SET_ORDER ) ) {
					foreach ( $pm as $p ) {
						$params[] = '`' . $p[1] . ' ' . $p[2] . '`' . ( '' !== trim( $p[3] ) ? ' – ' . rtrim( trim( $p[3] ), '.' ) : '' );
					}
				}
				break;
			}
			if ( ! is_array( $tok ) && in_array( $tok, array( ';', '{', '}' ), true ) ) {
				break; // Previous statement reached: no docblock for this call.
			}
		}
		if ( ! $params ) {
			foreach ( array_slice( $args, 1 ) as $a ) {
				$params[] = '`' . $a . '`';
			}
		}
		$hooks[] = array(
			'name'   => $name,
			'type'   => $targets[ strtolower( $t[1] ) ],
			'params' => $params,
			'source' => $first,
			'where'  => $rel . ':' . $t[2],
		);
	}
}

usort(
	$hooks,
	static fn( array $a, array $b ): int => array( $a['name'], $a['where'] ) <=> array( $b['name'], $b['where'] )
);

$esc = static fn( string $s ): string => str_replace( '|', '\\|', $s );
$out = "# Hook-Referenz (generiert)\n\n";
$out .= "> Diese Datei wird von `bin/list-hooks.php` erzeugt – nicht von Hand bearbeiten. Aktualisieren: `php bin/list-hooks.php --write`; prüfen (CI): `php bin/list-hooks.php --check`.\n\n";
$out .= 'Erfasst sind alle Aufrufe von `do_action()`/`apply_filters()` (inkl. `_ref_array`/`_deprecated`) mit Hook-Namen, die mit `review_captain_` beginnen, in `src/`, `templates/`, `blocks/`, `review-captain.php` und `uninstall.php`. Als Klassenkonstante angegebene Namen sind aufgelöst. Parameter stammen aus dem Docblock direkt über dem Aufruf, sonst aus den Argumenten. ';
$out .= 'Welche Hooks öffentliche, versionierte Verträge sind, regelt [ADR-0007](adr/0007-oeffentliche-hooks-v1.md) bzw. die Hook-Tabelle in [conventions.md](architecture/conventions.md); alle übrigen sind Erweiterungspunkte ohne Stabilitätszusage ([ADR-0002](adr/0002-hook-praefix-review-captain.md)). ';
$out .= "Interne Cron-Hooks (`review_captain_run_jobs`, `review_captain_daily_maintenance`) werden von WP-Cron/Action Scheduler ausgelöst und erscheinen hier daher nicht.\n\n";
$out .= sprintf( "Anzahl: %d Aufrufstellen, %d Hooks.\n\n", count( $hooks ), count( array_unique( array_column( $hooks, 'name' ) ) ) );
$out .= "| Name | Typ | Parameter | Datei:Zeile |\n|---|---|---|---|\n";
foreach ( $hooks as $h ) {
	$out .= sprintf(
		"| `%s` | %s | %s | `%s` |\n",
		$h['name'],
		$h['type'],
		$h['params'] ? $esc( implode( '<br>', $h['params'] ) ) : '–',
		$h['where']
	);
}

$target = $root . '/docs/hooks.md';
$mode   = $argv[1] ?? '';
if ( '--write' === $mode ) {
	file_put_contents( $target, $out );
	fwrite( STDOUT, "docs/hooks.md geschrieben (" . count( $hooks ) . " Aufrufstellen).\n" );
	exit( 0 );
}
if ( '--check' === $mode ) {
	$current = is_file( $target ) ? (string) file_get_contents( $target ) : '';
	if ( $current !== $out ) {
		fwrite( STDERR, "docs/hooks.md ist veraltet. Bitte `php bin/list-hooks.php --write` ausführen.\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "docs/hooks.md ist aktuell.\n" );
	exit( 0 );
}
fwrite( STDOUT, $out );
