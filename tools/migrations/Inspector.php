<?php

require_once dirname(__DIR__, 2) . '/install/SqlSplitter.php';

class DewawiMigrationException extends RuntimeException {}

// Shared catalog/history inspection. This class cannot execute migrations or register a baseline.
class DewawiMigrationInspector
{
    const HISTORY = 'dewawi_schema_migration';
    const BASELINE = 'baseline/1.1.8';
    protected $db;
    protected $reference;
    protected $catalog = [];
    protected $transitions = [];
    private $historyExists = false;

    public function __construct(?Zend_Db_Adapter_Abstract $db, string $root)
    {
        $this->db = $db;
        $this->reference = __DIR__ . '/baseline-1.1.8.json';
        $registry = json_decode(@file_get_contents($root . '/registry.json'), true, 512, JSON_THROW_ON_ERROR);
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
                $sql = @file_get_contents($path);
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
            if (!preg_match('/^(?:CREATE\s+(?:TABLE|(?:UNIQUE\s+)?INDEX)|ALTER\s+TABLE|DROP\s+(?:TABLE|INDEX)|INSERT\s+INTO|UPDATE|DELETE\s+FROM|RENAME\s+TABLE)\b/i', $statement) || stripos($sql, self::HISTORY) !== false || stripos($sql, 'dewawi_system_update') !== false) throw new DewawiMigrationException('unsupported_migration_statement');
            $result[] = $query['query'];
        }
        return $result;
    }

    protected function history(): array
    {
        $table = $this->db->fetchRow('SELECT ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [self::HISTORY]);
        $this->historyExists = (bool)$table;
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

    private function validateRow(string $id, array $row, array $entry): void
    {
        if ($row['status'] !== 'completed' || !$row['completed_at'] || $row['error_code'] !== null) {
            $status = in_array($row['status'], ['started', 'failed', 'completed'], true) ? $row['status'] : 'unknown';
            throw new DewawiMigrationException('unresolved_migration: ' . $id . ' status=' . $status . ' statement=' . (int)$row['statement_number'] . ' (inspect ' . self::HISTORY . ')');
        }
        if (!hash_equals($entry['checksum'], $row['checksum']) || !hash_equals($entry['definition'], $row['definition_checksum'])) throw new DewawiMigrationException('migration_checksum_mismatch: ' . $id);
        $statementCount = $id === self::BASELINE ? 0 : count($entry['statements']);
        if ((int)$row['statement_number'] !== $statementCount || self::timestamp($row['started_at']) === null || self::timestamp($row['completed_at']) === null || $row['completed_at'] < $row['started_at']) throw new DewawiMigrationException('ambiguous_completed_history: ' . $id);
    }

    protected function validateHistory(array $history): void
    {
        $seenPending = false;
        $all = [self::BASELINE => ['checksum' => @hash_file('sha256', $this->reference), 'definition' => @hash_file('sha256', $this->reference), 'depends' => []]] + $this->catalog;
        foreach ($history as $id => $row) {
            if (!isset($all[$id])) throw new DewawiMigrationException('missing_historical_migration');
            $this->validateRow($id, $row, $all[$id]);
        }
        foreach ($all as $id => $entry) {
            if (!isset($history[$id])) { $seenPending = true; continue; }
            if ($seenPending) throw new DewawiMigrationException('history_order_gap');
            foreach ($entry['depends'] as $dependency) if (!isset($history[$dependency])) throw new DewawiMigrationException('missing_applied_dependency');
        }
    }

    public function databaseIdentity(string $installation): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $installation)) throw new DewawiMigrationException('invalid_installation');
        $identity = $this->db->fetchRow('SELECT DATABASE() AS database_name, @@hostname AS server_host, @@port AS server_port, @@server_id AS server_id');
        return self::digest([$installation, $identity]);
    }

    public static function digest(array $value): string { return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); }

    public function executionPlan(string $installation, string $operation = 'migrate'): array
    {
        $history = $this->history();
        $this->validateHistory($history);
        if (!in_array($operation, ['migrate', 'baseline'], true)) throw new DewawiMigrationException('invalid_operation');
        if ($operation === 'migrate' && !isset($history[self::BASELINE])) throw new DewawiMigrationException('explicit_baseline_registration_required');
        if ($operation === 'baseline' && isset($history[self::BASELINE])) throw new DewawiMigrationException('baseline_already_registered');
        require_once dirname(__DIR__, 2) . '/library/DEEC/Version.php';
        $version = dewawiVersion();
        if ($version === null) throw new DewawiMigrationException('invalid_application_version');
        $catalog = [];
        foreach ($this->catalog as $entry) $catalog[] = array_intersect_key($entry, array_flip(['id', 'checksum', 'definition', 'depends', 'transition']));
        $plan = ['format' => 1, 'installation' => $installation, 'database' => $this->databaseIdentity($installation), 'version' => $version,
            'baseline' => $history[self::BASELINE]['checksum'] ?? hash_file('sha256', $this->reference), 'history' => self::digest(array_values($history)), 'catalog' => $catalog,
            'pending' => array_values(array_diff(array_keys($this->catalog), array_keys($history)))];
        if ($operation === 'baseline') {
            $plan['operation'] = 'baseline';
            $plan['history_table'] = $this->historyExists;
            $plan['pending'] = [self::BASELINE];
        }
        return $plan;
    }

    public function inspect(): array
    {
        $report = ['baseline' => 'unknown', 'last' => null, 'pending' => null, 'issues' => [], 'migrations' => []];
        try {
            $history = $this->history();
        } catch (Throwable $error) {
            $history = null;
            $report['issues'][] = 'history_unavailable';
        }
        $valid = false;
        if ($history !== null) {
            if (!$this->historyExists) $report['issues'][] = 'history_missing';
            try {
                $this->validateHistory($history);
                $valid = true;
            } catch (DewawiMigrationException $error) {
                $reason = explode(':', $error->getMessage(), 2)[0];
                $report['issues'][] = $reason === 'unresolved_migration' ? 'unresolved' : ($reason === 'migration_checksum_mismatch' ? 'checksum' : 'invalid_history');
            }
            if (!isset($history[self::BASELINE])) {
                $report['baseline'] = 'not_registered';
                $report['issues'][] = 'baseline_missing';
            } elseif ($valid) $report['baseline'] = 'completed';
            if ($valid) $report['pending'] = count(array_diff(array_keys($this->catalog), array_keys($history)));
        }
        $baselineHash = @hash_file('sha256', $this->reference);
        if (!is_string($baselineHash)) throw new DewawiMigrationException('missing_baseline_reference');
        $all = [self::BASELINE => ['checksum' => $baselineHash, 'definition' => $baselineHash, 'depends' => [], 'transition' => null]] + $this->catalog;
        foreach ($all as $id => $entry) {
            $row = $history[$id] ?? null;
            $checksum = $row ? (hash_equals($entry['checksum'], $row['checksum']) && hash_equals($entry['definition'], $row['definition_checksum']) ? 'matched' : 'mismatch') : 'not_registered';
            $status = $history === null ? 'unknown' : ($row ? 'unverified' : 'pending');
            if ($row) {
                try {
                    $this->validateRow($id, $row, $entry);
                    if ($valid) $status = 'completed';
                } catch (DewawiMigrationException $error) {
                    $status = in_array($row['status'], ['started', 'failed'], true) ? $row['status'] : ($checksum === 'mismatch' ? 'mismatch' : 'invalid');
                }
            }
            $completed = $row ? self::timestamp($row['completed_at']) : null;
            $item = ['id' => $id, 'transition' => $entry['transition'], 'status' => $status, 'completed' => $completed, 'started' => $row ? self::timestamp($row['started_at']) : null, 'checksum' => $history === null ? 'unknown' : $checksum];
            $report['migrations'][] = $item;
            if ($valid && $status === 'completed' && $id !== self::BASELINE) $report['last'] = $item;
        }
        return $report;
    }

    private static function timestamp(?string $value): ?string
    {
        if ($value === null) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d H:i:s') === $value && substr($value, 0, 4) !== '0000' ? $value : null;
    }

}
