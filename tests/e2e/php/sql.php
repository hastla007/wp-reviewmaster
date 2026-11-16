<?php
// Direkte Leseabfrage auf die SQLite-Datei der Testinstanz (Ersatz für `wp db query`, das den mysql-Client braucht).
// Aufruf: php tests/e2e/php/sql.php <sqlite-datei> "<SQL>"   → Zeilen tab-getrennt.
$pdo = new PDO( 'sqlite:' . $argv[1] );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$st = $pdo->query( $argv[2] );
foreach ( $st->fetchAll( PDO::FETCH_NUM ) as $row ) {
	echo implode( "\t", array_map( static fn( $v ) => null === $v ? 'NULL' : (string) $v, $row ) ), "\n";
}
