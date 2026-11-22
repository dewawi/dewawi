<?php

require_once dirname(__DIR__, 2) . '/library/DEEC/UpdateDatabase.php';
require_once dirname(__DIR__) . '/migrations/Inspector.php';

// Approval and transport around M1; SQL parsing, execution and history stay in M1.
class DewawiDatabaseUpdates
{
    private Zend_Db_Adapter_Abstract $db;
    private DEEC_UpdateDatabase $store;
    private string $catalog;
    private array $options;
    private string $root;

    public function __construct(Zend_Db_Adapter_Abstract $db, string $root, array $options = [])
    {
        if (!$db->getConnection() instanceof PDO || $db->getConnection()->getAttribute(PDO::ATTR_PERSISTENT)) throw new RuntimeException('configuration');
        $this->db = $db; $this->root = $root; $this->catalog = $root . '/install/config/migrations'; $this->options = $options;
        $this->store = new DEEC_UpdateDatabase($db);
    }

    private static function context(): array
    {
        return [(int)Zend_Registry::get('User')['id'], (int)Zend_Registry::get('Client')['id'], hash('sha256', Zend_Session::getId())];
    }

    public static function session(): Zend_Session_Namespace { return new Zend_Session_Namespace('DatabaseUpdates'); }

    public static function csrf(): string
    {
        $s = self::session();
        if ($s->context !== self::context()) { $s->context = self::context(); $s->csrf = bin2hex(random_bytes(32)); $s->job = null; }
        return $s->csrf;
    }

    private function operator(int $id): void
    {
        $u = $this->db->fetchRow('SELECT id, admin, activated, deleted FROM user WHERE id = ?', [$id]);
        if (!$u || (int)$u['admin'] !== 1 || (int)$u['activated'] !== 1 || (int)$u['deleted'] !== 0) throw new RuntimeException('authentication');
    }

    public function authorize(array $post, array $keys): void
    {
        if (!DEEC_SystemOperator::allowed()) throw new RuntimeException('authentication');
        if (array_diff(array_keys($post), $keys) || array_diff($keys, array_keys($post)) || !is_string($post['csrf']) || !hash_equals(self::csrf(), $post['csrf'])) throw new RuntimeException('confirmation');
        $this->operator(self::context()[0]);
    }

    private function legacy(): void
    {
        // Never supersede a configured or pinned file/worker installation, even if broken.
        if (!empty($this->options['queuePath']) || array_key_exists('statePath', $this->options)
            || @lstat($this->root . '/configs/update-storage.json') !== false || @lstat($this->root . '/configs/update-storage.ready.json') !== false) throw new RuntimeException('legacy');
        $base = getenv('DEWAWI_GOBD_REPORT_DIR');
        if ($base && @lstat($base . '/dewawi-updates-' . substr(hash('sha256', $this->root), 0, 32)) !== false) throw new RuntimeException('legacy');
    }

    private function control(): array
    {
        $c = $this->store->read('control');
        if (!is_array($c) || array_keys($c) !== ['format', 'installation', 'database'] || $c['format'] !== 1 || !is_string($c['installation']) || !preg_match('/^[a-f0-9]{32}$/D', $c['installation'])
            || (new DewawiMigrationInspector($this->db, $this->catalog))->databaseIdentity($c['installation']) !== $c['database']) throw new RuntimeException('plan_changed');
        return $c;
    }

    public function status(): array
    {
        $this->legacy();
        if (!$this->store->exists()) return ['initialized' => false, 'maintenance' => false, 'job' => null];
        $this->control(); $this->settled();
        $job = self::session()->job;
        $job = is_string($job) ? $this->store->read('job:' . $job) : null;
        if ($job && ($job['context'] !== self::context() || $job['expires'] <= time() || $this->store->read('accepted:' . $job['id']))) $job = null;
        return ['initialized' => true, 'maintenance' => $this->store->read('maintenance') !== null, 'job' => $job];
    }

    private function settled(): void
    {
        foreach ($this->store->jobs() as $job) if ($this->store->read('accepted:' . $job['id']) && ($this->store->read('result:' . $job['id'])['status'] ?? '') !== 'completed') throw new RuntimeException('manual_review');
    }

    private function migrator(): DewawiSqlMigrator
    {
        require_once dirname(__DIR__) . '/migrations/Migrator.php';
        return new DewawiSqlMigrator($this->db, $this->catalog);
    }

    private function plan(DewawiSqlMigrator $m, array $c): array
    {
        try { return $m->executionPlan($c['installation']); }
        catch (DewawiMigrationException $e) {
            if ($e->getMessage() !== 'explicit_baseline_registration_required') throw $e;
            return $m->baselinePlan($c['installation']);
        }
    }

    private function rights(bool $baseline): void
    {
        $database = $this->db->quoteIdentifier($this->db->fetchOne('SELECT DATABASE()')) . '.*'; $rights = [];
        foreach ($this->db->fetchAll('SHOW GRANTS') as $row) {
            if (!preg_match('/^GRANT (.+) ON (.+) TO /', (string)array_values($row)[0], $m) || !in_array($m[2], [$database, '*.*'], true)) continue;
            if ($m[1] === 'ALL PRIVILEGES') return;
            $rights = array_merge($rights, explode(', ', $m[1]));
        }
        if (array_diff($baseline ? ['SELECT', 'CREATE', 'INSERT', 'UPDATE', 'DELETE'] : ['SELECT', 'CREATE', 'ALTER', 'INDEX', 'DROP', 'INSERT', 'UPDATE', 'DELETE', 'REFERENCES'], $rights)) throw new RuntimeException('rights');
    }

    private function httpAvailable(array $plan): bool
    {
        $limit = (int)ini_get('max_execution_time');
        if (!str_contains($this->db->fetchOne('SELECT VERSION()'), 'MariaDB') || ($limit > 0 && $limit < 25)) return false;
        if (($plan['operation'] ?? '') === 'baseline') return true;
        $id = $plan['pending'][0];
        $sql = file_get_contents($this->catalog . '/' . $id);
        $statements = DewawiMigrationInspector::statements($sql);
        if (count($statements) > 20 || strlen($sql) > 131072) return false;
        foreach ($statements as $statement) {
            $plain = trim(preg_replace('/\/\*.*?\*\/|--[^\S\r\n][^\r\n]*|#[^\r\n]*/s', '', $statement));
            if (!preg_match('/^(?:CREATE\s+TABLE|ALTER\s+TABLE\s+`?\w+`?\s+ADD\s)/i', $plain) || preg_match('/\b(?:SELECT|DROP|RENAME|MODIFY|CHANGE)\b/i', $plain)) return false;
        }
        $largest = $this->db->fetchOne('SELECT MAX(COALESCE(DATA_LENGTH,0) + COALESCE(INDEX_LENGTH,0)) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        return (int)$largest <= 8 * 1024 * 1024;
    }

    private function preflight(array $plan): void
    {
        if (($plan['operation'] ?? '') === 'baseline' || !$plan['pending']) return;
        foreach (DewawiMigrationInspector::statements(file_get_contents($this->catalog . '/' . $plan['pending'][0])) as $statement) {
            $plain = trim(preg_replace('/\/\*.*?\*\/|--[^\S\r\n][^\r\n]*|#[^\r\n]*/s', '', $statement));
            if (preg_match('/^CREATE\s+TABLE\s+`?(\w+)`?\s*\(/i', $plain, $m)
                && $this->db->fetchOne('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$m[1]])) throw new RuntimeException('ambiguous');
            if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+/i', $plain, $table)) {
                preg_match_all('/\bADD\s+COLUMN\s+`?(\w+)`?/i', $plain, $columns);
                foreach ($columns[1] as $column) if ($this->db->fetchOne('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table[1], $column])) throw new RuntimeException('ambiguous');
            }
        }
    }

    public function check(): void
    {
        $this->legacy();
        $this->rights(true);
        $installation = bin2hex(random_bytes(16));
        $this->store->initialize(['format' => 1, 'installation' => $installation, 'database' => (new DewawiMigrationInspector($this->db, $this->catalog))->databaseIdentity($installation)]);
        $this->store->exclusive(function(): void {
            $c = $this->control(); $this->settled();
            $m = $this->migrator();
            $m->locked(function() use ($m, $c): void {
                $plan = $this->plan($m, $c);
                if (!$plan['pending']) return;
                $this->preflight($plan);
                $this->rights(($plan['operation'] ?? '') === 'baseline');
                if (!$this->store->read('maintenance')) $this->store->write('maintenance', ['id' => bin2hex(random_bytes(16)), 'since' => time()]);
                $id = bin2hex(random_bytes(16));
                $this->store->write('job:' . $id, ['id' => $id, 'context' => self::context(), 'expires' => time() + 600, 'plan' => $plan,
                    'maintenance' => $this->store->read('maintenance'), 'http' => $this->httpAvailable($plan)]);
                self::session()->job = $id;
            });
        });
    }

    public function execute(string $id, array $evidence, bool $cli = false): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new RuntimeException('confirmation');
        $this->legacy();
        $this->store->exclusive(function() use ($id, $evidence, $cli): void {
            $c = $this->control(); $this->settled();
            $job = $this->store->read('job:' . $id);
            if (!$job || $job['expires'] <= time() || (!$cli && ($job['context'] !== self::context() || self::session()->job !== $id))) throw new RuntimeException('confirmation');
            if ($this->store->read('accepted:' . $id) || $this->store->read('result:' . $id)) throw new RuntimeException('manual_review');
            if ($cli) $evidence = $this->store->read('approval:' . $id) ?? [];
            $baseline = ($job['plan']['operation'] ?? '') === 'baseline';
            $this->rights($baseline);
            foreach (['backup', 'restore'] as $key) if (!is_string($evidence[$key] ?? null) || !preg_match('/^[\pL\pN][\pL\pN .:_\/\-]{2,159}$/uD', $evidence[$key])) throw new RuntimeException('evidence');
            if (($evidence['confirm'] ?? '') !== '1' || ($evidence['writers'] ?? '') !== '1' || ($baseline && ($evidence['historical'] ?? '') !== '1')) throw new RuntimeException('evidence');
            if ($baseline && (!is_string($evidence['historical_reference'] ?? null) || !preg_match('/^[\pL\pN][\pL\pN .:_\/\-]{2,159}$/uD', $evidence['historical_reference']))) throw new RuntimeException('evidence');
            $this->operator($job['context'][0]);
            $m = $this->migrator();
            $m->locked(function() use ($m, $job, $id, $evidence, $c, $cli): void {
                if (DewawiMigrationInspector::digest($this->plan($m, $c)) !== DewawiMigrationInspector::digest($job['plan']) || $this->store->read('maintenance') !== $job['maintenance']) throw new RuntimeException('plan_changed');
                $this->preflight($job['plan']);
                $approval = $this->store->read('approval:' . $id);
                if ($approval && array_intersect_key($approval, $evidence) !== $evidence) throw new RuntimeException('confirmation');
                if (!$approval) $this->store->write('approval:' . $id, $evidence + ['operator' => $job['context'][0], 'at' => time()]);
                if (!$cli && (!$job['http'] || !$this->httpAvailable($job['plan']))) throw new RuntimeException('alternative');
                if (!$cli) {
                    $remaining = (int)ini_get('max_execution_time');
                    if ($remaining > 0 && $remaining - (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) < 25) throw new RuntimeException('alternative');
                    $this->db->query('SET SESSION lock_wait_timeout = 3, innodb_lock_wait_timeout = 3, max_statement_time = 5');
                    ignore_user_abort(true);
                }
                $deadline = microtime(true) + ($cli ? 3600 : 20);
                $guard = function() use ($job, $c, $deadline): void {
                    if (microtime(true) >= $deadline || $job['expires'] <= time() || $this->control() !== $c || $this->store->read('maintenance') !== $job['maintenance']) throw new RuntimeException('expired');
                    $this->operator($job['context'][0]);
                };
                $guard();
                $this->store->write('accepted:' . $id, ['at' => time(), 'operator' => $job['context'][0], 'transport' => $cli ? 'cli' : 'http']);
                try {
                    if (($job['plan']['operation'] ?? '') === 'baseline') $m->registerApprovedBaseline($job['plan'], $c['installation'], $guard);
                    else $m->applyApproved($job['plan'], $c['installation'], $guard, true);
                    $this->store->write('result:' . $id, ['status' => 'completed', 'at' => time()]);
                    $this->store->finish(); if (!$cli) self::session()->job = null;
                } catch (Throwable $e) {
                    $this->store->write('result:' . $id, ['status' => 'failed', 'at' => time()]);
                    throw new RuntimeException('manual_review');
                }
            });
        });
    }

    public function finish(): void
    {
        $this->legacy();
        $this->store->exclusive(function(): void {
            $this->control(); $this->settled();
            $report = (new DewawiMigrationInspector($this->db, $this->catalog))->inspect();
            if (array_diff($report['issues'], ['history_missing', 'baseline_missing'])) throw new RuntimeException('manual_review');
            $this->store->finish(); self::session()->job = null;
        });
    }
}
