<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// This tool is deliberately outside the application bootstrap and tenant models.
class DewawiMigrationException extends RuntimeException {}

class DewawiSqlMigrator
{
    const HISTORY = 'dewawi_schema_migration';
    const BASELINE = 'baseline/1.1.8';
    private $db;
    private $reference;
    private $catalog = [];
    private $transitions = [];
    private $lock;

    public function __construct(?Zend_Db_Adapter_Abstract $db, string $root)
    {
        $this->db = $db;
        $this->reference = __DIR__ . '/baseline-1.1.8.json';
        $registry = json_decode(file_get_contents($root . '/registry.json'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($registry) || count($registry) !== 1 || !isset($registry['transitions']) || !is_array($registry['transitions']) || !array_is_list($registry['transitions'])) throw new DewawiMigrationException('invalid_registry');
        $version = '1.1.8';
        $previous = self::BASELINE;
        foreach ($registry['transitions'] as $transition) {
            if (!is_array($transition) || count($transition) !== 3 || !isset($transition['from'], $transition['to'], $transition['migrations']) || $transition['from'] !== $version || !preg_match('/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)$/D', $transition['to']) || !version_compare($transition['to'], $version, '>') || !is_array($transition['migrations']) || !$transition['migrations'] || !array_is_list($transition['migrations'])) throw new DewawiMigrationException('invalid_transition_chain');
            $name = $transition['from'] . '-' . $transition['to'];
            $number = 0;
            foreach ($transition['migrations'] as $entry) {
                $number++;
                if (!is_array($entry) || count($entry) !== 2 || !isset($entry['file'], $entry['depends']) || !is_array($entry['depends']) || !array_is_list($entry['depends']) || !preg_match('/^([0-9]{3})-[a-z0-9]+(?:-[a-z0-9]+)*\.sql$/D', $entry['file'], $match) || (int)$match[1] !== $number) throw new DewawiMigrationException('invalid_migration_sequence');
                $id = $name . '/' . $entry['file'];
                if (strlen($id) > 191) throw new DewawiMigrationException('migration_id_too_long');
                $dependencies = array_values(array_unique(array_merge([$previous], $entry['depends'])));
                foreach ($dependencies as $dependency) {
                    if ($dependency !== self::BASELINE && !isset($this->catalog[$dependency])) throw new DewawiMigrationException('missing_or_forward_dependency: ' . $id);
                }
                $path = $root . '/' . $id;
                if (!is_file($path) || is_link($path)) throw new DewawiMigrationException('missing_migration: ' . $id);
                $sql = file_get_contents($path);
                $statements = self::statements($sql);
                if (!$statements) throw new DewawiMigrationException('empty_migration: ' . $id);
                $checksum = hash('sha256', $sql);
                $definition = hash('sha256', json_encode([$id, $checksum, $dependencies], JSON_THROW_ON_ERROR));
                $this->catalog[$id] = ['id' => $id, 'checksum' => $checksum, 'definition' => $definition, 'depends' => $dependencies, 'sql' => $sql, 'statements' => $statements, 'transition' => $name];
                $this->transitions[$name][] = $id;
                $previous = $id;
            }
            $version = $transition['to'];
        }
        $files = glob($root . '/*/*.sql');
        foreach ($files as $path) {
            if (!isset($this->catalog[substr($path, strlen($root) + 1)])) throw new DewawiMigrationException('unregistered_migration');
        }
    }

    public static function statements(string $sql): array
    {
        // Reuse the installer splitter; executable comments and client directives are excluded.
        if (preg_match('/\/\*(?:[!+]|M!)|\bDELIMITER\b/i', $sql)) throw new DewawiMigrationException('unsupported_sql_directive');
        $queries = [];
        PMA_splitSqlFile($queries, $sql);
        $result = [];
        foreach ($queries as $query) {
            $statement = trim(preg_replace('/\/\*.*?\*\/|--[^\S\r\n][^\r\n]*|#[^\r\n]*/s', '', $query['query']));
            if ($statement === '') continue;
            // No session/transaction/lock manipulation or stored programs in versioned files.
            // Comments are retained for execution; the prefix check uses the stripped copy only.
            if (!preg_match('/^(?:CREATE\s+(?:TABLE|(?:UNIQUE\s+)?INDEX)|ALTER\s+TABLE|DROP\s+(?:TABLE|INDEX)|INSERT\s+INTO|UPDATE|DELETE\s+FROM|RENAME\s+TABLE)\b/i', $statement) || stripos($sql, self::HISTORY) !== false) throw new DewawiMigrationException('unsupported_migration_statement');
            $result[] = $query['query'];
        }
        return $result;
    }

    private static function charset(?string $value): ?string
    {
        return $value === null ? null : str_replace('utf8mb3', 'utf8', strtolower($value));
    }

    public static function schema(Zend_Db_Adapter_Abstract $db): array
    {
        $schema = [];
        $database = $db->fetchOne('SELECT DATABASE()');
        $quoted = '`(?:``|[^`])+`';
        $identifier = static function(string $value): string { return str_replace('``', '`', substr($value, 1, -1)); };
        $tables = $db->fetchAll('SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME');
        foreach ($tables as $table) {
            $name = $table['TABLE_NAME'];
            if ($name === self::HISTORY) continue;
            $definition = ['type' => $table['TABLE_TYPE'], 'engine' => $table['ENGINE'], 'collation' => self::charset($table['TABLE_COLLATION']), 'columns' => [], 'indexes' => [], 'foreign_keys' => []];
            $columns = $db->fetchAll('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY COLUMN_NAME', [$name]);
            foreach ($columns as $column) {
                $type = preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\([0-9]+\)/', '$1', strtolower($column['COLUMN_TYPE']));
                $default = $column['COLUMN_DEFAULT'];
                if ($default === 'NULL') $default = null;
                elseif ($default !== null && strlen($default) >= 2 && $default[0] === "'" && substr($default, -1) === "'") $default = str_replace(["''", "\\'"], "'", substr($default, 1, -1));
                $extra = strtolower(str_replace('DEFAULT_GENERATED', '', $column['EXTRA']));
                $definition['columns'][$column['COLUMN_NAME']] = [$type, $column['IS_NULLABLE'], $default, trim($extra), self::charset($column['CHARACTER_SET_NAME']), self::charset($column['COLLATION_NAME']), $column['GENERATION_EXPRESSION'] ?: ''];
            }
            $indexes = $db->fetchAll('SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART, COLLATION, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$name]);
            foreach ($indexes as $index) $definition['indexes'][$index['INDEX_NAME']][] = [(int)$index['NON_UNIQUE'], $index['COLUMN_NAME'], $index['SUB_PART'] === null ? null : (int)$index['SUB_PART'], $index['COLLATION'], $index['INDEX_TYPE']];
            // REFERENTIAL_CONSTRAINTS hides rows from SELECT-only accounts on MariaDB.
            // SHOW CREATE TABLE exposes the authoritative FK definitions with read privileges.
            $create = array_values($db->fetchRow('SHOW CREATE TABLE ' . $db->quoteIdentifier($name)))[1];
            preg_match_all('/CONSTRAINT (' . $quoted . ') FOREIGN KEY \(([^)]+)\) REFERENCES (?:(' . $quoted . ')\.)?(' . $quoted . ') \(([^)]+)\)([^,\n]*)/i', $create, $keys, PREG_SET_ORDER);
            foreach ($keys as $key) {
                preg_match_all('/' . $quoted . '/', $key[2], $local);
                preg_match_all('/' . $quoted . '/', $key[5], $remote);
                if (count($local[0]) !== count($remote[0])) throw new DewawiMigrationException('unsupported_foreign_key');
                $target = $key[3] === '' ? $database : $identifier($key[3]);
                preg_match('/ON UPDATE (RESTRICT|CASCADE|SET NULL|NO ACTION|SET DEFAULT)/i', $key[6], $update);
                preg_match('/ON DELETE (RESTRICT|CASCADE|SET NULL|NO ACTION|SET DEFAULT)/i', $key[6], $delete);
                foreach ($local[0] as $position => $column) $definition['foreign_keys'][$identifier($key[1])][] = [$identifier($column), $target === $database ? '@self' : $target, $identifier($key[4]), $identifier($remote[0][$position]), strtoupper($update[1] ?? 'RESTRICT'), strtoupper($delete[1] ?? 'RESTRICT')];
            }
            ksort($definition['foreign_keys']);
            $schema[$name] = $definition;
        }
        return $schema;
    }

    public function verifyBaseline(): void
    {
        $reference = json_decode(file_get_contents($this->reference), true, 512, JSON_THROW_ON_ERROR);
        $actual = self::schema($this->db);
        $errors = [];
        foreach ($reference['tables'] as $table => $expected) {
            if (!isset($actual[$table])) { $errors[] = $table; continue; }
            foreach (['type', 'engine', 'collation'] as $property) {
                if ($actual[$table][$property] !== $expected[$property]) $errors[] = $table . '.' . $property;
            }
            foreach (['columns', 'indexes', 'foreign_keys'] as $property) {
                foreach ($expected[$property] as $name => $value) {
                    if (!isset($actual[$table][$property][$name]) || $actual[$table][$property][$name] !== $value) $errors[] = $table . '.' . $property . '.' . $name;
                }
            }
        }
        if ($errors) throw new DewawiMigrationException('incomplete_1.1.8_schema: ' . implode(', ', array_slice($errors, 0, 20)));
    }

    private function history(): array
    {
        $table = $this->db->fetchRow('SELECT ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [self::HISTORY]);
        if (!$table) return [];
        if ($table['ENGINE'] !== 'InnoDB' || $table['TABLE_TYPE'] !== 'BASE TABLE') throw new DewawiMigrationException('invalid_history_table');
        $columns = $this->db->fetchAll('SHOW COLUMNS FROM ' . self::HISTORY);
        $expected = ['migration_id' => 'varchar(191)', 'checksum' => 'char(64)', 'definition_checksum' => 'char(64)', 'status' => 'varchar(16)', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'statement_number' => 'int unsigned', 'error_code' => 'varchar(32)'];
        if (count($columns) !== count($expected)) throw new DewawiMigrationException('invalid_history_columns');
        foreach ($columns as $column) {
            $type = preg_replace('/\bint\([0-9]+\)/', 'int', $column['Type']);
            $nullable = in_array($column['Field'], ['completed_at', 'error_code'], true) ? 'YES' : 'NO';
            if (($expected[$column['Field']] ?? null) !== $type || $column['Null'] !== $nullable || $column['Extra'] !== '') throw new DewawiMigrationException('invalid_history_columns');
        }
        $primary = $this->db->fetchAll("SHOW INDEX FROM " . self::HISTORY . " WHERE Key_name = 'PRIMARY'");
        if (count($primary) !== 1 || $primary[0]['Column_name'] !== 'migration_id' || $primary[0]['Sub_part'] !== null) throw new DewawiMigrationException('invalid_history_primary_key');
        $rows = $this->db->fetchAll('SELECT * FROM ' . self::HISTORY . ' ORDER BY started_at, migration_id');
        $history = [];
        foreach ($rows as $row) {
            $id = $row['migration_id'];
            if (isset($history[$id])) throw new DewawiMigrationException('duplicate_history');
            $history[$id] = $row;
        }
        return $history;
    }

    private function validateHistory(array $history): void
    {
        $seenPending = false;
        $all = [self::BASELINE => ['checksum' => hash_file('sha256', $this->reference), 'definition' => hash_file('sha256', $this->reference), 'depends' => []]] + $this->catalog;
        foreach ($history as $id => $row) {
            if (!isset($all[$id])) throw new DewawiMigrationException('missing_historical_migration');
            if ($row['status'] !== 'completed' || !$row['completed_at'] || $row['error_code'] !== null) {
                $status = in_array($row['status'], ['started', 'failed', 'completed'], true) ? $row['status'] : 'unknown';
                throw new DewawiMigrationException('unresolved_migration: ' . $id . ' status=' . $status . ' statement=' . (int)$row['statement_number'] . ' (inspect ' . self::HISTORY . ')');
            }
            if (!hash_equals($all[$id]['checksum'], $row['checksum']) || !hash_equals($all[$id]['definition'], $row['definition_checksum'])) throw new DewawiMigrationException('migration_checksum_mismatch: ' . $id);
            $statementCount = $id === self::BASELINE ? 0 : count($all[$id]['statements']);
            if ((int)$row['statement_number'] !== $statementCount || !$row['started_at'] || $row['completed_at'] < $row['started_at']) throw new DewawiMigrationException('ambiguous_completed_history: ' . $id);
        }
        foreach ($all as $id => $entry) {
            if (!isset($history[$id])) { $seenPending = true; continue; }
            if ($seenPending) throw new DewawiMigrationException('history_order_gap');
            foreach ($entry['depends'] as $dependency) if (!isset($history[$dependency])) throw new DewawiMigrationException('missing_applied_dependency');
        }
    }

    public function locked(callable $action)
    {
        $this->lock = 'dewawi-migrate:' . substr(hash('sha256', strtolower($this->db->fetchOne('SELECT DATABASE()'))), 0, 40);
        if ((int)$this->db->fetchOne('SELECT GET_LOCK(?, 0)', [$this->lock]) !== 1) throw new DewawiMigrationException('migration_lock_busy');
        try {
            // Autocommit is required to persist the started marker before implicit DDL commits.
            $this->db->query('SET autocommit = 1');
            $this->db->query('SET SESSION foreign_key_checks = 1, unique_checks = 1');
            $this->db->query('SET SESSION sql_quote_show_create = 1');
            $this->db->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION'");
            $this->db->query("SET time_zone = '+00:00'");
            return $action();
        } finally {
            $this->db->fetchOne('SELECT RELEASE_LOCK(?)', [$this->lock]);
        }
    }

    public function plan(?string $bundle = null): array
    {
        $history = $this->history();
        $this->validateHistory($history);
        if (!isset($history[self::BASELINE])) $this->verifyBaseline();
        $pending = array_values(array_diff(array_keys($this->catalog), array_keys($history)));
        if ($bundle !== null) {
            $content = file_get_contents($bundle);
            $transition = null;
            foreach ($this->transitions as $name => $ids) if (hash_equals($this->bundle($name), $content)) $transition = $name;
            if ($transition === null) throw new DewawiMigrationException('bundle_does_not_match_catalog');
            $selected = $this->transitions[$transition];
            $pending = array_values(array_intersect($pending, $selected));
            $available = array_fill_keys(array_keys($history), true);
            foreach ($pending as $id) {
                foreach ($this->catalog[$id]['depends'] as $dependency) if (!isset($available[$dependency])) throw new DewawiMigrationException('bundle_predecessor_not_applied');
                $available[$id] = true;
            }
        }
        return ['baseline' => isset($history[self::BASELINE]) ? 'completed' : 'verified_not_registered', 'history' => array_values($history), 'pending' => $pending];
    }

    private function createHistory(): void
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . self::HISTORY . " (
            migration_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            checksum char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            definition_checksum char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            status varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            statement_number int unsigned NOT NULL DEFAULT 0,
            error_code varchar(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
            PRIMARY KEY (migration_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function start(string $id, string $checksum, string $definition): void
    {
        $this->db->insert(self::HISTORY, ['migration_id' => $id, 'checksum' => $checksum, 'definition_checksum' => $definition, 'status' => 'started', 'started_at' => gmdate('Y-m-d H:i:s')]);
    }

    private function complete(string $id): void
    {
        $changed = $this->db->update(self::HISTORY, ['status' => 'completed', 'completed_at' => gmdate('Y-m-d H:i:s')], ['migration_id = ?' => $id, 'status = ?' => 'started']);
        if ($changed !== 1) throw new DewawiMigrationException('completion_not_recorded: ' . $id);
    }

    public function baseline(): array
    {
        $history = $this->history();
        $this->validateHistory($history);
        if (isset($history[self::BASELINE])) return $this->plan();
        $this->verifyBaseline();
        $this->createHistory();
        $checksum = hash_file('sha256', $this->reference);
        $this->start(self::BASELINE, $checksum, $checksum);
        $this->complete(self::BASELINE);
        return $this->plan();
    }

    public function apply(?string $bundle = null): array
    {
        $plan = $this->plan($bundle);
        if ($plan['baseline'] !== 'completed') throw new DewawiMigrationException('explicit_baseline_registration_required');
        foreach ($plan['pending'] as $id) {
            $entry = $this->catalog[$id];
            $this->start($id, $entry['checksum'], $entry['definition']);
            foreach ($entry['statements'] as $number => $statement) {
                $this->db->update(self::HISTORY, ['statement_number' => $number + 1], ['migration_id = ?' => $id]);
                try {
                    $this->db->query($statement);
                } catch (Throwable $error) {
                    $code = 'sql_error';
                    if ($error instanceof Zend_Db_Statement_Exception && $error->getPrevious() instanceof PDOException) $code = 'sqlstate_' . preg_replace('/[^A-Z0-9]/', '', (string)$error->getPrevious()->getCode());
                    $this->db->update(self::HISTORY, ['status' => 'failed', 'error_code' => $code], ['migration_id = ?' => $id]);
                    throw new DewawiMigrationException('migration_failed: ' . $id . ' statement ' . ($number + 1) . ' ' . $code);
                }
            }
            $this->complete($id);
        }
        return $this->plan($bundle);
    }

    public function bundle(string $transition): string
    {
        if (!isset($this->transitions[$transition])) throw new DewawiMigrationException('unknown_transition');
        $sql = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEWAWI: use tools/migrate.php --bundle; never import directly';\n";
        $sql .= "-- DEWAWI consolidated update: " . $transition . "\n-- Catalog-verified input for the CLI; the initial guard is never executed by the CLI.\n";
        foreach ($this->transitions[$transition] as $id) {
            $entry = $this->catalog[$id];
            $sql .= "\n-- migration " . $id . ' sha256=' . $entry['checksum'] . ' definition=' . $entry['definition'] . "\n" . $entry['sql'] . "\n;\n";
        }
        return $sql;
    }
}
