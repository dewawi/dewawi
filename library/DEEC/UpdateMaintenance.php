<?php

require_once dirname(__DIR__, 2) . '/tools/updates/Files.php';

// Cooperative installation-wide fence, not an OS security boundary.
class DEEC_UpdateMaintenance
{
    public string $root;
    private static $lease = null;
    private static bool $exclusive = false;
    private static ?self $active = null;
    private static bool $blocked = false;

    private static function present(string $path): bool { clearstatcache(true, $path); return @lstat($path) !== false; }

    private static function roots(array $options, string $root): array
    {
        $roots = [$root];
        $configured = $options['documentRoots'] ?? [];
        if (!is_array($configured)) throw new RuntimeException('storage_unavailable');
        foreach (array_merge($configured, [$_SERVER['DOCUMENT_ROOT'] ?? '']) as $path) {
            if ($path === '') continue;
            if (!is_string($path) || !($resolved = realpath($path)) || !is_dir($resolved)) throw new RuntimeException('storage_unavailable');
            $roots[] = $resolved;
        }
        return array_values(array_unique($roots));
    }

    private static function outsideRoots(string $path, array $roots): void
    {
        foreach ($roots as $root) if ($path === $root || str_starts_with($path, rtrim($root, '/') . '/')) throw new RuntimeException('storage_unavailable');
    }

    public static function localFilesystem(string $path): bool
    {
        $mounts = @file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES);
        if ($mounts === false) return false;
        $best = ''; $type = '';
        foreach ($mounts as $line) {
            $parts = explode(' - ', $line, 2); $fields = explode(' ', $parts[0]);
            if (count($parts) !== 2 || count($fields) < 5) return false;
            $mount = preg_replace_callback('/\\\\([0-7]{3})/', static fn($m): string => chr(octdec($m[1])), $fields[4]);
            if (($path === $mount || str_starts_with($path, rtrim($mount, '/') . '/')) && strlen($mount) >= strlen($best)) {
                $best = $mount; $type = explode(' ', $parts[1])[0];
            }
        }
        return in_array($type, ['ext2', 'ext3', 'ext4', 'xfs', 'btrfs', 'zfs'], true);
    }

    private static function automaticBase(string $base, array $roots): void
    {
        self::outsideRoots($base, $roots);
        new self($base);
        foreach (array_unique(['/tmp', '/var/tmp', '/run', '/dev/shm', sys_get_temp_dir()]) as $temp) {
            if ($base === $temp || str_starts_with($base, rtrim($temp, '/') . '/')) throw new RuntimeException('storage_unavailable');
        }
        if (!is_writable($base) || !self::localFilesystem($base)) throw new RuntimeException('storage_unavailable');
    }

    // Setup may inspect one provider; ordinary entries read only the pinned locator.
    public static function storage(array $options, string $root, bool $discover = false): ?array
    {
        if (self::mode($options) !== 'shared') return null;
        $locator = $root . '/configs/update-storage.json';
        $ready = $root . '/configs/update-storage.ready.json';
        if (!array_key_exists('statePath', $options) && !self::present($locator) && !self::present($ready)
            && (!$discover || !getenv('DEWAWI_GOBD_REPORT_DIR'))) return null;
        $roots = self::roots($options, $root);
        $record = null;
        if (self::present($locator)) {
            $record = json_decode(self::privateBytes($locator), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($record) || !DewawiUpdateFiles::keys($record, ['format', 'root', 'base', 'path', 'public_roots', 'control']) || $record['format'] !== 1
                || $record['root'] !== hash('sha256', $root) || !is_string($record['base']) || !is_string($record['path']) || !is_array($record['public_roots']) || !self::validControl($record['control'])
                || $record['path'] !== $record['base'] . '/dewawi-updates-' . substr(hash('sha256', $root), 0, 32)) throw new RuntimeException('storage_conflict');
            foreach ($record['public_roots'] as $public) if (!is_string($public) || realpath($public) !== $public || !is_dir($public)) throw new RuntimeException('storage_conflict');
            $roots = array_values(array_unique(array_merge($roots, $record['public_roots'])));
            self::automaticBase($record['base'], $roots);
            self::outsideRoots($record['path'], $roots);
            if (self::present($record['path'])) new self($record['path']);
            if (fileowner($locator) !== fileowner($record['base'])) throw new RuntimeException('storage_conflict');
        } elseif (self::present($ready)) throw new RuntimeException('storage_conflict');
        if (array_key_exists('statePath', $options)) {
            $path = $options['statePath'];
            if (!is_string($path) || $path === '' || ($record && $record['path'] !== $path)) throw new RuntimeException('storage_conflict');
            self::outsideRoots($path, $roots);
            if (self::present($path)) new self($path);
            else {
                DewawiUpdateFiles::outside(dirname($path));
                if (!is_writable(dirname($path))) throw new RuntimeException('storage_unavailable');
            }
            return ['path' => $path, 'source' => 'configured', 'root' => $root, 'record' => $record, 'ready' => $record ? self::ready($root, $record) : null];
        }
        if ($record) return ['path' => $record['path'], 'source' => 'automatic', 'root' => $root, 'record' => $record, 'ready' => self::ready($root, $record)];
        if (!$discover) return null;
        $base = getenv('DEWAWI_GOBD_REPORT_DIR');
        if (!$base || (empty($_SERVER['DOCUMENT_ROOT']) && empty($options['documentRoots']))) return null;
        self::automaticBase($base, $roots);
        if (realpath($root . '/configs') !== $root . '/configs' || !is_writable($root . '/configs') || !self::localFilesystem($root . '/configs')) throw new RuntimeException('storage_unavailable');
        $path = $base . '/dewawi-updates-' . substr(hash('sha256', $root), 0, 32);
        self::outsideRoots($path, $roots);
        if (self::present($path)) {
            new self($path);
            if (iterator_count(new FilesystemIterator($path)) !== 0) throw new RuntimeException('storage_conflict');
        }
        return ['path' => $path, 'source' => 'automatic', 'root' => $root, 'record' => null, 'ready' => false, 'base' => $base, 'public_roots' => $roots];
    }

    private static function ready(string $root, array $record): bool
    {
        $path = $root . '/configs/update-storage.ready.json';
        if (!self::present($path)) return false;
        if (fileowner($path) !== fileowner($record['base'])) throw new RuntimeException('storage_conflict');
        if (json_decode(self::privateBytes($path), true, 32, JSON_THROW_ON_ERROR) !== ['format' => 1, 'locator_sha256' => hash_file('sha256', $root . '/configs/update-storage.json')]) throw new RuntimeException('storage_conflict');
        $files = new self($record['path']);
        $files->bytes('gate.lock');
        if ($files->read('control.json') !== $record['control']) throw new RuntimeException('storage_conflict');
        return true;
    }

    public static function validControl($control): bool
    {
        return is_array($control) && DewawiUpdateFiles::keys($control, ['format', 'installation', 'database', 'topology']) && $control['format'] === 1
            && DewawiUpdateFiles::hex($control['installation']) && DewawiUpdateFiles::hex($control['database'], 64) && $control['topology'] === 'single-local-host';
    }

    public static function claim(array $selection, array $control): array
    {
        if ($selection['record']) return $selection['record'];
        $record = ['format' => 1, 'root' => hash('sha256', $selection['root']), 'base' => $selection['base'], 'path' => $selection['path'],
            'public_roots' => $selection['public_roots'], 'control' => $control];
        $path = $selection['root'] . '/configs/update-storage.json';
        try { self::writeAt($path, $record); }
        catch (Throwable $e) {
            if ($e->getMessage() !== 'record_exists') throw $e;
            $record = self::storage(['mode' => 'shared'], $selection['root'])['record'];
            if (($record['path'] ?? null) !== $selection['path'] || ($record['root'] ?? null) !== hash('sha256', $selection['root'])) throw new RuntimeException('storage_conflict');
        }
        return $record;
    }

    public static function completeSelection(array $selection): void
    {
        if (json_decode(self::privateBytes($selection['root'] . '/configs/update-storage.json'), true, 32, JSON_THROW_ON_ERROR) !== $selection['record']) throw new RuntimeException('storage_conflict');
        $path = $selection['root'] . '/configs/update-storage.ready.json';
        if (self::present($path)) { self::ready($selection['root'], $selection['record']); return; }
        self::writeAt($path, ['format' => 1, 'locator_sha256' => hash_file('sha256', $selection['root'] . '/configs/update-storage.json')]);
    }

    public function capabilities(): void
    {
        self::probeDirectory($this->root, fileowner($this->root));
    }

    public static function probeDirectory(string $directory, ?int $owner = null): void
    {
        $name = $directory . '/.probe-' . bin2hex(random_bytes(16)); $renamed = $name . '.renamed';
        $a = $b = null;
        try {
            self::writeAt($name, []); self::privateBytes($name, $owner);
            $a = fopen($name, 'rb'); $b = fopen($name, 'rb');
            if (!flock($a, LOCK_EX | LOCK_NB) || flock($b, LOCK_EX | LOCK_NB) || !rename($name, $renamed)) throw new RuntimeException('storage_unavailable');
            self::privateBytes($renamed, $owner); self::syncDirectory($directory);
        } finally {
            if ($a) fclose($a); if ($b) fclose($b);
            foreach ([$name, $renamed] as $file) if (self::present($file)) unlink($file);
            self::syncDirectory($directory);
        }
    }

    public static function mode(array $options): string
    {
        $legacy = array_key_exists('statePath', $options)
            || self::present(dirname(__DIR__, 2) . '/configs/update-storage.json') || self::present(dirname(__DIR__, 2) . '/configs/update-storage.ready.json');
        $mode = $options['mode'] ?? (!empty($options['queuePath']) ? 'worker' : ($legacy ? 'shared' : 'database'));
        if (!in_array($mode, ['shared', 'worker', 'database'], true)) throw new RuntimeException('configuration');
        return $mode;
    }

    public function __construct(string $root)
    {
        DewawiUpdateFiles::outside($root);
        if (!is_dir($root) || (fileperms($root) & 07777) !== 0700 || !is_readable($root) || !is_writable($root) || !function_exists('fsync')) throw new RuntimeException('configuration');
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
        return self::privateBytes($this->path($name), fileowner($this->root));
    }

    private static function privateBytes(string $path, ?int $owner = null): string
    {
        clearstatcache(true, $path); $s = @lstat($path);
        if (!$s || realpath($path) !== $path || ($s['mode'] & 0170000) !== 0100000 || ($s['mode'] & 07777) !== 0600 || $s['nlink'] !== 1 || ($owner !== null && $s['uid'] !== $owner) || $s['size'] > 262144) throw new RuntimeException('configuration');
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
        self::writeAt($this->path($name), $value);
    }

    private static function writeAt(string $path, array $value): void
    {
        $temp = dirname($path) . '/.write-' . bin2hex(random_bytes(16));
        $old = umask(0077);
        try { $f = fopen($temp, 'x+b'); } finally { umask($old); }
        try {
            $bytes = DewawiUpdateFiles::encode($value);
            if (!chmod($temp, 0600) || fwrite($f, $bytes) !== strlen($bytes) || !fflush($f) || !fsync($f)) throw new RuntimeException('state_io');
            if (!@link($temp, $path)) throw new RuntimeException(self::present($path) ? 'record_exists' : 'state_io');
            if (!unlink($temp)) throw new RuntimeException('state_io');
            self::syncDirectory(dirname($path));
        } finally { fclose($f); if (self::present($temp)) unlink($temp); }
    }

    private function sync(): void
    {
        self::syncDirectory($this->root);
    }

    private static function syncDirectory(string $path): void
    {
        $f = fopen($path, 'r');
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

    public static function enter(array $options, bool $updateRoute = false, ?string $root = null, array $database = []): void
    {
        try {
            if (self::mode($options) !== 'database' && $database) {
                require_once __DIR__ . '/UpdateDatabase.php';
                $params = $database['params']; $params['persistent'] = false; $params['driver_options'][PDO::ATTR_PERSISTENT] = false;
                if ((new DEEC_UpdateDatabase(Zend_Db::factory($database['adapter'], $params)))->exists()) throw new RuntimeException('configuration');
            }
            if (self::mode($options) === 'database') {
                $base = getenv('DEWAWI_GOBD_REPORT_DIR');
                if ($base && self::present($base . '/dewawi-updates-' . substr(hash('sha256', $root ?? dirname(__DIR__, 2)), 0, 32))) throw new RuntimeException('storage_conflict');
                require_once __DIR__ . '/UpdateDatabase.php';
                DEEC_UpdateDatabase::enter($database, $updateRoute);
                return;
            }
            $selection = self::storage($options, $root ?? dirname(__DIR__, 2));
            if (!$selection) return;
            if ($selection['source'] === 'automatic' && !$selection['ready'] && !$updateRoute) throw new RuntimeException('storage_incomplete');
            $path = $selection['path'];
            if ($updateRoute && !is_dir($path)) { DewawiUpdateFiles::outside(dirname($path)); self::$blocked = true; return; }
            $gate = new self($path); self::$active = $gate;
            if (!$gate->exists('gate.lock') && $updateRoute && !$gate->maintenance()) return;
            self::$lease = $gate->lock();
            if ($gate->maintenance() && !$updateRoute) { self::release(); throw new RuntimeException('maintenance'); }
        } catch (Throwable $e) {
            if (!$updateRoute) throw $e;
            self::$blocked = true;
        }
    }

    public static function dispatchAllowed(Zend_Controller_Request_Abstract $request): bool
    {
        return (!self::$blocked && (!self::$active || !self::$active->maintenance()) && (!class_exists('DEEC_UpdateDatabase', false) || !DEEC_UpdateDatabase::maintenance())) || ($request->getModuleName() === 'admin' && $request->getControllerName() === 'updates'
            && in_array($request->getActionName(), ['index', 'initialize', 'maintenance', 'prepare', 'approve', 'execute', 'finish', 'check', 'run', 'cancel'], true));
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
            require_once $root . '/library/Zend/Loader/Autoloader.php';
            Zend_Loader_Autoloader::getInstance();
            require_once $root . '/library/Zend/Config.php';
            require_once $root . '/library/Zend/Config/Ini.php';
            $all = new Zend_Config_Ini($root . '/configs/database.ini');
            $environment = getenv('APPLICATION_ENV') ?: 'production';
            $config = $all->get($environment) ?? ($environment === 'development' ? $all->get('production') : null);
            if (!$config) throw new RuntimeException('configuration');
            self::enter($config->get('systemUpdates') ? $config->systemUpdates->toArray() : [], false, $root, $config->resources->db->toArray());
        } catch (Throwable $e) { fwrite(STDERR, "Maintenance unavailable\n"); exit(1); }
        finally { restore_error_handler(); set_include_path($includePath); }
    }
}
