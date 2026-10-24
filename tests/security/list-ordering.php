<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/list-ordering.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = getenv('DEWAWI_TEST_ROOT') ?: dirname(__DIR__, 2);
$overlay = getenv('DEWAWI_TEST_OVERLAY') ?: $root;
set_include_path($overlay . '/library' . PATH_SEPARATOR . $root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root, $overlay): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
        require_once $path;
        return;
    }
    $parts = explode('_', $class);
    if (count($parts) >= 3) {
        $relative = 'application/modules/' . strtolower(array_shift($parts)) . '/'
            . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
        foreach (array_unique([$overlay, $root]) as $base) {
            if (is_file($base . '/' . $relative)) {
                require_once $base . '/' . $relative;
                return;
            }
        }
    }
});
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
class OrderingProbe extends DEEC_List_Query {
    public function apply($select, array $params, array $config): void {
        $this->applyOrdering($select, $this->normalizeParams($params), $config);
    }
}
$adapter = new Zend_Db_Adapter_Pdo_Mysql(['host'=>'unused', 'username'=>'unused', 'password'=>'unused', 'dbname'=>'unused']);
$attacks = [
    'if(1=1,sleep(5),0)',
    'extractvalue(1,concat(0x7e,(select password from user limit 1)))',
    's.modified DESC, sleep(5)', 'other.column', '0', 0, [],
    new Zend_Db_Expr('sleep(5)'),
];
Zend_Registry::set('User', ['id'=>42, 'admin'=>0, 'clientid'=>7]);
$checks = 0;
foreach (glob($root . '/application/modules/*/models/Entity/*.php') as $file) {
    $relative = substr($file, strlen($root) + 1);
    $source = is_file($overlay . '/' . $relative) ? $overlay . '/' . $relative : $file;
    $text = file_get_contents($source);
    if (!preg_match('/class (\w+)/', $text, $match) || !str_contains($text, 'function listConfig')) continue;
    require_once $source;
    $config = $match[1]::listConfig();
    $probe = new OrderingProbe();
    $makeSelect = static fn() => $adapter->select()->from([$config['alias'] => 'test_table'], ['id']);
    $baseline = $makeSelect();
    $probe->apply($baseline, ['sort'=>'DESC'], $config);
    foreach ($attacks as $order) {
        $select = $makeSelect();
        $probe->apply($select, ['order'=>$order, 'sort'=>['DESC']], $config);
        $sql = $select->assemble();
        check($sql === $baseline->assemble(), 'Invalid order did not use configured fallback: ' . $match[1]);
        check(!str_contains($sql, 'sleep') && !str_contains($sql, 'extractvalue') && !str_contains($sql, 'other'), 'Unsafe ordering: ' . $match[1]);
        $checks++;
    }
    check(DEEC_List_Ordering::column('modified', $config['orders']) === 'modified', 'Toolbar default missing: ' . $match[1]);
    foreach ($config['orders'] as $key => $column) {
        $order = is_int($key) ? $column : $key;
        check(DEEC_List_Ordering::column($order, $config['orders']) === $column, 'Allowed order lost: ' . $match[1]);
        $select = $makeSelect();
        $probe->apply($select, ['order'=>$order, 'sort'=>'asc'], $config);
        check(str_ends_with($select->assemble(), ' ASC'), 'Ascending sort lost');
        $checks++;
    }
}
// Client configuration must use the authenticated user's role and tenant.
foreach ([0, 1] as $admin) {
    Zend_Registry::set('User', ['id'=>42, 'admin'=>$admin, 'clientid'=>7]);
    $config = Admin_Model_Entity_Client::listConfig();
    check($config['clientFilter'] === ($admin === 0), 'Incorrect client filtering for user role');
    check($config['clientId'] === 7, 'Incorrect user client ID');
    $checks++;
}
Zend_Registry::set('User', ['id'=>42, 'admin'=>0, 'clientid'=>7]);
check(DEEC_List_Ordering::column('0', ['title','modified']) === 'modified', 'Numeric index accepted as order');
check(DEEC_List_Ordering::column('c.name1', ['name1'=>'c.name1']) === 'c.name1', 'Qualified mapping lost');
$expr = new Zend_Db_Expr('COALESCE(s.modified,s.created)');
check(DEEC_List_Ordering::column('recent', ['recent'=>$expr]) === $expr, 'Trusted expression lost');
try {
    DEEC_List_Ordering::column('sleep(5)', []);
    throw new RuntimeException('Empty whitelist accepted');
} catch (InvalidArgumentException $expected) {}

// Execute each legacy method using the real SQL builder and in-memory table doubles.
class LegacySelect extends Zend_Db_Select {
    public function setIntegrityCheck($value) { return $this; }
}
class LegacyRows extends ArrayObject { public $subtotal = 0; public $total = 0; }
class LegacyTable {
    public static string $sql = '';
    public function select() { return new LegacySelect(Zend_Db_Table::getDefaultAdapter()); }
    public function fetchAll($select) { self::$sql = $select->assemble(); return new LegacyRows(); }
}
class Items_Model_DbTable_Ledger extends LegacyTable {}
class Items_Model_DbTable_Itemlist extends LegacyTable {}
class Tasks_Model_DbTable_Task extends LegacyTable {}
class Shops_Model_DbTable_Account extends LegacyTable {}
class Shops_Model_DbTable_Order extends LegacyTable {}
class Ebay_Model_DbTable_Account extends LegacyTable {}
class Ebay_Model_DbTable_Listing extends LegacyTable {}
class Ebay_Model_DbTable_Order extends LegacyTable {}
class Ebay_Model_DbTable_Item extends LegacyTable {}
class LegacyHelper {
    public function __call($method, $args) { return ''; }
    public function getCurrency() { return $this; }
    public function toCurrency($value) { return $value; }
}
class Zend_Controller_Action_HelperBroker {
    public static function getStaticHelper($name) { return new LegacyHelper(); }
}
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('Client', ['id'=>1, 'parentid'=>0]);
$params = ['keyword'=>'', 'catid'=>'all', 'states'=>[], 'country'=>'', 'paymentstatus'=>'', 'daterange'=>'all', 'limit'=>25, 'tagid'=>0];
foreach (['Items'=>['ledger','itemlists'], 'Tasks'=>['tasks'], 'Shops'=>['accounts','orders'], 'Ebay'=>['accounts','listings','orders','items']] as $module => $methods) {
    $class = $module . '_Model_Get';
    $model = new $class();
    foreach ($methods as $method) {
        foreach ($attacks as $order) {
            foreach (['DESC, sleep(5)', [], 'asc'] as $sort) {
                $model->$method($params + ['order'=>$order, 'sort'=>$sort], ['catid'=>[], 'categories'=>[]], new LegacyHelper());
                check(!str_contains(LegacyTable::$sql, 'sleep') && !str_contains(LegacyTable::$sql, 'extractvalue') && !str_contains(LegacyTable::$sql, 'other'), 'Unsafe legacy ordering: ' . $class . '::' . $method);
                $checks++;
            }
        }
    }
}
// Preserve valid legacy sorting and reject a column absent from the item schema.
$model = new Ebay_Model_Get();
$model->listings($params + ['order'=>'margin', 'sort'=>'ASC'], []);
check(str_contains(LegacyTable::$sql, 'ORDER BY `i`.`margin` ASC'), 'Legacy margin sort lost');
$model->listings($params + ['order'=>'quantity', 'sort'=>'ASC'], []);
check(str_contains(LegacyTable::$sql, 'ORDER BY `e`.`modified` ASC'), 'Nonexistent item quantity column accepted');
echo 'PASS: ' . $checks . " entity and legacy ordering cases\n";
