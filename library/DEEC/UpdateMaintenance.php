<?php

require_once dirname(__DIR__, 2) . '/tools/updates/Files.php';

// Cooperative installation-wide fence, not an OS security boundary.
class DEEC_UpdateMaintenance
{
    public string $root;
    private static $lease = null;
    private static bool $exclusive = false;
    private static ?self $active = null;

    public static function mode(array $options): string
    {
        $mode = $options['mode'] ?? (empty($options['queuePath']) ? 'shared' : 'worker');
        if (!in_array($mode, ['shared', 'worker'], true)) throw new RuntimeException('configuration');
        return $mode;
    }

    public function __construct(string $root)
    {
        DewawiUpdateFiles::outside($root);
        if ((fileperms($root) & 07777) !== 0700 || !function_exists('fsync')) throw new RuntimeException('configuration');
        for ($p = $root; $p !== '/'; $p = dirname($p)) {
            if (is_link($p) || realpath($p) !== $p || ((fileperms($p) & 0022) && !(fileperms($p) & 01000))) throw new RuntimeException('configuration');
        }
        $this->root = $root;
    }

    public function exists(string $name): bool { clearstatcache(); return @lstat($this->path($name)) !== false; }

    private function path(string $name): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*$/D', $name)) throw new RuntimeException('configuration');
        return $this->root . '/' . $name;
    }

    public function bytes(string $name): string
    {
        $path = $this->path($name); clearstatcache(true, $path); $s = lstat($path);
        if (!$s || ($s['mode'] & 0170000) !== 0100000 || ($s['mode'] & 07777) !== 0600 || $s['nlink'] !== 1 || $s['uid'] !== fileowner($this->root) || $s['size'] > 262144) throw new RuntimeException('configuration');
        $f = fopen($path, 'rb');
        try {
            $after = fstat($f);
            if ($after['ino'] !== $s['ino'] || $after['dev'] !== $s['dev']) throw new RuntimeException('configuration');
            $bytes = stream_get_contents($f, 262145);
            if (strlen($bytes) !== $s['size']) throw new RuntimeException('configuration');
            return $bytes;
        } finally { fclose($f); }
    }

    public function read(string $name): array { return json_decode($this->bytes($name), true, 32, JSON_THROW_ON_ERROR); }

    public function write(string $name, array $value): void
    {
        $path = $this->path($name);
        $temp = $this->root . '/.write-' . bin2hex(random_bytes(16));
        $old = umask(0077);
        try { $f = fopen($temp, 'x+b'); } finally { umask($old); }
        try {
            $bytes = DewawiUpdateFiles::encode($value);
            if (!chmod($temp, 0600) || fwrite($f, $bytes) !== strlen($bytes) || !fflush($f) || !fsync($f) || !link($temp, $path)) throw new RuntimeException('state_io');
            $this->sync();
        } finally { fclose($f); unlink($temp); }
    }

    private function sync(): void
    {
        $f = fopen($this->root, 'r');
        try { if (!fsync($f)) throw new RuntimeException('state_io'); } finally { fclose($f); }
    }

    public function maintenance(): ?array { return $this->exists('maintenance.json') ? $this->read('maintenance.json') : null; }

    public function lock(bool $exclusive = false)
    {
        $this->bytes('gate.lock');
        $f = fopen($this->path('gate.lock'), 'rb');
        if (!flock($f, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) { fclose($f); throw new RuntimeException('busy'); }
        return $f;
    }

    public function exclusive(callable $action)
    {
        self::release();
        $f = $this->lock(true); self::$exclusive = true;
        try { return $action(); }
        finally { self::$exclusive = false; flock($f, LOCK_UN); fclose($f); }
    }

    public static function exclusiveHeld(): bool { return self::$exclusive; }

    public static function release(): void
    {
        if (self::$lease) { flock(self::$lease, LOCK_UN); fclose(self::$lease); self::$lease = null; }
    }

    public function end(): void
    {
        $this->bytes('maintenance.json');
        if (!unlink($this->path('maintenance.json'))) throw new RuntimeException('state_io');
        $this->sync();
    }

    public static function enter(array $options, bool $updateRoute = false): void
    {
        if (self::mode($options) !== 'shared' || empty($options['statePath'])) return;
        if ($updateRoute && !is_dir($options['statePath'])) { DewawiUpdateFiles::outside(dirname($options['statePath'])); return; }
        $gate = new self($options['statePath']);
        self::$active = $gate;
        if (!$gate->exists('gate.lock') && $updateRoute && !$gate->maintenance()) return;
        self::$lease = $gate->lock();
        if ($gate->maintenance() && !$updateRoute) { self::release(); throw new RuntimeException('maintenance'); }
    }

    public static function dispatchAllowed(Zend_Controller_Request_Abstract $request): bool
    {
        return !self::$active || !self::$active->maintenance() || ($request->getModuleName() === 'admin' && $request->getControllerName() === 'updates'
            && in_array($request->getActionName(), ['index', 'initialize', 'maintenance', 'prepare', 'approve', 'execute', 'finish'], true));
    }

    public static function protectInstallation(string $root): void
    {
        if (self::$lease) return;
        if (!is_file($root . '/configs/database.ini')) return;
        $includePath = get_include_path();
        set_include_path($root . '/library' . PATH_SEPARATOR . $includePath);
        set_error_handler(static function(int $severity): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new RuntimeException('configuration');
        });
        try {
            require_once $root . '/library/Zend/Config.php';
            require_once $root . '/library/Zend/Config/Ini.php';
            $all = new Zend_Config_Ini($root . '/configs/database.ini');
            $environment = getenv('APPLICATION_ENV') ?: 'production';
            $config = $all->get($environment) ?? ($environment === 'development' ? $all->get('production') : null);
            if (!$config) throw new RuntimeException('configuration');
            self::enter($config->get('systemUpdates') ? $config->systemUpdates->toArray() : []);
        } catch (Throwable $e) { fwrite(STDERR, "Maintenance unavailable\n"); exit(1); }
        finally { restore_error_handler(); set_include_path($includePath); }
    }
}
