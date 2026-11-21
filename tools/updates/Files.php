<?php

// Queue paths are installation configuration, never request parameters.
class DewawiUpdateFiles
{
    public array $policy;
    public string $root;
    private string $role;

    public function __construct(string $root, string $role)
    {
        if (!in_array($role, ['web', 'worker'], true)) throw new RuntimeException('queue_unsafe');
        $this->root = $root; $this->role = $role;
        self::outside($root);
        $this->directory($root, 0, null, 0755);
        $this->policy = $this->read('control.json', 0, null, 0644);
        $p = $this->policy;
        if (!self::keys($p, ['format', 'installation', 'database', 'web_uid', 'web_gid', 'worker_uid', 'worker_gid']) || $p['format'] !== 1
            || !self::hex($p['installation'], 32) || !self::hex($p['database'], 64)) throw new RuntimeException('queue_unsafe');
        foreach (['web_uid', 'web_gid', 'worker_uid', 'worker_gid'] as $key) if (!is_int($p[$key]) || $p[$key] < 1) throw new RuntimeException('queue_unsafe');
        if ($p['web_uid'] === $p['worker_uid'] || $p['web_gid'] === $p['worker_gid']) throw new RuntimeException('queue_unsafe');
        $this->identity($p[$role . '_uid'], $role === 'web' ? $p['worker_gid'] : null);
        $this->directory($root . '/inbox', $p['web_uid'], $p['worker_gid'], 02750);
        $this->directory($root . '/published', $p['worker_uid'], $p['web_gid'], 02750);
        if ($role === 'worker') {
            $this->directory($root . '/private', $p['worker_uid'], $p['worker_gid'], 0700);
            $this->directory($root . '/evidence', 0, $p['worker_gid'], 0750);
        }
    }

    public static function outside(string $path): void
    {
        $code = dirname(__DIR__, 2);
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $web = $documentRoot !== '' ? realpath($documentRoot) : false;
        if ($path === '' || $path === '/' || $path[0] !== '/' || realpath($path) !== $path || $path === $code || str_starts_with($path, $code . '/')
            || ($web && ($path === $web || str_starts_with($path, $web . '/')))) throw new RuntimeException('queue_unsafe');
    }

    protected function identity(int $uid, ?int $forbiddenGroup): void
    {
        if (posix_geteuid() !== $uid || ($forbiddenGroup !== null && (posix_getegid() === $forbiddenGroup || in_array($forbiddenGroup, posix_getgroups(), true)))) throw new RuntimeException('queue_unsafe');
    }

    protected function ownership(array $stat, int $uid, ?int $gid): void
    {
        if ($stat['uid'] !== $uid || ($gid !== null && $stat['gid'] !== $gid)) throw new RuntimeException('queue_unsafe');
    }

    public static function keys(array $value, array $keys): bool { return count($value) === count($keys) && !array_diff($keys, array_keys($value)); }

    public static function hex($value, int $length = 32): bool { return is_string($value) && preg_match('/^[a-f0-9]{' . $length . '}$/D', $value); }

    private function directory(string $path, int $uid, ?int $gid, int $mode): void
    {
        clearstatcache(true, $path);
        $stat = lstat($path);
        if (!$stat || ($stat['mode'] & 0170000) !== 0040000 || ($stat['mode'] & 07777) !== $mode || realpath($path) !== $path) throw new RuntimeException('queue_unsafe');
        $this->ownership($stat, $uid, $gid);
        for ($parent = dirname($path); $parent !== '/'; $parent = dirname($parent)) {
            $stat = lstat($parent);
            if (!$stat || ($stat['mode'] & 0170000) !== 0040000 || realpath($parent) !== $parent || (($stat['mode'] & 0022) && !($stat['mode'] & 01000))) throw new RuntimeException('queue_unsafe');
            // A sticky system temporary directory cannot replace another owner's child.
            $this->ownership($stat, 0, null);
        }
    }

    private function path(string $name): string
    {
        if (!preg_match('~^(?:control\.json|(?:inbox|published|private|evidence)/[a-z0-9][a-z0-9.-]*)$~D', $name)) throw new RuntimeException('queue_unsafe');
        return $this->root . '/' . $name;
    }

    public function exists(string $name): bool
    {
        $path = $this->path($name); clearstatcache(true, $path);
        return @lstat($path) !== false;
    }

    public function read(string $name, ?int $uid = null, ?int $gid = null, ?int $mode = null): array
    {
        return json_decode($this->bytes($name, $uid, $gid, $mode), true, 32, JSON_THROW_ON_ERROR);
    }

    public function bytes(string $name, ?int $uid = null, ?int $gid = null, ?int $mode = null): string
    {
        $path = $this->path($name);
        if ($uid === null) [$uid, $gid, $mode] = $this->permissions($name);
        clearstatcache(true, $path); $before = lstat($path);
        if (!$before || ($before['mode'] & 0170000) !== 0100000 || ($before['mode'] & 07777) !== $mode || $before['nlink'] !== 1 || $before['size'] > 262144) throw new RuntimeException('queue_unsafe');
        $this->ownership($before, $uid, $gid);
        $file = fopen($path, 'rb');
        try {
            $after = fstat($file);
            if ($before['ino'] !== $after['ino'] || $before['dev'] !== $after['dev']) throw new RuntimeException('queue_unsafe');
            $bytes = stream_get_contents($file, 262145);
            if (strlen($bytes) > 262144 || strlen($bytes) !== $after['size']) throw new RuntimeException('queue_unsafe');
            return $bytes;
        } finally { fclose($file); }
    }

    private function permissions(string $name): array
    {
        $p = $this->policy;
        if (str_starts_with($name, 'inbox/')) return [$p['web_uid'], $p['worker_gid'], 0640];
        if (str_starts_with($name, 'evidence/')) return [0, $p['worker_gid'], 0640];
        if (str_starts_with($name, 'private/')) return [$p['worker_uid'], $p['worker_gid'], 0600];
        return [$p['worker_uid'], $p['web_gid'], 0640];
    }

    public static function encode(array $value): string { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"; }

    public function write(string $name, array $value, bool $immutable = false): void
    {
        if (($this->role === 'web' && !str_starts_with($name, 'inbox/')) || ($this->role === 'worker' && !preg_match('~^(?:private|published)/~', $name))) throw new RuntimeException('queue_unsafe');
        $path = $this->path($name); [$uid, $gid, $mode] = $this->permissions($name);
        if ($this->exists($name)) {
            $this->bytes($name);
            if ($immutable) throw new RuntimeException('evidence_exists');
        }
        $temp = dirname($path) . '/.write-' . bin2hex(random_bytes(16));
        $mask = umask(0077);
        try { $file = fopen($temp, 'x+b'); } finally { umask($mask); }
        try {
            $bytes = self::encode($value);
            if (!chmod($temp, $mode) || fwrite($file, $bytes) !== strlen($bytes) || !fflush($file) || !fsync($file)) throw new RuntimeException('queue_io');
            $this->ownership(fstat($file), $uid, $gid);
            if ($immutable) {
                if (!link($temp, $path)) throw new RuntimeException('evidence_exists');
                if (!unlink($temp)) throw new RuntimeException('queue_io');
            } elseif (!rename($temp, $path)) throw new RuntimeException('queue_io');
            $directory = fopen(dirname($path), 'r');
            try { if (!fsync($directory)) throw new RuntimeException('queue_io'); } finally { fclose($directory); }
        } finally { fclose($file); if (is_file($temp)) unlink($temp); }
    }

    public function lock()
    {
        $name = $this->role === 'worker' ? 'private/worker.lock' : 'inbox/submit.lock';
        if (!$this->exists($name)) $this->write($name, [], true);
        $this->bytes($name);
        $file = fopen($this->path($name), 'rb');
        if (!$file || !flock($file, LOCK_EX | LOCK_NB)) { if ($file) fclose($file); throw new RuntimeException('queue_busy'); }
        return $file;
    }

    public function records(string $suffix): array
    {
        if ($this->role !== 'worker' || !in_array($suffix, ['request', 'accepted', 'result', 'rejected'], true)) throw new RuntimeException('queue_unsafe');
        $files = glob($this->root . '/private/*.' . $suffix . '.json');
        if (count($files) > 10000) throw new RuntimeException('queue_unsafe');
        return array_map('basename', $files);
    }
}
