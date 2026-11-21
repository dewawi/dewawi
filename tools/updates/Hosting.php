<?php

require_once dirname(__DIR__, 2) . '/library/DEEC/UpdateMaintenance.php';
require_once dirname(__DIR__) . '/migrations/Inspector.php';

// Variant A deliberately shares the web account and its database privileges.
class DewawiHostingUpdates
{
    public DEEC_UpdateMaintenance $files;
    private Zend_Db_Adapter_Abstract $db;
    private array $control;
    private string $catalog;
    public const CODES = ['configuration', 'busy', 'maintenance', 'evidence', 'rights', 'authentication', 'confirmation', 'plan_changed', 'expired', 'manual_review', 'alternative', 'verification', 'state_io'];

    public function __construct(string $path, Zend_Db_Adapter_Abstract $db, string $catalog, bool $initializing = false)
    {
        if (!$db->getConnection() instanceof PDO || $db->getConnection()->getAttribute(PDO::ATTR_PERSISTENT)) throw new RuntimeException('configuration');
        if ($initializing && !is_dir($path)) {
            DewawiUpdateFiles::outside(dirname($path));
            if (!mkdir($path, 0700)) throw new RuntimeException('configuration');
        }
        $this->files = new DEEC_UpdateMaintenance($path); $this->db = $db; $this->catalog = $catalog;
        if ($initializing) return;
        $this->control = $this->files->read('control.json');
        if (!DewawiUpdateFiles::keys($this->control, ['format', 'installation', 'database', 'topology']) || $this->control['format'] !== 1
            || !DewawiUpdateFiles::hex($this->control['installation']) || !DewawiUpdateFiles::hex($this->control['database'], 64)
            || $this->control['topology'] !== 'single-local-host') throw new RuntimeException('configuration');
    }

    public static function session(): Zend_Session_Namespace { return new Zend_Session_Namespace('HostingUpdates'); }

    public function initialize(): void
    {
        if (!$this->files->exists('gate.lock')) $this->files->write('gate.lock', []);
        $this->files->exclusive(function(): void {
            $installation = bin2hex(random_bytes(16));
            $i = new DewawiMigrationInspector($this->db, $this->catalog);
            $this->files->write('control.json', ['format' => 1, 'installation' => $installation, 'database' => $i->databaseIdentity($installation), 'topology' => 'single-local-host']);
        });
    }

    private static function context(): array
    {
        return [(int)Zend_Registry::get('User')['id'], (int)Zend_Registry::get('Client')['id'], hash('sha256', Zend_Session::getId())];
    }

    public static function csrf(): string
    {
        $s = self::session(); $context = self::context();
        if ($s->context !== $context) { $s->context = $context; $s->csrf = bin2hex(random_bytes(32)); $s->approval = null; }
        return $s->csrf;
    }

    private function operator(int $id, ?string $password = null): void
    {
        $u = $this->db->fetchRow('SELECT id, admin, activated, deleted, password FROM user WHERE id = ?', [$id]);
        if (!$u || (int)$u['admin'] !== 1 || (int)$u['activated'] !== 1 || (int)$u['deleted'] !== 0) throw new RuntimeException('authentication');
        // Match the existing login verifier, without rehashing during maintenance.
        if ($password !== null && ($password === '' || strlen($password) > 4096 || (!password_verify($password, $u['password'])
            && !(preg_match('/^[a-f0-9]{32}$/D', $u['password']) && hash_equals($u['password'], md5($password)))))) throw new RuntimeException('authentication');
    }

    public function authorize(array $post, array $keys, bool $password = false): void
    {
        if (!DEEC_SystemOperator::allowed()) throw new RuntimeException('authentication');
        if (!DewawiUpdateFiles::keys($post, $keys) || !is_string($post['csrf'] ?? null) || !hash_equals(self::csrf(), $post['csrf'])) throw new RuntimeException('confirmation');
        if ($password && !is_string($post['password'] ?? null)) throw new RuntimeException('authentication');
        $this->operator(self::context()[0], $password ? $post['password'] : null);
    }

    private function jobs(): array
    {
        $paths = glob($this->files->root . '/*.request.json');
        if (count($paths) > 10000) throw new RuntimeException('configuration');
        $jobs = [];
        foreach ($paths as $path) {
            $r = $this->files->read(basename($path)); $id = $r['id'];
            if (!DewawiUpdateFiles::hex($id)) throw new RuntimeException('configuration');
            $r['status'] = $this->files->exists($id . '.accepted.json') ? 'manual_review' : ($r['expires'] > time() ? 'approved' : 'expired');
            if ($this->files->exists($id . '.result.json')) {
                $result = $this->files->read($id . '.result.json');
                $r['status'] = $result['status']; $r['code'] = $result['code'] ?? null;
            }
            $jobs[] = $r;
        }
        usort($jobs, static fn($a, $b): int => ($a['created_at'] ?? $a['expires']) <=> ($b['created_at'] ?? $b['expires']));
        return $jobs;
    }

    private function settled(): void
    {
        foreach ($this->jobs() as $r) if (!in_array($r['status'], ['completed', 'expired', 'rejected'], true)) throw new RuntimeException('manual_review');
    }

    public function status(): array
    {
        $s = self::session(); $plan = $approval = null;
        if (is_array($s->approval) && $s->approval['expires'] > time() && $s->approval['context'] === self::context()) {
            $approval = $s->approval; $plan = $this->files->read($approval['id'] . '.plan.json');
        }
        $jobs = $this->jobs(); $pending = null;
        foreach ($jobs as $r) if ($r['status'] === 'approved' && $r['context'] === self::context()) $pending = $r['id'];
        $limit = (int)ini_get('max_execution_time');
        $http = str_contains($this->db->fetchOne('SELECT VERSION()'), 'MariaDB') && ($limit === 0 || $limit >= 25) && (!$plan || $plan['http_seconds'] <= 10);
        return ['maintenance' => $this->files->maintenance(), 'plan' => $plan, 'approval' => $approval, 'pending' => $pending,
            'http_available' => $http, 'jobs' => array_slice($jobs, -10), 'evidence' => $this->files->exists('evidence.json'), 'rights' => $this->rights(false)];
    }

    public function begin(): void
    {
        $this->files->exclusive(function(): void {
            $this->settled();
            if (!$this->files->maintenance()) $this->files->write('maintenance.json', ['id' => bin2hex(random_bytes(16)), 'since' => time(), 'operator' => self::context()[0]]);
        });
    }

    public function finish(): void
    {
        $this->files->exclusive(function(): void {
            $this->settled();
            $report = (new DewawiMigrationInspector($this->db, $this->catalog))->inspect();
            if (array_intersect($report['issues'], ['unresolved', 'invalid_history', 'checksum'])) throw new RuntimeException('manual_review');
            $this->files->end();
        });
    }

    private function migrator(): DewawiSqlMigrator
    {
        require_once dirname(__DIR__) . '/migrations/Migrator.php';
        return new DewawiSqlMigrator($this->db, $this->catalog);
    }

    private function rights(bool $baseline): bool
    {
        $database = $this->db->quoteIdentifier($this->db->fetchOne('SELECT DATABASE()')) . '.*';
        $rights = [];
        foreach ($this->db->fetchAll('SHOW GRANTS') as $row) {
            $grant = (string)array_values($row)[0];
            if (!preg_match('/^GRANT (.+) ON (.+) TO /', $grant, $m) || !in_array($m[2], [$database, '*.*'], true)) continue;
            if ($m[1] === 'ALL PRIVILEGES') return true;
            $rights = array_merge($rights, explode(', ', $m[1]));
        }
        return !array_diff($baseline ? ['SELECT', 'CREATE', 'INSERT', 'UPDATE'] : ['SELECT', 'CREATE', 'ALTER', 'INDEX', 'DROP', 'INSERT', 'UPDATE', 'DELETE', 'REFERENCES'], $rights);
    }

    private static function reference($value): bool { return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9 .:_-]{0,159}$/D', $value); }

    private function artifact(array $value): void
    {
        if (!is_string($value['file'] ?? null) || !DewawiUpdateFiles::hex($value['sha256'] ?? null, 64)) throw new RuntimeException('evidence');
        $p = $value['file']; DewawiUpdateFiles::outside($p === '' ? '' : dirname($p));
        clearstatcache(true, $p); $s = lstat($p);
        if (realpath($p) !== $p || !$s || ($s['mode'] & 0170000) !== 0100000 || ($s['mode'] & 0077) !== 0 || $s['nlink'] !== 1 || !hash_equals($value['sha256'], hash_file('sha256', $p))) throw new RuntimeException('evidence');
    }

    private function evidence(array $m1): array
    {
        $e = $this->files->read('evidence.json'); $maintenance = $this->files->maintenance(); $now = time();
        $selected = $m1['pending'][0]; $baseline = ($m1['operation'] ?? '') === 'baseline';
        $checksum = $baseline ? $m1['baseline'] : array_values(array_filter($m1['catalog'], static fn($entry): bool => $entry['id'] === $selected))[0]['checksum'];
        if (!DewawiUpdateFiles::keys($e, ['format', 'maintenance', 'installation', 'database', 'expires_at', 'external_writers_stopped', 'writers', 'backup', 'restore', 'historical', 'execution'])
            || $e['format'] !== 1 || !$maintenance || $e['maintenance'] !== $maintenance['id'] || $e['installation'] !== $this->control['installation'] || $e['database'] !== $this->control['database']
            || !is_int($e['expires_at']) || $e['expires_at'] <= $now || $e['external_writers_stopped'] !== true
            || !is_array($e['writers']) || !is_int($e['writers']['checked_at'] ?? null) || $e['writers']['checked_at'] < $maintenance['since'] || $e['writers']['checked_at'] > $now || !self::reference($e['writers']['confirmed_by'] ?? null)
            || !is_array($e['backup']) || !is_int($e['backup']['taken_at'] ?? null) || $e['backup']['taken_at'] < max($maintenance['since'], $now - 3600) || $e['backup']['taken_at'] > $now
            || !is_array($e['restore']) || !is_int($e['restore']['tested_at'] ?? null) || $e['restore']['tested_at'] < $e['backup']['taken_at'] || $e['restore']['tested_at'] > $now || !self::reference($e['restore']['confirmed_by'] ?? null)
            || !is_array($e['execution']) || ($e['execution']['migration'] ?? '') !== $selected || ($e['execution']['checksum'] ?? '') !== $checksum
            || !is_int($e['execution']['http_seconds'] ?? null) || $e['execution']['http_seconds'] < 1) throw new RuntimeException('evidence');
        $this->artifact($e['writers']); $this->artifact($e['backup']); $this->artifact($e['restore']);
        if ($baseline) {
            if (!is_array($e['historical']) || !is_int($e['historical']['checked_at'] ?? null) || $e['historical']['checked_at'] < $maintenance['since'] || $e['historical']['checked_at'] > $now
                || !self::reference($e['historical']['confirmed_by'] ?? null)) throw new RuntimeException('evidence');
            $this->artifact($e['historical']);
        }
        return $e;
    }

    private function plan(DewawiSqlMigrator $m): array
    {
        if (!$this->files->maintenance()) throw new RuntimeException('maintenance');
        try { $p = $m->executionPlan($this->control['installation']); }
        catch (DewawiMigrationException $e) {
            if ($e->getMessage() !== 'explicit_baseline_registration_required') throw $e;
            $p = $m->baselinePlan($this->control['installation']);
        }
        if ($p['database'] !== $this->control['database']) throw new RuntimeException('plan_changed');
        if (!$p['pending']) throw new RuntimeException('verification');
        if (!$this->rights(($p['operation'] ?? '') === 'baseline')) throw new RuntimeException('rights');
        $e = $this->evidence($p);
        return ['format' => 1, 'mode' => 'shared', 'm1' => $p, 'selected' => $p['pending'][0], 'maintenance' => $this->files->maintenance()['id'],
            'evidence_sha256' => hash('sha256', $this->files->bytes('evidence.json')), 'http_seconds' => $e['execution']['http_seconds']];
    }

    public function prepare(): void
    {
        $this->files->exclusive(function(): void {
            $this->settled(); $m = $this->migrator();
            $m->locked(function() use ($m): void {
                $p = $this->plan($m); $id = bin2hex(random_bytes(16));
                $this->files->write($id . '.plan.json', $p);
                self::session()->approval = ['id' => $id, 'digest' => DewawiMigrationInspector::digest($p), 'nonce' => bin2hex(random_bytes(16)), 'context' => self::context(), 'expires' => time() + 600];
            });
        });
    }

    public function approve(array $post): void
    {
        $a = self::session()->approval;
        if (!is_array($a) || $a['context'] !== self::context() || $a['expires'] <= time() || !is_string($post['nonce']) || !hash_equals($a['nonce'], $post['nonce']) || $post['plan'] !== $a['digest'] || $post['confirm'] !== '1') throw new RuntimeException('confirmation');
        $this->files->exclusive(function() use ($a): void {
            $this->settled(); $m = $this->migrator();
            $m->locked(function() use ($m, $a): void {
                $p = $this->files->read($a['id'] . '.plan.json');
                if (!hash_equals($a['digest'], DewawiMigrationInspector::digest($this->plan($m))) || DewawiMigrationInspector::digest($p) !== $a['digest']) throw new RuntimeException('plan_changed');
                $this->files->write($a['id'] . '.request.json', ['id' => $a['id'], 'operator' => self::context()[0], 'context' => self::context(), 'created_at' => time(), 'expires' => $a['expires'], 'digest' => $a['digest'], 'selected' => $p['selected']]);
                self::session()->approval = null;
            });
        });
    }

    public function execute(string $id, bool $cli = false): string
    {
        if (!DewawiUpdateFiles::hex($id)) throw new RuntimeException('confirmation');
        return $this->files->exclusive(function() use ($id, $cli): string {
            $r = $this->files->read($id . '.request.json');
            if (!$cli && $r['context'] !== self::context()) throw new RuntimeException('confirmation');
            if ($this->files->exists($id . '.accepted.json') || $this->files->exists($id . '.result.json')) throw new RuntimeException('manual_review');
            if ($r['expires'] <= time()) throw new RuntimeException('expired');
            foreach ($this->jobs() as $job) if ($job['id'] !== $id && !in_array($job['status'], ['completed', 'expired', 'rejected'], true)) throw new RuntimeException('manual_review');
            $m = $this->migrator();
            try { return $m->locked(function() use ($m, $r, $id, $cli): string {
                $p = $this->files->read($id . '.plan.json');
                if (!hash_equals($r['digest'], DewawiMigrationInspector::digest($p)) || !hash_equals($r['digest'], DewawiMigrationInspector::digest($this->plan($m)))) throw new RuntimeException('plan_changed');
                $this->operator($r['operator']);
                $deadline = microtime(true) + ($cli ? 3600 : 20);
                if (!$cli) {
                    $remaining = (int)ini_get('max_execution_time');
                    if ($p['http_seconds'] > 10 || ($remaining > 0 && $remaining - (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) < 25)
                        || !str_contains($this->db->fetchOne('SELECT VERSION()'), 'MariaDB')) throw new RuntimeException('alternative');
                    ignore_user_abort(true);
                    $this->db->query('SET SESSION lock_wait_timeout = 3, innodb_lock_wait_timeout = 3, max_statement_time = 5');
                }
                $guard = function() use ($r, $p, $deadline): void {
                    if (microtime(true) >= $deadline || $r['expires'] <= time()) throw new RuntimeException('expired');
                    $e = $this->files->read('evidence.json');
                    if ($e['expires_at'] <= time() || $e['external_writers_stopped'] !== true || $this->files->maintenance()['id'] !== $p['maintenance']
                        || !hash_equals($p['evidence_sha256'], hash('sha256', $this->files->bytes('evidence.json')))
                        || (new DewawiMigrationInspector($this->db, $this->catalog))->databaseIdentity($this->control['installation']) !== $this->control['database']) throw new RuntimeException('evidence');
                    $this->operator($r['operator']);
                };
                $guard();
                $evidence = $this->files->bytes('evidence.json');
                if (!hash_equals($p['evidence_sha256'], hash('sha256', $evidence))) throw new RuntimeException('evidence');
                $this->files->write($id . '.evidence.json', ['source_sha256' => $p['evidence_sha256'], 'source' => $evidence]);
                $this->files->write($id . '.accepted.json', $r + ['at' => time(), 'transport' => $cli ? 'cli' : 'http']);
                $status = 'completed'; $code = null;
                try {
                    if (($p['m1']['operation'] ?? '') === 'baseline') $m->registerApprovedBaseline($p['m1'], $this->control['installation'], $guard);
                    else $m->applyApproved($p['m1'], $this->control['installation'], $guard, true);
                } catch (Throwable $e) { $status = 'manual_review'; $code = in_array($e->getMessage(), self::CODES, true) ? $e->getMessage() : 'manual_review'; }
                $this->files->write($id . '.result.json', ['id' => $id, 'selected' => $p['selected'], 'operator' => $r['operator'], 'status' => $status, 'code' => $code, 'at' => time()]);
                return $status;
            }); } catch (Throwable $e) {
                if (!$this->files->exists($id . '.accepted.json') && !$this->files->exists($id . '.result.json') && !in_array($e->getMessage(), ['alternative', 'migration_lock_busy'], true)) {
                    $this->files->write($id . '.result.json', ['id' => $id, 'selected' => $r['selected'], 'operator' => $r['operator'], 'status' => 'rejected',
                        'code' => in_array($e->getMessage(), self::CODES, true) ? $e->getMessage() : 'verification', 'at' => time()]);
                }
                throw $e;
            }
        });
    }
}
