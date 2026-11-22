<?php

// The gate connection keeps its row lock while DDL commits on the application connection.
class DEEC_UpdateDatabase
{
    const TABLE = 'dewawi_system_update';
    private Zend_Db_Adapter_Abstract $db;
    private static ?Zend_Db_Adapter_Abstract $lease = null;
    private static bool $exclusive = false;
    private static bool $maintenance = false;

    public function __construct(Zend_Db_Adapter_Abstract $db) { $this->db = $db; }

    public function exists(): bool
    {
        $table = $this->db->fetchRow('SELECT ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [self::TABLE]);
        if (!$table) return false;
        $columns = $this->db->fetchAll('SHOW COLUMNS FROM ' . self::TABLE);
        $keys = $this->db->fetchAll('SHOW INDEX FROM ' . self::TABLE);
        if ($table['ENGINE'] !== 'InnoDB' || $table['TABLE_TYPE'] !== 'BASE TABLE' || count($columns) !== 2
            || $columns[0]['Field'] !== 'id' || $columns[0]['Type'] !== 'varchar(191)' || $columns[0]['Null'] !== 'NO'
            || $columns[1]['Field'] !== 'payload' || $columns[1]['Type'] !== 'mediumtext' || $columns[1]['Null'] !== 'NO'
            || count($keys) !== 1 || $keys[0]['Key_name'] !== 'PRIMARY' || $keys[0]['Column_name'] !== 'id') throw new RuntimeException('configuration');
        return true;
    }

    public function initialize(array $control): void
    {
        $name = 'dewawi-update-setup:' . substr(hash('sha256', $this->db->fetchOne('SELECT DATABASE()')), 0, 32);
        if ((int)$this->db->fetchOne('SELECT GET_LOCK(?, 0)', [$name]) !== 1) throw new RuntimeException('busy');
        try {
            if ($this->exists()) { $this->read('control'); return; }
            $this->db->query('CREATE TABLE ' . self::TABLE . ' (id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, payload mediumtext NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $this->db->beginTransaction();
            try {
                $this->write('gate', []); $this->write('control', $control);
                $this->db->commit();
            } catch (Throwable $e) { $this->db->rollBack(); throw $e; }
        } finally { $this->db->fetchOne('SELECT RELEASE_LOCK(?)', [$name]); }
    }

    public function read(string $id): ?array
    {
        $value = $this->db->fetchOne('SELECT payload FROM ' . self::TABLE . ' WHERE id = ?', [$id]);
        if ($value === false) return null;
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new RuntimeException('configuration');
        return $decoded;
    }

    public function write(string $id, array $value): void
    {
        $this->db->insert(self::TABLE, ['id' => $id, 'payload' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    }

    public function jobs(): array
    {
        $rows = $this->db->fetchCol('SELECT payload FROM ' . self::TABLE . " WHERE id LIKE 'job:%' ORDER BY id");
        return array_map(static fn($value): array => json_decode($value, true, 512, JSON_THROW_ON_ERROR), $rows);
    }

    private static function connection(Zend_Db_Adapter_Abstract $db): Zend_Db_Adapter_Abstract
    {
        $config = $db->getConfig(); $config['persistent'] = false; $config['driver_options'][PDO::ATTR_PERSISTENT] = false;
        $gate = Zend_Db::factory('Pdo_Mysql', $config);
        $gate->query('SET SESSION innodb_lock_wait_timeout = 3');
        $gate->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $gate->beginTransaction();
        return $gate;
    }

    public function exclusive(callable $action)
    {
        self::release();
        $gate = self::connection($this->db);
        try {
            if ($gate->fetchOne('SELECT payload FROM ' . self::TABLE . " WHERE id = 'gate' FOR UPDATE") !== '[]') throw new RuntimeException('configuration');
            self::$exclusive = true;
            return $action();
        } finally { self::$exclusive = false; $gate->rollBack(); $gate->closeConnection(); }
    }

    public static function enter(array $settings, bool $updateRoute): void
    {
        if (strtolower($settings['adapter'] ?? '') !== 'pdo_mysql') throw new RuntimeException('configuration');
        $params = $settings['params']; $params['persistent'] = false; $params['driver_options'][PDO::ATTR_PERSISTENT] = false;
        $db = Zend_Db::factory('Pdo_Mysql', $params); $store = new self($db);
        if (!$store->exists()) return;
        $control = $store->read('control');
        if (!$control || array_keys($control) !== ['format', 'installation', 'database'] || $control['format'] !== 1
            || !is_string($control['installation']) || !preg_match('/^[a-f0-9]{32}$/D', $control['installation'])
            || !is_string($control['database']) || !preg_match('/^[a-f0-9]{64}$/D', $control['database'])) throw new RuntimeException('configuration');
        if (!$updateRoute) {
            $gate = self::connection($db);
            try {
                if ($gate->fetchOne('SELECT payload FROM ' . self::TABLE . " WHERE id = 'gate' LOCK IN SHARE MODE") !== '[]') throw new RuntimeException('configuration');
                self::$lease = $gate;
                register_shutdown_function([self::class, 'release']);
            } catch (Throwable $e) { $gate->rollBack(); throw $e; }
        }
        self::$maintenance = $store->read('maintenance') !== null;
        if (self::$maintenance && !$updateRoute) { self::release(); throw new RuntimeException('maintenance'); }
    }

    public function finish(): void { $this->db->delete(self::TABLE, ['id = ?' => 'maintenance']); }
    public static function exclusiveHeld(): bool { return self::$exclusive; }
    public static function maintenance(): bool { return self::$maintenance; }
    public static function release(): void
    {
        if (self::$lease) { self::$lease->rollBack(); self::$lease->closeConnection(); self::$lease = null; }
    }
}
