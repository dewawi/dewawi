<?php

require_once dirname(__DIR__, 4) . '/tools/updates/Files.php';

class Admin_Service_SystemUpdates
{
    public const CODES = ['running', 'completed', 'interrupted', 'migration_failed', 'request_invalid', 'evidence_required', 'evidence_invalid', 'database_identity', 'plan_changed', 'maintenance_required', 'migration_lock_busy', 'queue_unsafe', 'operator_denied', 'baseline_required', 'verification_failed', 'manual_review', 'baseline_incomplete', 'historical_required', 'historical_invalid', 'baseline_registered', 'baseline_registration_failed'];
    private DewawiUpdateFiles $files;

    public function __construct(DewawiUpdateFiles $files) { $this->files = $files; }

    public function state(DewawiMigrationInspector $inspector): array
    {
        $state = $this->files->read('published/current.json');
        if (($state['format'] ?? null) !== 1 || ($state['installation'] ?? null) !== $this->files->policy['installation'] || ($state['database'] ?? null) !== $this->files->policy['database']
            || !DewawiUpdateFiles::hex($state['token'] ?? null) || !is_int($state['heartbeat_until'] ?? null) || !is_int($state['expires_at'] ?? null)) throw new RuntimeException('worker_unavailable');
        $state['available'] = $state['heartbeat_until'] >= time() && $state['expires_at'] > time();
        if ($state['plan'] !== null) {
            try {
                $operation = $state['plan']['operation'] ?? 'migrate';
                $actual = $inspector->executionPlan($this->files->policy['installation'], $operation);
                if ($operation === 'baseline') {
                    if (!DewawiUpdateFiles::hex($state['plan']['schema'] ?? null, 64)) throw new RuntimeException('plan_changed');
                    // HTTP only compares metadata; the worker repeats the full schema check under lock.
                    $actual['schema'] = $state['plan']['schema'];
                }
                if ($actual['database'] !== $state['database'] || !hash_equals(DewawiMigrationInspector::digest($actual), $state['digest'])) throw new RuntimeException('plan_changed');
                $state['plan'] = $actual;
            } catch (Throwable $error) { $state['available'] = false; $state['plan'] = null; $state['issues'][] = 'plan_changed'; }
        }
        $state['issues'] = array_values(array_unique(array_map(static fn($code): string => in_array($code, self::CODES, true) ? $code : 'verification_failed', $state['issues'])));
        $state['job'] = self::job($state['job']);
        $state['recent'] = array_values(array_filter(array_map([self::class, 'job'], $state['recent'])));
        return $state;
    }

    public static function job($job): ?array
    {
        if (!is_array($job) || !DewawiUpdateFiles::hex($job['id'] ?? null) || !in_array($job['status'] ?? null, ['waiting', 'running', 'completed', 'failed', 'manual_review', 'rejected'], true)
            || !is_int($job['at'] ?? null) || !in_array($job['code'] ?? null, self::CODES, true) || !in_array($job['operation'] ?? 'migrate', ['migrate', 'baseline'], true)) return null;
        return array_intersect_key($job, array_flip(['id', 'status', 'code', 'at', 'operation'])) + ['operation' => 'migrate'];
    }

    public static function session(): Zend_Session_Namespace { return new Zend_Session_Namespace('SystemUpdates'); }

    public function confirmation(array $state): ?array
    {
        if (!$state['available'] || !$state['plan'] || !$state['plan']['pending'] || $state['issues'] || ($state['job'] && in_array($state['job']['status'], ['waiting', 'running', 'manual_review'], true))) return null;
        if ($this->files->exists('inbox/request.json')) {
            $pending = $this->files->read('inbox/request.json');
            if (($pending['token'] ?? null) === $state['token']) return null;
        }
        $s = self::session();
        $context = [(int)Zend_Registry::get('User')['id'], (int)Zend_Registry::get('Client')['id'], hash('sha256', Zend_Session::getId()), $state['token'], $state['digest']];
        if (!is_array($s->approval) || $s->approval['context'] !== $context || $s->approval['expires'] <= time()) {
            $s->approval = ['context' => $context, 'csrf' => bin2hex(random_bytes(32)), 'nonce' => bin2hex(random_bytes(16)), 'expires' => min(time() + 600, $state['expires_at'])];
        }
        return $s->approval;
    }

    public function approve(array $post, DewawiMigrationInspector $inspector): string
    {
        if (!DEEC_SystemOperator::allowed()) throw new Zend_Controller_Action_Exception('Forbidden', 403);
        $s = self::session(); $approval = $s->approval;
        if (!DewawiUpdateFiles::keys($post, ['csrf', 'nonce', 'plan', 'confirm']) || !is_array($approval) || !is_string($post['csrf']) || !is_string($post['nonce'])
            || !is_string($post['plan']) || $post['confirm'] !== '1' || !hash_equals($approval['csrf'], $post['csrf']) || !hash_equals($approval['nonce'], $post['nonce']) || $approval['expires'] <= time()) throw new Zend_Controller_Action_Exception('Forbidden', 403);
        $lock = $this->files->lock();
        try {
            $state = $this->state($inspector);
            if ($this->confirmation($state) !== $approval || $post['plan'] !== $state['digest']) throw new RuntimeException('plan_changed');
            if ($this->files->exists('inbox/request.json')) {
                $old = $this->files->read('inbox/request.json');
                if (($old['token'] ?? null) === $state['token']) throw new RuntimeException('approval_pending');
                if (!DewawiUpdateFiles::hex($old['id'] ?? null) || !$this->files->exists('published/' . $old['id'] . '.result.json')) throw new RuntimeException('approval_pending');
            }
            $id = bin2hex(random_bytes(16));
            $this->files->write('inbox/request.json', ['format' => 1, 'id' => $id, 'operator' => $approval['context'][0], 'token' => $state['token'], 'digest' => $state['digest'], 'created_at' => time(), 'expires_at' => $approval['expires']]);
            $s->approval = null;
            $s->submitted = ['id' => $id, 'at' => time()];
            return $id;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
