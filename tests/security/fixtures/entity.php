<?php

// Shared in-memory adapter for entity and tenant authorization regressions.
function contactpersonWhereMatches(string $where, array $row): bool
{
    $where = trim(preg_replace('/^AND /', '', str_replace('`', '', $where)));
    if ($where !== '' && $where[0] === '(' && substr($where, -1) === ')') $where = substr($where, 1, -1);

    if (preg_match('/^([a-z0-9_]+) IN \(([^)]*)\)$/i', $where, $match)) {
        $values = array_map(static fn($value) => trim($value, " '\""), explode(',', $match[2]));
        return array_key_exists($match[1], $row) && in_array((string)$row[$match[1]], $values, true);
    }

    if (preg_match('/^([a-z0-9_]+) > ([0-9]+)$/i', $where, $match)) return isset($row[$match[1]]) && $row[$match[1]] > (int)$match[2];

    if (!preg_match('/^([a-z0-9_]+) = (.*)$/i', $where, $match)) {
        throw new RuntimeException('Unsupported fixture predicate: ' . $where);
    }

    return array_key_exists($match[1], $row) && (string)$row[$match[1]] === trim($match[2], " '\"");
}

class ContactpersonTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $rows = [];
    public array $selects = [];
    private int $lastId = 100;

    protected function _connect() {}
    protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
    public function beginTransaction() { return true; }
    public function commit() { return true; }
    public function rollBack() { return true; }

    public function describeTable($tableName, $schemaName = null)
    {
        $columns = ['id', 'clientid', 'parentid', 'module', 'controller', 'ordering', 'deleted', 'created', 'createdby', 'modified', 'modifiedby'];
        foreach ($this->rows[$tableName] ?? [] as $row) $columns = array_merge($columns, array_keys($row));
        $metadata = [];
        foreach (array_unique($columns) as $column) {
            $metadata[$column] = ['COLUMN_NAME' => $column, 'DATA_TYPE' => 'varchar', 'PRIMARY' => $column === 'id', 'PRIMARY_POSITION' => 1, 'IDENTITY' => $column === 'id'];
        }
        return $metadata;
    }

    public function query($sql, $bind = [])
    {
        if (!$sql instanceof Zend_Db_Table_Select || $bind) throw new RuntimeException('Unexpected database access');
        $sql->assemble();
        $this->selects[] = $sql;
        $from = $sql->getPart(Zend_Db_Select::FROM);
        $table = reset($from)['tableName'];
        $rows = array_values(array_filter($this->rows[$table] ?? [], static function(array $row) use ($sql): bool {
            foreach ($sql->getPart(Zend_Db_Select::WHERE) as $where) {
                if (!contactpersonWhereMatches($where, $row)) return false;
            }
            return true;
        }));

        foreach (array_reverse($sql->getPart(Zend_Db_Select::ORDER)) as $order) {
            $field = trim((string)$order[0], '`');
            $direction = strtoupper((string)$order[1]) === 'DESC' ? -1 : 1;
            usort($rows, static fn($left, $right) => $direction * (($left[$field] ?? null) <=> ($right[$field] ?? null)));
        }

        if ($limit = $sql->getPart(Zend_Db_Select::LIMIT_COUNT)) $rows = array_slice($rows, 0, $limit);

        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetchAll($mode) { return $this->rows; }
        };
    }

    public function insert($table, array $bind)
    {
        $bind['id'] = ++$this->lastId;
        $bind += ['deleted' => 0];
        $this->rows[$table][] = $bind;
        return 1;
    }

    public function update($table, array $bind, $where = '')
    {
        $where = is_array($where)
            ? $where
            : preg_split('/\)?\s+AND\s+\(?/i', trim((string)$where, '() '));
        $updated = 0;
        if (!isset($this->rows[$table])) return 0;
        foreach ($this->rows[$table] as &$row) {
            foreach ($where as $condition) {
                if (!contactpersonWhereMatches($condition, $row)) continue 2;
            }
            $row = array_replace($row, $bind);
            $updated++;
        }
        unset($row);
        return $updated;
    }

    public function lastInsertId($tableName = null, $primaryKey = null) { return $this->lastId; }
}

