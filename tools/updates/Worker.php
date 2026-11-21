<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/Files.php';
require_once dirname(__DIR__) . '/migrations/Migrator.php';

class DewawiUpdateWorker
{
    private DewawiUpdateFiles $files;
    private Zend_Db_Adapter_Abstract $db;
    private array $recent = [];

    public function __construct(DewawiUpdateFiles $files, Zend_Db_Adapter_Abstract $db)
    {
        $this->files = $files; $this->db = $db;
    }

    public function identity(): string { return $this->migrator()->databaseIdentity($this->files->policy['installation']); }

    private function migrator(): DewawiSqlMigrator
    {
        return new DewawiSqlMigrator($this->db, dirname(__DIR__, 2) . '/install/config/migrations');
    }

    private function plan(DewawiSqlMigrator $migrator): array
    {
        try { $plan = $migrator->executionPlan($this->files->policy['installation']); }
        catch (DewawiMigrationException $error) {
            if ($error->getMessage() !== 'explicit_baseline_registration_required') throw $error;
            $plan = $migrator->baselinePlan($this->files->policy['installation']);
        }
        if (!hash_equals($this->files->policy['database'], $plan['database'])) throw new RuntimeException('database_identity');
        return $plan;
    }

    private function publish(?array $plan, array $issues = [], ?array $job = null, ?array $previous = null): array
    {
        $state = ['format' => 1, 'installation' => $this->files->policy['installation'], 'database' => $this->files->policy['database'],
            'token' => $previous['token'] ?? bin2hex(random_bytes(16)), 'digest' => $plan ? DewawiSqlMigrator::digest($plan) : null,
            'created_at' => $previous['created_at'] ?? time(), 'expires_at' => $previous['expires_at'] ?? time() + 900,
            'heartbeat_until' => time() + 60, 'plan' => $plan, 'issues' => $issues, 'job' => $job, 'recent' => array_slice($this->recent, -10)];
        $this->files->write('published/current.json', $state);
        return $state;
    }

    private function result(string $id, string $status, string $code, ?array $request = null, string $operation = 'migrate'): array
    {
        $result = ['id' => $id, 'status' => $status, 'code' => $code, 'at' => time(), 'operator' => $request['operator'] ?? null,
            'digest' => $request['digest'] ?? null, 'operation' => $operation];
        $this->files->write('private/' . $id . '.result.json', $result, true);
        $this->files->write('published/' . $id . '.result.json', $result, true);
        $this->recent[] = $result;
        return $result;
    }

    private function request(array $request, array $state): void
    {
        if (!DewawiUpdateFiles::keys($request, ['format', 'id', 'operator', 'token', 'digest', 'created_at', 'expires_at']) || $request['format'] !== 1
            || !DewawiUpdateFiles::hex($request['id']) || !DewawiUpdateFiles::hex($request['token']) || !DewawiUpdateFiles::hex($request['digest'], 64)
            || !is_int($request['operator']) || $request['operator'] < 1
            || !is_int($request['created_at']) || !is_int($request['expires_at']) || $request['created_at'] > time() || $request['created_at'] < $state['created_at']
            || $request['expires_at'] > $state['expires_at'] || $request['expires_at'] <= time() || $request['expires_at'] > $request['created_at'] + 900
            || $request['token'] !== $state['token'] || $request['digest'] !== $state['digest'] || !$state['plan'] || !$state['plan']['pending']) throw new RuntimeException('request_invalid');
    }

    private function evidence(array $request, array $plan): array
    {
        $name = 'evidence/' . $request['id'] . '.json';
        if (!$this->files->exists($name)) throw new RuntimeException('evidence_required');
        $bytes = $this->files->bytes($name); $e = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        $now = time();
        if (!is_array($e) || !DewawiUpdateFiles::keys($e, ['format', 'request', 'request_sha256', 'plan', 'installation', 'database', 'operator', 'issued_at', 'expires_at', 'backup', 'restore', 'maintenance'])
            || $e['format'] !== 1 || $e['request'] !== $request['id'] || $e['operator'] !== $request['operator']
            || $e['request_sha256'] !== hash('sha256', $this->files->bytes('private/' . $request['id'] . '.request.json'))
            || $e['plan'] !== $request['digest'] || $e['installation'] !== $plan['installation'] || $e['database'] !== $plan['database']
            || !is_int($e['issued_at']) || !is_int($e['expires_at']) || $e['issued_at'] > $now || $e['issued_at'] < $request['created_at']
            || $e['expires_at'] <= $now || $e['expires_at'] > $request['expires_at'] || $e['expires_at'] > $e['issued_at'] + 900) throw new RuntimeException('evidence_invalid');
        $backup = $e['backup']; $restore = $e['restore']; $maintenance = $e['maintenance'];
        if (!is_array($backup) || !is_array($restore) || !is_array($maintenance)
            || !DewawiUpdateFiles::keys($backup, ['reference', 'sha256', 'taken_at', 'scope']) || !DewawiUpdateFiles::keys($restore, ['reference', 'confirmed_by', 'tested_at'])
            || !DewawiUpdateFiles::keys($maintenance, ['reference', 'since', 'until', 'privileged_writers_stopped'])
            || !self::reference($backup['reference']) || !DewawiUpdateFiles::hex($backup['sha256'], 64) || $backup['scope'] !== 'database-and-history'
            || !is_int($backup['taken_at']) || $backup['taken_at'] > $now || $backup['taken_at'] < $now - 3600
            || !self::reference($restore['reference']) || !self::reference($restore['confirmed_by']) || !is_int($restore['tested_at'])
            || $restore['tested_at'] < $backup['taken_at'] || $restore['tested_at'] > $now
            || !self::reference($maintenance['reference']) || !is_int($maintenance['since']) || !is_int($maintenance['until'])
            || $maintenance['since'] > $backup['taken_at'] || $maintenance['until'] < $e['expires_at'] || $maintenance['privileged_writers_stopped'] !== true) throw new RuntimeException('evidence_invalid');
        $proof = ['operational' => hash('sha256', $bytes)];
        if (($plan['operation'] ?? 'migrate') === 'baseline') {
            $name = 'evidence/' . $request['id'] . '.historical.json';
            if (!$this->files->exists($name)) throw new RuntimeException('historical_required');
            $bytes = $this->files->bytes($name); $h = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($h) || !DewawiUpdateFiles::keys($h, ['format', 'request', 'request_sha256', 'plan', 'installation', 'database', 'operator', 'scope', 'baseline_sha256', 'reference', 'report_sha256', 'confirmed_by', 'checked_at'])
                || $h['format'] !== 1 || $h['request'] !== $request['id'] || $h['operator'] !== $request['operator'] || $h['request_sha256'] !== $e['request_sha256']
                || $h['plan'] !== $request['digest'] || $h['installation'] !== $plan['installation'] || $h['database'] !== $plan['database']
                || $h['scope'] !== 'historical-data-through-1.1.8' || $h['baseline_sha256'] !== $plan['baseline']
                || !self::reference($h['reference']) || !self::reference($h['confirmed_by']) || !DewawiUpdateFiles::hex($h['report_sha256'], 64)
                || !is_int($h['checked_at']) || $h['checked_at'] < $maintenance['since'] || $h['checked_at'] > $now) throw new RuntimeException('historical_invalid');
            $proof['historical'] = hash('sha256', $bytes);
        }
        return $proof;
    }

    private static function reference($value): bool { return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9 .:_-]{0,159}$/D', $value); }

    private function fence(): void
    {
        if ((int)$this->db->fetchOne('SELECT @@GLOBAL.read_only') !== 1) throw new RuntimeException('maintenance_required');
    }

    private function operator(int $id): void
    {
        $user = $this->db->fetchRow('SELECT id, admin, activated, deleted FROM user WHERE id = ?', [$id]);
        if (!$user || (int)$user['admin'] !== 1 || (int)$user['activated'] !== 1 || (int)$user['deleted'] !== 0) throw new RuntimeException('operator_denied');
    }

    public function tick(bool $publishOnly = false): array
    {
        $lock = $this->files->lock();
        try {
            $this->files->records('rejected');
            foreach (array_merge($this->files->records('accepted'), $this->files->records('result')) as $name) {
                $id = substr($name, 0, 32);
                if (!DewawiUpdateFiles::hex($id) || !$this->files->exists('private/' . $id . '.request.json')) return $this->publish(null, ['manual_review']);
            }
            $waiting = [];
            foreach ($this->files->records('request') as $name) {
                $id = substr($name, 0, 32);
                if (!DewawiUpdateFiles::hex($id) || $name !== $id . '.request.json') throw new RuntimeException('queue_unsafe');
                $request = $this->files->read('private/' . $name);
                if (!$this->files->exists('private/' . $id . '.result.json')) {
                    if ($this->files->exists('private/' . $id . '.accepted.json') || $this->files->exists('private/token-' . $request['token'] . '.json')) {
                        $plan = $this->files->read('private/' . $id . '.plan.json');
                        $this->result($id, 'manual_review', 'interrupted', $request, $plan['operation'] ?? 'migrate');
                    }
                    else $waiting[] = $request;
                } else {
                    $final = $this->files->read('private/' . $id . '.result.json');
                    $public = 'published/' . $id . '.result.json';
                    if (!$this->files->exists($public)) $this->files->write($public, $final, true);
                    elseif ($this->files->read($public) !== $final) throw new RuntimeException('queue_unsafe');
                    $this->recent[] = $final;
                }
            }
            usort($this->recent, static fn(array $a, array $b): int => $a['at'] <=> $b['at']);
            foreach ($this->recent as $result) if ($result['status'] === 'manual_review') return $this->publish(null, ['manual_review'], $result);
            if (count($waiting) > 1) throw new RuntimeException('queue_unsafe');
            $state = $this->files->exists('published/current.json') ? $this->files->read('published/current.json') : null;
            if (!$waiting && !$publishOnly && $state && $this->files->exists('inbox/request.json')) {
                try {
                    $input = $this->files->bytes('inbox/request.json');
                    $request = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
                    $done = DewawiUpdateFiles::hex($request['id'] ?? null) && $this->files->exists('private/' . $request['id'] . '.result.json');
                    if (!$done) {
                        $this->request($request, $state);
                        $this->files->write('private/' . $request['id'] . '.request.json', $request, true);
                        $waiting[] = $request;
                    }
                } catch (Throwable $error) {
                    $hash = hash('sha256', $input ?? json_encode(@lstat($this->files->root . '/inbox/request.json'), JSON_THROW_ON_ERROR));
                    $name = 'private/input-' . $hash . '.rejected.json';
                    if (!$this->files->exists($name)) $this->files->write($name, ['sha256' => $hash, 'code' => 'request_invalid', 'at' => time()], true);
                    return $this->publish(null, ['request_invalid']);
                }
            }
            if ($waiting) {
                $request = $waiting[0];
                if (!$state) return $this->publish(null, ['manual_review']);
                if ($publishOnly) return $this->publish($state['plan'], ['evidence_required'], ['id' => $request['id'], 'status' => 'waiting', 'code' => 'evidence_required', 'at' => time(), 'operator' => $request['operator'], 'digest' => $request['digest'], 'operation' => $state['plan']['operation'] ?? 'migrate'], $state);
                try {
                    $this->request($request, $state);
                    $migrator = $this->migrator();
                    return $migrator->locked(function() use ($migrator, $request, $state): array {
                        $this->operator($request['operator']);
                        $plan = $this->plan($migrator);
                        $operation = $plan['operation'] ?? 'migrate';
                        if (!hash_equals($request['digest'], DewawiSqlMigrator::digest($plan))) throw new RuntimeException('plan_changed');
                        $archived = 'private/' . $request['id'] . '.plan.json';
                        if (!$this->files->exists($archived)) $this->files->write($archived, $plan, true);
                        elseif (!hash_equals($request['digest'], DewawiSqlMigrator::digest($this->files->read($archived)))) throw new RuntimeException('plan_changed');
                        $proof = $this->evidence($request, $plan);
                        $guard = function() use ($request, $plan, $proof): void {
                            if (!hash_equals(DewawiSqlMigrator::digest($proof), DewawiSqlMigrator::digest($this->evidence($request, $plan))) || !hash_equals($plan['database'], $this->identity())) throw new RuntimeException('evidence_invalid');
                            $this->fence();
                            $this->operator($request['operator']);
                        };
                        $guard();
                        $release = $this->files->bytes('evidence/' . $request['id'] . '.json');
                        if (!hash_equals($proof['operational'], hash('sha256', $release))) throw new RuntimeException('evidence_invalid');
                        $archive = ['source_sha256' => $proof['operational'], 'evidence' => json_decode($release, true, 32, JSON_THROW_ON_ERROR)];
                        if (isset($proof['historical'])) {
                            $historical = $this->files->bytes('evidence/' . $request['id'] . '.historical.json');
                            if (!hash_equals($proof['historical'], hash('sha256', $historical))) throw new RuntimeException('historical_invalid');
                            $archive['historical'] = ['source_sha256' => $proof['historical'], 'evidence' => json_decode($historical, true, 32, JSON_THROW_ON_ERROR)];
                        }
                        $this->files->write('private/' . $request['id'] . '.release.json', $archive, true);
                        $this->files->write('private/token-' . $request['token'] . '.json', ['request' => $request['id']], true);
                        $accepted = ['id' => $request['id'], 'status' => 'running', 'code' => 'running', 'at' => time(), 'operator' => $request['operator'], 'digest' => $request['digest'], 'operation' => $operation];
                        $this->files->write('private/' . $request['id'] . '.accepted.json', $accepted, true);
                        $this->files->write('published/' . $request['id'] . '.accepted.json', $accepted, true);
                        $this->publish($plan, [], $accepted, $state);
                        $status = $code = 'completed';
                        try {
                            if ($operation === 'baseline') {
                                $migrator->registerApprovedBaseline($plan, $plan['installation'], $guard);
                                $code = 'baseline_registered';
                            } else $migrator->applyApproved($plan, $plan['installation'], $guard);
                        } catch (Throwable $error) {
                            $this->files->write('private/' . $request['id'] . '.diagnostic.json', ['reason' => $error instanceof DewawiMigrationException ? $error->getMessage() : 'execution_precondition_or_io_failure', 'at' => time()], true);
                            $failed = $error instanceof DewawiMigrationException && str_starts_with($error->getMessage(), 'migration_failed:');
                            $status = $failed ? 'failed' : 'manual_review'; $code = $operation === 'baseline' ? 'baseline_registration_failed' : ($failed ? 'migration_failed' : 'interrupted');
                        }
                        $result = $this->result($request['id'], $status, $code, $request, $operation);
                        $next = $operation === 'baseline' && $status === 'completed' ? $this->plan($migrator) : null;
                        return $this->publish($next, $result['status'] === 'completed' ? [] : ['manual_review'], $result);
                    });
                } catch (Throwable $error) {
                    if ($this->files->exists('private/' . $request['id'] . '.accepted.json') || $this->files->exists('private/token-' . $request['token'] . '.json')) throw $error;
                    $code = self::safeCode($error);
                    if (in_array($code, ['evidence_required', 'historical_required', 'migration_lock_busy'], true)) {
                        return $this->publish($state['plan'], [$code], ['id' => $request['id'], 'status' => 'waiting', 'code' => $code, 'at' => time(), 'operator' => $request['operator'], 'digest' => $request['digest'], 'operation' => $state['plan']['operation'] ?? 'migrate'], $state);
                    }
                    $result = $this->result($request['id'], 'rejected', $code, $request, $state['plan']['operation'] ?? 'migrate');
                    return $this->publish(null, [$code], $result);
                }
            }
            try {
                $migrator = $this->migrator();
                $plan = $migrator->locked(fn(): array => $this->plan($migrator));
                $issues = [];
                try { $this->fence(); } catch (Throwable $error) { $issues[] = 'maintenance_required'; }
                return $this->publish($plan, $issues, $this->recent ? end($this->recent) : null);
            } catch (Throwable $error) {
                return $this->publish(null, [self::safeCode($error)], $this->recent ? end($this->recent) : null);
            }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private static function safeCode(Throwable $error): string
    {
        $code = explode(':', $error->getMessage(), 2)[0];
        if ($code === 'explicit_baseline_registration_required') return 'baseline_required';
        if ($code === 'incomplete_1.1.8_schema') return 'baseline_incomplete';
        if (in_array($code, ['request_invalid', 'evidence_required', 'evidence_invalid', 'historical_required', 'historical_invalid', 'database_identity', 'plan_changed', 'maintenance_required', 'migration_lock_busy', 'queue_unsafe', 'operator_denied'], true)) return $code;
        return 'verification_failed';
    }
}
