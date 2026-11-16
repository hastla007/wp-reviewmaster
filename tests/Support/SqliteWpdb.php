<?php
/**
 * Minimal wpdb replacement backed by SQLite in-memory for unit/integration tests.
 *
 * Supports the subset used by Review Captain: prepare, query, get_results, get_row,
 * get_var, get_col, insert, update, delete, replace, esc_like, insert_id, rows_affected,
 * last_error, prefix, get_charset_collate. MySQL DDL from Schema::definitions() is
 * translated to SQLite.
 *
 * @package ReviewCaptain\Tests
 */

declare(strict_types=1);

namespace ReviewCaptain\Tests\Support;

/**
 * SQLite-backed wpdb.
 */
class SqliteWpdb {

	public string $prefix     = 'wp_';
	public string $base_prefix = 'wp_';
	public int $insert_id     = 0;
	public int $rows_affected = 0;
	public string $last_error = '';
	public int $blogid        = 1;

	/**
	 * Executed SQL statements (for assertions).
	 *
	 * @var list<string>
	 */
	public array $queries = array();

	private \PDO $pdo;

	public function __construct() {
		$this->pdo = new \PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT );
		$this->pdo->sqliteCreateFunction( 'NOW', static fn() => gmdate( 'Y-m-d H:i:s' ), 0 );
		$this->pdo->sqliteCreateFunction( 'UTC_TIMESTAMP', static fn() => gmdate( 'Y-m-d H:i:s' ), 0 );
	}

	public function get_charset_collate(): string {
		return '';
	}

	public function set_prefix( string $prefix ): void {
		$this->prefix = $prefix;
	}

	/**
	 * Create table from MySQL dbDelta-style DDL.
	 */
	public function create_from_mysql( string $sql ): void {
		if ( ! preg_match( '/CREATE TABLE\s+(\S+)\s*\((.*)\)\s*[^)]*$/is', $sql, $m ) ) {
			throw new \RuntimeException( 'Cannot parse DDL' );
		}
		$table   = $m[1];
		$lines   = preg_split( '/,\s*\n/', trim( $m[2] ) );
		$cols    = array();
		$indexes = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^PRIMARY KEY\s*\((\w+)\)/i', $line ) ) {
				continue;
			}
			if ( preg_match( '/^UNIQUE KEY\s+(\w+)\s*\(([^)]+)\)/i', $line, $k ) ) {
				$indexes[] = "CREATE UNIQUE INDEX {$table}_{$k[1]} ON {$table} ({$k[2]})";
				continue;
			}
			if ( preg_match( '/^KEY\s+(\w+)\s*\(([^)]+)\)/i', $line, $k ) ) {
				$indexes[] = "CREATE INDEX {$table}_{$k[1]} ON {$table} ({$k[2]})";
				continue;
			}
			if ( preg_match( '/AUTO_INCREMENT/i', $line ) ) {
				preg_match( '/^(\w+)/', $line, $c );
				$cols[] = "{$c[1]} INTEGER PRIMARY KEY AUTOINCREMENT";
				continue;
			}
			$line   = preg_replace( '/\bunsigned\b/i', '', $line );
			$line   = preg_replace( '/\b(bigint|int|tinyint)\(\d+\)/i', 'INTEGER', $line );
			$line   = preg_replace( '/\bdecimal\(\d+,\d+\)/i', 'REAL', $line );
			$line   = preg_replace( '/\b(varchar|char)\(\d+\)/i', 'TEXT', $line );
			$line   = preg_replace( '/\b(longtext|datetime)\b/i', 'TEXT', $line );
			$cols[] = $line;
		}
		$this->pdo->exec( "DROP TABLE IF EXISTS {$table}" );
		$ok = $this->pdo->exec( "CREATE TABLE {$table} (" . implode( ', ', $cols ) . ')' );
		if ( false === $ok ) {
			throw new \RuntimeException( 'DDL failed: ' . implode( ' ', $this->pdo->errorInfo() ) );
		}
		foreach ( $indexes as $idx ) {
			$this->pdo->exec( $idx );
		}
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * wpdb::prepare compatible (supports %s %d %f and %i identifiers).
	 *
	 * @param string $query Query.
	 * @param mixed  ...$args Args or single array.
	 */
	public function prepare( string $query, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$args = array_values( $args );
		$i    = 0;
		return preg_replace_callback(
			'/%(%|s|d|f|i)/',
			function ( $m ) use ( &$i, $args ) {
				if ( '%' === $m[1] ) {
					return '%';
				}
				$v = $args[ $i++ ] ?? null;
				return match ( $m[1] ) {
					'd' => (string) (int) $v,
					'f' => (string) (float) $v,
					'i' => '`' . str_replace( '`', '', (string) $v ) . '`',
					default => $this->pdo->quote( (string) $v ),
				};
			},
			$query
		);
	}

	private function translate( string $sql ): string {
		// MySQL "DELETE ... LIMIT n" / "UPDATE ... LIMIT n" are not supported by default SQLite builds.
		if ( preg_match( '/^\s*(DELETE|UPDATE)\b/i', $sql ) ) {
			$sql = preg_replace( '/\s+LIMIT\s+\d+\s*$/i', '', $sql );
		}
		$sql = str_replace( '`', '"', $sql );
		$sql = preg_replace( '/\bINSERT IGNORE\b/i', 'INSERT OR IGNORE', $sql );
		return $sql;
	}

	/**
	 * Run a statement.
	 *
	 * @param string $sql SQL.
	 * @return int|bool
	 */
	public function query( string $sql ) {
		$sql             = $this->translate( $sql );
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( preg_match( '/^\s*(SELECT|SHOW|PRAGMA)/i', $sql ) ) {
			$st = $this->pdo->query( $sql );
			if ( false === $st ) {
				$this->last_error = implode( ' ', $this->pdo->errorInfo() );
				return false;
			}
			$rows = $st->fetchAll( \PDO::FETCH_ASSOC );
			return count( $rows );
		}
		if ( preg_match( '/^\s*(START TRANSACTION|BEGIN)/i', $sql ) ) {
			return $this->pdo->inTransaction() ? true : $this->pdo->beginTransaction();
		}
		if ( preg_match( '/^\s*COMMIT/i', $sql ) ) {
			return $this->pdo->inTransaction() ? $this->pdo->commit() : true;
		}
		if ( preg_match( '/^\s*ROLLBACK/i', $sql ) ) {
			return $this->pdo->inTransaction() ? $this->pdo->rollBack() : true;
		}
		$res = $this->pdo->exec( $sql );
		if ( false === $res ) {
			$this->last_error = implode( ' ', $this->pdo->errorInfo() );
			return false;
		}
		$this->rows_affected = (int) $res;
		if ( preg_match( '/^\s*INSERT/i', $sql ) ) {
			$this->insert_id = (int) $this->pdo->lastInsertId();
		}
		return $this->rows_affected;
	}

	/**
	 * @return array<int, mixed>|null
	 */
	public function get_results( string $sql, string $output = 'OBJECT' ) {
		$sql             = $this->translate( $sql );
		$this->queries[] = $sql;
		$st              = $this->pdo->query( $sql );
		if ( false === $st ) {
			$this->last_error = implode( ' ', $this->pdo->errorInfo() );
			return null;
		}
		$rows = $st->fetchAll( \PDO::FETCH_ASSOC );
		if ( 'ARRAY_A' === $output ) {
			return $rows;
		}
		if ( 'ARRAY_N' === $output ) {
			return array_map( 'array_values', $rows );
		}
		return array_map( static fn( $r ) => (object) $r, $rows );
	}

	public function get_row( string $sql, string $output = 'OBJECT', int $y = 0 ) {
		$rows = $this->get_results( $sql, $output );
		return $rows[ $y ] ?? null;
	}

	public function get_var( string $sql, int $x = 0, int $y = 0 ) {
		$rows = $this->get_results( $sql, 'ARRAY_N' );
		return $rows[ $y ][ $x ] ?? null;
	}

	/**
	 * @return list<mixed>
	 */
	public function get_col( string $sql, int $x = 0 ): array {
		$rows = $this->get_results( $sql, 'ARRAY_N' ) ?? array();
		return array_map( static fn( $r ) => $r[ $x ] ?? null, $rows );
	}

	private function quote_value( mixed $v ): string {
		if ( null === $v ) {
			return 'NULL';
		}
		if ( is_bool( $v ) ) {
			return $v ? '1' : '0';
		}
		if ( is_int( $v ) || is_float( $v ) ) {
			return (string) $v;
		}
		return $this->pdo->quote( (string) $v );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function insert( string $table, array $data, $format = null ) {
		$cols = implode( ', ', array_map( static fn( $c ) => '"' . $c . '"', array_keys( $data ) ) );
		$vals = implode( ', ', array_map( array( $this, 'quote_value' ), array_values( $data ) ) );
		return $this->query( "INSERT INTO {$table} ({$cols}) VALUES ({$vals})" );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function replace( string $table, array $data, $format = null ) {
		$cols = implode( ', ', array_map( static fn( $c ) => '"' . $c . '"', array_keys( $data ) ) );
		$vals = implode( ', ', array_map( array( $this, 'quote_value' ), array_values( $data ) ) );
		return $this->query( "REPLACE INTO {$table} ({$cols}) VALUES ({$vals})" );
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $where
	 */
	public function update( string $table, array $data, array $where, $format = null, $where_format = null ) {
		$set = implode( ', ', array_map( fn( $c, $v ) => '"' . $c . '" = ' . $this->quote_value( $v ), array_keys( $data ), $data ) );
		$wh  = implode( ' AND ', array_map( fn( $c, $v ) => null === $v ? '"' . $c . '" IS NULL' : '"' . $c . '" = ' . $this->quote_value( $v ), array_keys( $where ), $where ) );
		return $this->query( "UPDATE {$table} SET {$set} WHERE {$wh}" );
	}

	/**
	 * @param array<string, mixed> $where
	 */
	public function delete( string $table, array $where, $where_format = null ) {
		$wh = implode( ' AND ', array_map( fn( $c, $v ) => '"' . $c . '" = ' . $this->quote_value( $v ), array_keys( $where ), $where ) );
		return $this->query( "DELETE FROM {$table} WHERE {$wh}" );
	}
}
