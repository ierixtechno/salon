<?php

namespace App\Domain\Platform\Backup;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

/**
 * Writes a plain-SQL dump of the whole database to a stream, in pure PHP
 * over PDO — no `mysqldump` binary and no exec()/proc_open(), both of which
 * shared hosts commonly disable (CLAUDE.md §64: nothing here may require a
 * capability shared hosting doesn't reliably have).
 *
 * The output is ordinary SQL that `mysql < database.sql` or phpMyAdmin's
 * Import restores directly. It is written for restoring into an *empty*
 * database: each table is DROPped-if-exists and recreated, foreign-key
 * checks are switched off for the load so table order doesn't matter, and
 * the session is pinned to UTC so TIMESTAMP columns round-trip to the same
 * instants regardless of the servers' own time zones.
 *
 * Rows are streamed one at a time (unbuffered) and written in multi-row
 * INSERTs, so memory use stays flat however large a table is.
 *
 * Some tables are dumped structure-only, on purpose (see STRUCTURE_ONLY):
 * their contents are either worthless after a restore (sessions, cache) or
 * actively harmful (queued jobs that would re-fire notifications; password
 * reset tokens are credentials).
 *
 * This class does not touch session state itself (no time-zone change, no
 * snapshot transaction) — that belongs to the caller, on its own dedicated
 * connection, so nothing leaks into the application's own connection. See
 * RunBackup.
 */
class DatabaseDumper
{
    public const STRUCTURE_ONLY = [
        'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens',
        'backup_runs',
    ];

    /** Written as the very last line — proof the dump ran to the end. */
    public const COMPLETION_MARKER = '-- Dump completed';

    private const ROWS_PER_INSERT = 200;

    public function __construct(private readonly Connection $connection) {}

    /**
     * @param  resource  $handle  writable stream
     * @return array{tables: int, rows: int, table_rows: array<string, int>}
     */
    public function dump($handle): array
    {
        if ($this->connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Database backup supports MySQL only.');
        }

        $pdo = $this->connection->getPdo();
        $database = $this->connection->getDatabaseName();

        $this->write($handle, "-- Database backup\n-- Database: {$database}\n-- Generated: ".gmdate('Y-m-d H:i:s')." UTC\n"
            ."-- Restore into an EMPTY database:  mysql -u USER -p DATABASE < database.sql\n"
            ."-- (or phpMyAdmin > Import). Tables are dropped and recreated.\n\n"
            ."SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\n"
            ."SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tableRows = [];
        $totalRows = 0;

        // Unbuffered = rows are pulled from the server as we read them
        // instead of the whole result set landing in PHP memory first.
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        try {
            $tables = $this->tableNames($pdo);

            foreach ($tables as $table) {
                $quoted = $this->identifier($table);

                $this->write($handle, "-- Table: {$table}\nDROP TABLE IF EXISTS {$quoted};\n".$this->createStatement($pdo, $quoted).";\n\n");

                $count = in_array($table, self::STRUCTURE_ONLY, true) ? 0 : $this->dumpRows($pdo, $handle, $table, $quoted);

                $tableRows[$table] = $count;
                $totalRows += $count;
            }
        } finally {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }

        $this->write($handle, "SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n".self::COMPLETION_MARKER."\n");

        return ['tables' => count($tables), 'rows' => $totalRows, 'table_rows' => $tableRows];
    }

    /** @return list<string> */
    private function tableNames(PDO $pdo): array
    {
        $names = [];
        $result = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $names[] = $row[0];
        }
        $result->closeCursor();

        return $names;
    }

    private function createStatement(PDO $pdo, string $quotedTable): string
    {
        $result = $pdo->query("SHOW CREATE TABLE {$quotedTable}");
        $row = $result->fetch(PDO::FETCH_NUM);
        $result->closeCursor();

        return $row[1];
    }

    /**
     * @param  resource  $handle
     */
    private function dumpRows(PDO $pdo, $handle, string $table, string $quotedTable): int
    {
        // Which columns hold raw bytes (written as 0x... hex, since a
        // quoted binary string is not safe across character sets).
        $columns = [];
        $binary = [];
        $result = $pdo->query("SHOW COLUMNS FROM {$quotedTable}");
        while ($column = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $this->identifier($column['Field']);
            $binary[] = (bool) preg_match('/blob|binary/i', $column['Type']);
        }
        $result->closeCursor();

        $prefix = "INSERT INTO {$quotedTable} (".implode(',', $columns).") VALUES\n";
        $buffer = [];
        $count = 0;

        $rows = $pdo->query("SELECT * FROM {$quotedTable}");

        while ($row = $rows->fetch(PDO::FETCH_NUM)) {
            $values = [];
            foreach ($row as $i => $value) {
                $values[] = $value === null ? 'NULL' : $this->literal($pdo, (string) $value, $binary[$i]);
            }
            $buffer[] = '('.implode(',', $values).')';
            $count++;

            if (count($buffer) >= self::ROWS_PER_INSERT) {
                $this->write($handle, $prefix.implode(",\n", $buffer).";\n");
                $buffer = [];
            }
        }
        $rows->closeCursor();

        if ($buffer !== []) {
            $this->write($handle, $prefix.implode(",\n", $buffer).";\n");
        }

        return $count;
    }

    private function literal(PDO $pdo, string $value, bool $binary): string
    {
        if ($binary) {
            return $value === '' ? "''" : '0x'.bin2hex($value);
        }

        return $pdo->quote($value);
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /**
     * @param  resource  $handle
     */
    private function write($handle, string $sql): void
    {
        // fwrite() can return fewer bytes than asked (disk full) without
        // failing outright — a truncated backup must not look like success.
        if (fwrite($handle, $sql) !== strlen($sql)) {
            throw new RuntimeException('Could not write the database dump — the disk may be full.');
        }
    }
}
