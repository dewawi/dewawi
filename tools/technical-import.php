<?php

// Explicit private CLI: no application DB configuration, route, upload, or default writes.
if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if($path = stream_resolve_include_path(str_replace('_', '/', $class).'.php')) { require_once $path; return; }
	$parts = explode('_', $class); if(count($parts) < 3) return;
	$module = array_shift($parts); $section = strtolower(array_shift($parts)).'s';
	$path = $root.($module === 'Application' ? '/application/' : '/application/modules/'.strtolower($module).'/').$section.'/'.implode('/', $parts).'.php';
	if(is_file($path)) require_once $path;
});
$options = getopt('', ['file:', 'validate', 'socket:', 'database:', 'db-user:', 'user:', 'client:', 'mapping:', 'write', 'csv:', 'context:', 'references:', 'revision:']);
try {
	if(!isset($options['file'])) throw new InvalidArgumentException('Required: --file; use --validate for offline checking. Database dry-run additionally requires --socket --database --db-user --user --client.');
	function readTechnicalInput(string $path): string {
		if(!is_file($path) || filesize($path) > 4194304) throw new InvalidArgumentException('Invalid private input file');
		$content = file_get_contents($path);
		if($content === false) throw new RuntimeException('Cannot read private input');
		return $content;
	}
	$json = readTechnicalInput($options['file']);
	$adapter = new Items_Service_TechnicalImport();
	if(isset($options['csv'], $options['references'])) throw new InvalidArgumentException('Choose exactly one explicit input adapter');
	if(isset($options['csv'])) {
		if(!isset($options['context'])) throw new InvalidArgumentException('CSV requires --context');
		$json = $adapter->csv($options['csv'], $json, readTechnicalInput($options['context']));
	}
	if(isset($options['references'])) {
		$revision = $options['revision'] ?? '1';
		if(!is_string($revision) || !preg_match('/^[1-9][0-9]{0,9}$/D', $revision) || (float)$revision > 2147483647) throw new InvalidArgumentException('Invalid reference revision');
		$json = $adapter->referencePoints($json, $options['references'], (int)$revision);
	}
	if(isset($options['validate'])) {
		if(isset($options['write'])) throw new InvalidArgumentException('--validate cannot be combined with --write');
		$bundle = (new Items_Service_TechnicalDataset())->validateJson($json);
		echo json_encode(['status' => 'review_required', 'validation_only' => true, 'datasets' => count($bundle->datasets)], JSON_THROW_ON_ERROR)."\n";
		exit;
	}
	foreach(['socket', 'database', 'db-user', 'user', 'client'] as $field) if(empty($options[$field])) throw new InvalidArgumentException('Missing explicit private DB context');
	if(!str_starts_with($options['socket'], '/') || !preg_match('/^[A-Za-z0-9_]+$/D', $options['database']) || !ctype_digit($options['user']) || !ctype_digit($options['client'])) throw new InvalidArgumentException('Invalid private DB context');
	$db = Zend_Db::factory('Pdo_Mysql', ['host' => 'localhost', 'unix_socket' => $options['socket'], 'username' => $options['db-user'], 'password' => getenv('DEWAWI_TECHNICAL_DB_PASSWORD') ?: '', 'dbname' => $options['database'], 'charset' => 'utf8']);
	Zend_Db_Table::setDefaultAdapter($db);
	$account = $db->fetchRow('SELECT * FROM user WHERE id = ? AND activated = 1 AND deleted = 0', [(int)$options['user']]);
	$client = $db->fetchRow('SELECT * FROM client WHERE id = ? AND deleted = 0', [(int)$options['client']]);
	if(!$account || !$client) throw new RuntimeException('Private actor or owner is unavailable');
	if((int)$client['parentid'] > 0) $client['modules'] = ['items' => (int)$client['parentid']];
	Zend_Registry::set('User', $account); Zend_Registry::set('Client', $client);
	Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
	Zend_Auth::getInstance()->getStorage()->write((object)['id' => (int)$account['id']]);
	$mapping = [];
	if(isset($options['mapping'])) {
		$map = Items_Service_TechnicalJson::decode(readTechnicalInput($options['mapping']));
		if(!$map instanceof stdClass) throw new InvalidArgumentException('Invalid identity mapping');
		foreach($map as $category => $entries) {
			if(!$entries instanceof stdClass) throw new InvalidArgumentException('Invalid mapping category');
			foreach($entries as $symbol => $id) {
				if(!is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D',$id) || (float)$id>2147483647) throw new InvalidArgumentException('Invalid mapped ID');
				$mapping[$category][$symbol]=(int)$id;
			}
		}
	}
	$result = (new Items_Service_TechnicalDataset())->importJson($json, $mapping, !isset($options['write']));
	echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e) {
	fwrite(STDERR, $e->getMessage()."\n");
	exit(1);
}
