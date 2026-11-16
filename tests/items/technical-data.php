<?php

if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if(!isset($argv[1], $argv[2])) { echo "Run python3 tests/items/technical-data.py\n"; exit(1); }
$work = realpath($argv[2]);
if(!$work || !str_starts_with($work, '/tmp/dewawi-technical-test-') || $argv[1] !== $work.'/mysql.sock') throw new RuntimeException('Only disposable technical test sockets are accepted');
$root = dirname(__DIR__, 2);
error_reporting(E_ALL & ~E_DEPRECATED);
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function(string $class) use ($root): void {
	if($file = stream_resolve_include_path(str_replace('_', '/', $class).'.php')) { require_once $file; return; }
	$parts = explode('_', $class); if(count($parts) < 3) return;
	$module = array_shift($parts); $section = strtolower(array_shift($parts)).'s';
	$file = $root.($module === 'Application' ? '/application/' : '/application/modules/'.strtolower($module).'/').$section.'/'.implode('/', $parts).'.php';
	if(is_file($file)) require_once $file;
});
Zend_Session::start(['save_path' => $work]);
require $root.'/install/helper.php';
require __DIR__.'/fixtures/technical.php';
$db = Zend_Db::factory('Pdo_Mysql', ['host' => 'localhost', 'unix_socket' => $argv[1], 'username' => 'root', 'password' => '', 'dbname' => 'mysql', 'charset' => 'utf8']);
Zend_Db_Table::setDefaultAdapter($db);
$checks = 0; $coverage = [];
function checkTechnical(bool $condition, string $message): void { global $checks; $checks++; if(!$condition) throw new RuntimeException($message); }
function rejectsTechnical(callable $work, string $message): void {
	try { $work(); } catch(Throwable $e) { checkTechnical(true, $message); return; }
	throw new RuntimeException('Expected rejection: '.$message);
}
function coverTechnical(int $id): void { global $coverage; $coverage[$id] = true; }
function executeTechnicalSql(string $sql): void { global $db; $queries = []; PMA_splitSqlFile($queries, $sql); foreach($queries as $q) $db->query($q['query']); }
$db->query('CREATE DATABASE technical_fresh'); $db->query('USE technical_fresh');
executeTechnicalSql(file_get_contents($root.'/install/config/structure.sql'));
$schemas = [];
foreach(['technicaldataset', 'itemtechnicalprofile'] as $table) $schemas[$table] = $db->fetchRow('SHOW CREATE TABLE '.$table)['Create Table'];
$reviewSchema = $db->fetchRow('SHOW CREATE TABLE inquirytechnicalreview')['Create Table'];
coverTechnical(10);
$db->query('CREATE DATABASE technical_partial');$db->query('USE technical_partial');
$db->query('CREATE TABLE client LIKE technical_fresh.client');$db->query('CREATE TABLE item LIKE technical_fresh.item');
$partialQueries=[];PMA_splitSqlFile($partialQueries,file_get_contents($root.'/install/config/update-1.1.7-1.1.8.sql'));
$technicalCreates=array_values(array_filter($partialQueries,fn($q)=>preg_match('/CREATE TABLE IF NOT EXISTS `(technicaldataset|itemtechnicalprofile)`/',trim($q['query']))));
checkTechnical(count($technicalCreates)===2,'Expected exactly two targeted additive DDL statements');
$db->query($technicalCreates[0]['query']);foreach($technicalCreates as $q)$db->query($q['query']);
foreach($schemas as $table=>$definition)checkTechnical($db->fetchRow('SHOW CREATE TABLE '.$table)['Create Table']===$definition,'Partial technical migration differs from new installation');
$reviewCreates = array_values(array_filter($partialQueries, fn($q) => preg_match('/CREATE TABLE IF NOT EXISTS `inquirytechnicalreview`/', trim($q['query']))));
checkTechnical(count($reviewCreates) === 1, 'Expected one purpose-specific review table');
$db->query('CREATE TABLE shopinquirydata LIKE technical_fresh.shopinquirydata');
$db->query($reviewCreates[0]['query']); $db->query($reviewCreates[0]['query']);
checkTechnical($db->fetchRow('SHOW CREATE TABLE inquirytechnicalreview')['Create Table'] === $reviewSchema, 'Partial/repeated review DDL differs from fresh install');
$db->query('CREATE DATABASE technical_upgrade'); $db->query('USE technical_upgrade');
executeTechnicalSql(file_get_contents($work.'/structure-1.1.7.sql'));
$db->insert('client', ['id' => 100, 'company' => 'Preserved fixture']);
$db->insert('item', ['id' => 1, 'catid' => 0, 'shopid' => 0, 'clientid' => 100, 'sku' => 'PRESERVED', 'price' => '123.4567']);
executeTechnicalSql(file_get_contents($root.'/install/config/update-1.1.7-1.1.8.sql'));
foreach($schemas as $table => $schema) checkTechnical($db->fetchRow('SHOW CREATE TABLE '.$table)['Create Table'] === $schema, 'Fresh and actual 1.1.7 upgrade schema differ: '.$table);
checkTechnical($db->fetchRow('SHOW CREATE TABLE inquirytechnicalreview')['Create Table'] === $reviewSchema, 'Review upgrade differs from fresh install');
checkTechnical($db->fetchOne('SELECT price FROM item WHERE id = 1') === '123.4567', 'Upgrade changed existing item price'); coverTechnical(11);
$queries = []; PMA_splitSqlFile($queries, file_get_contents($root.'/install/config/update-1.1.7-1.1.8.sql'));
foreach($queries as $q) if(preg_match('/CREATE TABLE IF NOT EXISTS `(technicaldataset|itemtechnicalprofile)`/', trim($q['query']))) $db->query($q['query']);
checkTechnical((int)$db->fetchOne('SELECT COUNT(*) FROM technicaldataset') === 0, 'Repeated targeted DDL seeded data'); coverTechnical(12);
$db->query('USE technical_fresh');
foreach([[100,0],[101,100],[200,0]] as [$id,$parent]) $db->insert('client', ['id' => $id, 'parentid' => $parent, 'company' => 'Synthetic owner']);
foreach([[10,100],[11,101],[20,200]] as [$id,$owner]) {
	$db->insert('user', ['id' => $id, 'clientid' => $owner, 'password' => '', 'activated' => 1]);
	$db->insert('permission', ['userid' => $id, 'clientid' => $owner, 'items' => json_encode(['item' => ['view', 'edit'], 'technicaldataset' => ['view','import','approve','withdraw']])]);
}
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
function technicalContext(int $user = 10, int $selected = 100, ?int $itemsOwner = null): void {
	global $db;
	if(Zend_Registry::isRegistered('SiteContext')) Zend_Registry::getInstance()->offsetUnset('SiteContext');
	$u = $db->fetchRow('SELECT * FROM user WHERE id = ?', [$user]);
	Zend_Registry::set('User', $u);
	$c = $db->fetchRow('SELECT * FROM client WHERE id = ?', [$selected]);
	if($itemsOwner !== null) $c['modules'] = ['items' => $itemsOwner];
	Zend_Registry::set('Client', $c);
	Zend_Auth::getInstance()->getStorage()->write((object)['id' => $user]);
}
technicalContext();
$mapping = ['items' => [], 'options' => [], 'groups' => [], 'manufacturers' => []];
foreach(['compressor', 'evaporator', 'condenser', 'pump', 'tank', 'fan', 'base', 'machine'] as $i => $key) {
	$id = 1000 + $i; $mapping['items']['fixture:'.$key] = $id;
	$db->insert('item', ['id' => $id, 'parentid' => $key === 'machine' ? 1006 : null, 'catid' => 0, 'shopid' => 0, 'clientid' => 100, 'manufacturersku' => 'SYNTHETIC-'.$key, 'sku' => 'SYNTHETIC-'.$key, 'price' => '17.1250']);
}
$db->insert('quote', ['id'=>700, 'clientid'=>100, 'state'=>105, 'completed'=>1, 'templateid'=>0, 'language'=>'de', 'subtotal'=>'500.2500', 'taxes'=>'95.0475', 'total'=>'595.2975']);
$db->insert('quotepos', ['id'=>701, 'parentid'=>700, 'itemid'=>1007, 'possetid'=>0, 'clientid'=>100, 'quantity'=>'2.0000', 'price'=>'250.1250', 'total'=>'595.2975']);
$db->insert('shopinquiryform', ['id'=>865, 'shopid'=>120, 'clientid'=>100, 'quotetemplateid'=>0, 'fields'=>json_encode(['_settings'=>['selection'=>['method'=>'manual','automatic_quote'=>false]]])]);
$businessBefore=[];foreach(['item','quote','quotepos','shopinquiryform','shopinquirydata'] as $table)$businessBefore[$table]=$db->fetchAll('SELECT * FROM '.$table.' ORDER BY id');
$service = new Items_Service_TechnicalDataset();
$encode = static fn($v) => Items_Service_TechnicalJson::encode($v);
if(($argv[3] ?? '') === '--inquiry-technical-only') {
	require __DIR__.'/fixtures/thermohydraulic-polynomial.php';
	require __DIR__.'/inquiry-technical-db.php';
	require __DIR__.'/inquiry-technical-review-db.php';
	exit;
}
$bundle = technicalBundle();
$valid = $service->validateJson($encode($bundle));
checkTechnical(count($valid->datasets) === 9, 'Five-type fixture normalization failed');
$result = $service->importJson($encode($bundle), $mapping);
checkTechnical(count($result['revisions']) === 15, 'All five types and six profiles did not roundtrip'); coverTechnical(15);
$id = $result['revisions']['technicaldataset:fixture:family@1']['id'];
$polyId = $result['revisions']['technicaldataset:fixture:compressor@1']['id'];
$machineId = $result['revisions']['technicaldataset:fixture:machine:2@1']['id'];
checkTechnical($service->technicalStatus('technicaldataset', $machineId)['status'] === 'review_required', 'Draft machine was released'); coverTechnical(61);
$again = $service->importJson($encode($bundle), $mapping);
checkTechnical($again === $result, 'Identical import was not idempotent'); coverTechnical(16);
$changed = technicalBundle(); $changed->datasets[0]->data->title = 'Different immutable data';
rejectsTechnical(fn() => $service->importJson($encode($changed), $mapping), 'Conflicting revision'); coverTechnical(17);
$before = $db->fetchAll('SELECT * FROM technicaldataset ORDER BY id');
$broken = technicalBundle(); $broken->datasets[0]->dataset_key = 'fixture:new-family'; $broken->datasets[count($broken->datasets)-1]->data->records[0]->dataset_ref->dataset_key = 'missing';
rejectsTechnical(fn() => $service->importJson($encode($broken), $mapping), 'Missing late dependency');
checkTechnical($db->fetchAll('SELECT * FROM technicaldataset ORDER BY id') === $before, 'Invalid bundle partially wrote data');
$db->query("CREATE TRIGGER technical_failure BEFORE INSERT ON technicaldataset FOR EACH ROW BEGIN IF NEW.dataset_key = 'fixture:write-failure' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic insert failure'; END IF; END");
$first=technicalFamily();$first->dataset_key='fixture:before-failure';$second=technicalFamily();$second->dataset_key='fixture:write-failure';
rejectsTechnical(fn()=>$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$first,$second]])),'Failure after first actual insert');
checkTechnical($db->fetchAll('SELECT * FROM technicaldataset ORDER BY id') === $before,'Database insertion failure did not roll back first insert');
$db->query('DROP TRIGGER technical_failure');coverTechnical(19);
// Revision byte/hash identity keeps all decimal digits, including JSON numeric tokens.
$row = $service->getById('technicaldataset', $polyId);
checkTechnical(str_contains($row['payload_json'], '1.23456789012345678901234567890123456789e2'), 'Coefficient decimal precision lost');
checkTechnical(hash('sha256', $row['payload_json']) === $row['payload_sha256'], 'Canonical payload hash differs');
$unquoted = str_replace('"123.456789012345678901234567890123456789"', '123.456789012345678901234567890123456789', $encode(technicalBundle()));
checkTechnical($service->importJson($unquoted, $mapping) === $result, 'Numeric token differs from lossless decimal text');
foreach(['{"a":1,"a":2}', '{"a":1,"\\u0061":2}', '{"a":NaN}', '{"a":01}', '{"a":1,}', "{\"a\":\"\xff\"}", '{"a":"\\u0000"}', '{"a":"\\ud83d\\ude00"}'] as $bad) rejectsTechnical(fn() => Items_Service_TechnicalJson::decode($bad), 'Malformed/duplicate/unsupported JSON'); coverTechnical(21);
foreach(['NaN','Infinity','1e999','1e-999','1,5'] as $bad) rejectsTechnical(fn() => Items_Service_TechnicalJson::decimal($bad), 'Invalid exact decimal');
foreach([9,11] as $count) { $b = technicalBundle(); $b->datasets[1]->data->quantities->cooling_capacity->coefficients_C0_to_C9 = array_fill(0,$count,'1'); rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Coefficient count'); } coverTechnical(22);
foreach(['unknown_field', 'dataset_type', 'schema_version'] as $field) { $b = technicalBundle(); if($field === 'unknown_field') $b->datasets[0]->extra = true; elseif($field === 'dataset_type') $b->datasets[0]->dataset_type = 'fluid_properties'; else $b->datasets[0]->schema_version = 2; rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Closed package schema'); } coverTechnical(20);
$b = technicalBundle(); $b->datasets[1]->data->quantities->current->unit = 'kW'; rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Quantity-specific unit'); coverTechnical(23);
$b = technicalBundle(); $b->datasets[1]->data->equation_id = 'eval(php)'; rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Executable/unknown equation'); coverTechnical(26);
$adapter = new Items_Service_TechnicalImport();
$rp = (object)['format' => 'dewawi-reference-points', 'format_version' => 1, 'production_import_allowed' => false, 'records' => [technicalBundle()->datasets[8]->data->records[0]]];
$adapted = $adapter->referencePoints($encode($rp), 'fixture:adapter-points', 1);
checkTechnical($service->validateJson($adapted)->datasets[0]->dataset_type === 'reference_points', 'Explicit reference adapter failed');
rejectsTechnical(fn() => $service->validateJson($encode($rp)), 'Reference format must use explicit adapter');
echo "PASS: initial technical schema, import, decimal and closed-validation checks ($checks assertions)\n";

// Structural machine tests use the same schema for 2/1, 4/2 and 8/4.
foreach([1,2,4] as $circuits) {
	$b = technicalBundle($circuits);
	checkTechnical(count($service->validateJson($encode($b))->datasets[7]->data->circuits) === $circuits, 'Generic circuit-count validation failed');
	if($circuits !== 2) {
		$rev=$circuits+1;$b->datasets[2]->revision=$rev;$b->profiles[1]->revision=$rev;$b->profiles[1]->dataset_ref->revision=$rev;
		foreach($b->datasets[7]->data->components as $component)if($component->role==='evaporator')$component->profile_revision=$rev;
		$r=$service->importJson($encode($b),$mapping);
		$stored=$service->getById('technicaldataset',$r['revisions']['technicaldataset:fixture:machine:'.$circuits.'@1']['id']);
		$payload=Items_Service_TechnicalJson::decode($stored['payload_json']);
		$shared=array_filter($payload->references,fn($ref)=>$ref->key==='fixture:profile:compressor');
		checkTechnical(count($shared)===1,'Multiple machines did not reuse one compressor profile');
	}
}
coverTechnical(46); coverTechnical(47); coverTechnical(51);
$machineMutations = [
	48 => static function($m) { $m->data->circuits[1]->compressor_instances[] = $m->data->circuits[0]->compressor_instances[0]; },
	49 => static function($m) { $m->data->circuit_count = 3; },
	50 => static function($m) { $m->data->circuits[0]->refrigerant = 'wrong-fluid'; },
	52 => static function($m) { $m->data->circuits[1]->refrigerant_groups[0]->members[0] = 'evap.r1_out'; },
	53 => static function($m) { $m->data->circuits[0]->expansion_device->ports->in = 'liquid_in'; },
	55 => static function($m) { $m->data->stages[0]->active_compressors[] = 'missing.compressor'; },
	56 => static function($m) { $m->data->components[] = $m->data->components[0]; },
	57 => static function($m) { $m->data->cooling_kind = 'water'; },
];
foreach($machineMutations as $case => $mutate) { $b = technicalBundle(); $mutate($b->datasets[7]); rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Invalid topology case '.$case); coverTechnical($case); }
$b = technicalBundle(); $b->datasets[7]->data->circuits[0]->compressor_instances = []; rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Missing compressor assignments');
$b = technicalBundle(); $b->datasets[7]->data->mode_topologies[1]->release_status = 'approved'; rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Unsupported mode release');
$b = technicalBundle(); $b->datasets[0]->data->chassis[0]->structural_limits_dataset = (object)['dataset_key' => 'fixture:family', 'revision' => 1]; rejectsTechnical(fn() => $service->importJson($encode($b),$mapping), 'Cyclic package reference'); coverTechnical(54);

// Validation budgets are independent from machine output or compressor count.
rejectsTechnical(fn() => $service->validateJson(str_repeat(' ',4194305)), '4MiB import limit');
$b = (object)['format' => 'dewawi-technical-import', 'format_version' => 1, 'datasets' => array_fill(0,101,technicalFamily())]; rejectsTechnical(fn() => $service->validateJson($encode($b)), '100 package limit');
rejectsTechnical(fn() => Items_Service_TechnicalJson::decode(str_repeat('[',34).'0'.str_repeat(']',34)), 'JSON depth limit');
$b = technicalBundle(); $b->datasets[1]->source->conflicts = array_fill(0,5001,(object)['field' => 'frequency', 'cell' => 'D17', 'reported' => null, 'source' => '50']); rejectsTechnical(fn() => $service->validateJson($encode($b)), '5000 entry limit');
$b = technicalBundle(); $b->datasets[1]->data->operating_envelope->vertices = array_map(static fn($i) => (object)['Te' => (string)(-$i-1), 'Tc' => '50'],range(0,4999)); rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Aggregate package entry limit');
coverTechnical(25);

// Source-backed drafts are represented by synthetic condition/metadata fixtures only.
foreach([['standard','10','0'],['selected','8','2']] as [$set,$sh,$sc]) {
	$b = technicalPolynomial(); $b->dataset_key = 'fixture:conditions:'.$set; $b->data->conditions->superheat_K = $sh; $b->data->conditions->subcooling_K = $sc;
	$b->data->operating_envelope = null; $b->data->electrical = (object)['voltage_V' => null, 'phases' => null, 'execution_code' => null];
	$b->source->conflicts = [(object)['field' => 'superheat', 'cell' => 'E17', 'reported' => null, 'source' => $sh]];
	$r = $service->importJson($encode((object)['format' => 'dewawi-technical-import','format_version'=>1,'datasets'=>[$b]]));
	$stored = $service->getById('technicaldataset', $r['revisions']['technicaldataset:'.$b->dataset_key.'@1']['id']);
	checkTechnical($stored['status'] === 'draft' && str_contains($stored['source_json'], 'conflicts'), 'Source conflict or draft state lost');
}
coverTechnical(28); coverTechnical(29);

// Owner-scoped database relationships reject cross-owner references even when IDs exist.
$db->insert('item',['id'=>9999,'catid'=>0,'shopid'=>0,'clientid'=>200,'manufacturersku'=>'SYNTHETIC-compressor']);
$wrongMap=$mapping; $wrongMap['items']['fixture:compressor']=9999;
rejectsTechnical(fn() => $service->importJson($encode(technicalBundle()),$wrongMap),'Foreign mapped item'); coverTechnical(14); coverTechnical(31);
rejectsTechnical(fn() => $service->importJson($encode(technicalBundle()),['items'=>['fixture:compressor'=>'SYNTHETIC-compressor']]),'SKU is not an ID mapping'); coverTechnical(30);
$db->insert('itemoptset',['id'=>50,'parentid'=>1006,'clientid'=>100]);
$db->insert('itemopt',['id'=>51,'parentid'=>1006,'optsetid'=>50,'clientid'=>100,'itemid'=>1004]);
$mapping['options']['fixture:included']=51; $mapping['groups']['fixture:group']=50;
$b = technicalBundle(); $b->profiles[0]->binding->base_item_ref='fixture:base'; rejectsTechnical(fn() => $service->importJson($encode($b),$mapping),'Base profile cannot inherit a variant'); coverTechnical(32);
$db->insert('itemvariantopt',['itemid'=>1007,'itemoptid'=>51,'clientid'=>100]);
$b = technicalBundle(); $b->datasets[7]->data->variant_signature->included_option_refs=['fixture:included']; $b->datasets[7]->data->variant_signature->extra_option_refs=['fixture:included']; rejectsTechnical(fn() => $service->validateJson($encode($b)), 'Duplicate included/extra option'); coverTechnical(58);
$db->delete('itemvariantopt',['itemid = ?'=>1007]);

// All inherited mutations and direct DbTable/Row writes remain blocked.
$model = new Items_Model_DbTable_Technicaldataset();
$protectedBefore = $db->fetchAll('SELECT * FROM technicaldataset ORDER BY id');
foreach([
	fn() => $model->insert(['clientid'=>100]), fn() => $model->create(['clientid'=>100]), fn() => $model->createForParent(1,'items','item'),
	fn() => $model->update(['status'=>'approved'],'1=1'), fn() => $model->updateById($id,['status'=>'approved']), fn() => $model->setState($id,1),
	fn() => $model->delete('1=1'), fn() => $model->deleteById($id), fn() => $model->deleteByIds([$id]), fn() => $model->copyById($id),
	fn() => $model->lock($id), fn() => $model->unlock($id), fn() => $model->setDefault($id),
	fn() => $model->createRow(['clientid'=>100])->save(),
	function() use ($model,$id) { $r=$model->find($id)->current(); $r->status='approved'; $r->save(); },
	fn() => $model->find($id)->current()->delete(),
] as $mutation) rejectsTechnical($mutation,'Generic technical write');
checkTechnical($db->fetchAll('SELECT * FROM technicaldataset ORDER BY id')===$protectedBefore,'Generic mutation changed a revision'); coverTechnical(40);

// Permission checks re-read the real account and persisted rights, independently of inputs.
$fullRights = json_encode(['item'=>['view','edit'],'technicaldataset'=>['view','import','approve','withdraw']]);
$db->update('permission',['items'=>json_encode(['item'=>['view','edit'],'technicaldataset'=>['view','import']])],['userid = ?'=>10]);
$evidence = $encode((object)['scope_key'=>'fixture:scope','rights_reference'=>'Synthetic test rights','engineering_reference'=>'Synthetic test review','source_sha256s'=>[technicalSource()->sha256]]);
$family = $service->getById('technicaldataset',$id);
rejectsTechnical(fn()=>$service->approve('technicaldataset',$id,$family['payload_sha256'],$evidence),'Importer cannot approve'); coverTechnical(39);
$db->update('permission',['items'=>$fullRights],['userid = ?'=>10]);
$service->approve('technicaldataset',$id,$family['payload_sha256'],$evidence);
$approved = $service->getById('technicaldataset',$id);
checkTechnical($approved['status']==='approved' && $approved['payload_json']===$family['payload_json'] && (int)$approved['approvedby']===10,'Approval changed payload or lost actor'); coverTechnical(41);
rejectsTechnical(fn()=>$service->approve('technicaldataset',$id,$family['payload_sha256'],$evidence),'Second approval');
$poly = $service->getById('technicaldataset',$polyId);
$service->approve('technicaldataset',$polyId,$poly['payload_sha256'],$evidence);
$profileId = $result['revisions']['itemtechnicalprofile:fixture:profile:compressor@1']['id'];
$profile = $service->getById('itemtechnicalprofile',$profileId);
$profileEvidence=$encode((object)['scope_key'=>'fixture:compressor','rights_reference'=>'Synthetic test rights','engineering_reference'=>'Synthetic test review','source_sha256s'=>[technicalSource()->sha256]]);
$service->approve('itemtechnicalprofile',$profileId,$profile['binding_sha256'],$profileEvidence);
checkTechnical($service->technicalStatus('itemtechnicalprofile',$profileId)['status']==='approved','Complete synthetic profile could not be approved');
$duplicate = technicalBundle()->profiles[0]; $duplicate->profile_key='fixture:overlapping';
$r = $service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[],'profiles'=>[$duplicate]]),$mapping);
$dupId=$r['revisions']['itemtechnicalprofile:fixture:overlapping@1']['id']; $dup=$service->getById('itemtechnicalprofile',$dupId);
rejectsTechnical(fn()=>$service->approve('itemtechnicalprofile',$dupId,$dup['binding_sha256'],$profileEvidence),'Overlapping profile approval'); coverTechnical(33);
$service->withdraw('technicaldataset',$polyId,$poly['payload_sha256'],'Synthetic retirement');
checkTechnical($service->technicalStatus('itemtechnicalprofile',$profileId)['status']==='review_required','Withdrawn dependency did not block new use');
checkTechnical($service->getById('technicaldataset',$polyId)['payload_json']===$poly['payload_json'],'Withdrawal changed historical bytes'); coverTechnical(45);
rejectsTechnical(fn()=>$service->approve('technicaldataset',$polyId,$poly['payload_sha256'],$evidence),'Reapproval after withdrawal');
rejectsTechnical(fn()=>$service->withdraw('technicaldataset',$polyId,$poly['payload_sha256'],'again'),'Repeated withdrawal'); coverTechnical(42);
rejectsTechnical(fn()=>$service->withdraw('technicaldataset',$id,str_repeat('0',64),'stale hash'),'Expected hash conflict'); coverTechnical(43);
$missing = technicalPolynomial(); $missing->dataset_key='fixture:no-rights'; $missing->data_owner=null;
$r=$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$missing]]));
$missingId=$r['revisions']['technicaldataset:fixture:no-rights@1']['id']; $row=$service->getById('technicaldataset',$missingId);
rejectsTechnical(fn()=>$service->approve('technicaldataset',$missingId,$row['payload_sha256'],$evidence),'No rights/data owner'); coverTechnical(27);
$tankId=$result['revisions']['technicaldataset:fixture:tank@1']['id']; $tankRow=$service->getById('technicaldataset',$tankId); $service->approve('technicaldataset',$tankId,$tankRow['payload_sha256'],$evidence);
$referenceId=$result['revisions']['technicaldataset:fixture:references@1']['id']; $reference=$service->getById('technicaldataset',$referenceId);
rejectsTechnical(fn()=>$service->approve('technicaldataset',$referenceId,$reference['payload_sha256'],$evidence),'Mathematical reference is not independent evidence');
$machine=$service->getById('technicaldataset',$machineId);
rejectsTechnical(fn()=>$service->approve('technicaldataset',$machineId,$machine['payload_sha256'],$evidence),'Structural machine is not physics approval');

// Authorized parent sharing and site ownership use separate, exact scopes.
technicalContext(11,101,100);
checkTechnical($service->getById('technicaldataset',$id)!==null,'Authorized child lost shared parent data'); coverTechnical(34);
technicalContext(20,200);
checkTechnical($service->getById('technicaldataset',$id)===null,'Cross-tenant read');
rejectsTechnical(fn()=>$service->withdraw('technicaldataset',$id,$family['payload_sha256'],'foreign'),'Cross-tenant write');
checkTechnical(count((new Items_Model_DbTable_Technicaldataset())->fetchAll())===0,'Raw DbTable fetch leaked tenant data');
rejectsTechnical(fn()=>(new Items_Model_DbTable_Technicaldataset())->setClientId(100)->getById($id),'Manual owner override'); coverTechnical(35);
technicalContext(11,101);
checkTechnical($service->getById('technicaldataset',$id)===null,'Parent relation alone granted sharing'); coverTechnical(38);
Zend_Registry::set('SiteContext',new DEEC_Site_Context(['id'=>77,'clientid'=>100],[],['catalog']));
checkTechnical($service->getById('technicaldataset',$id,true)!==null,'Authorized parent-site owner read'); coverTechnical(36);
Zend_Registry::set('SiteContext',new DEEC_Site_Context(['id'=>78,'clientid'=>101],[],['catalog']));
$client=Zend_Registry::get('Client');$client['modules']=['items'=>100];Zend_Registry::set('Client',$client);
checkTechnical($service->getById('technicaldataset',$id,true)===null,'Child site inherited private parent data');
rejectsTechnical(fn()=>$service->importJson($encode(technicalBundle()),$mapping),'No private site writes'); coverTechnical(37);
technicalContext();
// Foreign references also fail database-level constraints.
$rawProfile=$db->fetchRow('SELECT * FROM itemtechnicalprofile WHERE id = ?',[$profileId]);unset($rawProfile['id']);$rawProfile['profile_key']='fixture:foreign-fk';$rawProfile['clientid']=200;
rejectsTechnical(fn()=>$db->insert('itemtechnicalprofile',$rawProfile),'Composite dataset FK'); coverTechnical(13);

// New revisions do not retarget stored machine dependencies or mutate issued business data.
$oldMachine=$service->getById('technicaldataset',$machineId);
$newPoly=technicalPolynomial();$newPoly->revision=2;$newPoly->supersedes=(object)['dataset_key'=>'fixture:compressor','revision'=>1];$newPoly->data->quantities->power_input->coefficients_C0_to_C9[0]='999';
$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$newPoly]]));
checkTechnical($service->getById('technicaldataset',$machineId)['payload_json']===$oldMachine['payload_json'],'New compressor revision retargeted old machine'); coverTechnical(44);
$db->insert('itemvariantopt',['itemid'=>1007,'itemoptid'=>51,'clientid'=>100]);
rejectsTechnical(fn()=>$service->importJson($encode(technicalBundle()),$mapping),'Changed variant signature requires new binding');
$db->delete('itemvariantopt',['itemid = ?'=>1007]); coverTechnical(60);

// Hold the owner advisory lock until both independent workers are actually waiting.
function raceTechnical(string $mode): array {
	global $db,$work,$argv;
	$lock='technical:'.sha1('technical_fresh:100');
	checkTechnical((int)$db->fetchOne('SELECT GET_LOCK(?,1)',[$lock])===1,'Race barrier lock');
	$workers=[];
	try {
		for($i=0;$i<2;$i++) {
			$process=proc_open([PHP_BINARY,__DIR__.'/fixtures/technical-race.php',$argv[1],$work,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
			if(!is_resource($process)) throw new RuntimeException('Unable to start technical race worker');
			fclose($pipes[0]);$workers[]=[$process,$pipes];
		}
		$waiting=0;
		for($i=0;$i<200;$i++) {
			$waiting=(int)$db->fetchOne("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE STATE = 'User lock' AND DB = 'technical_fresh'");
			if($waiting>=2)break;
			usleep(10000);
		}
		checkTechnical($waiting>=2,'Both concurrent workers reached the owner lock');
	} finally {$db->fetchOne('SELECT RELEASE_LOCK(?)',[$lock]);}
	$results=[];
	foreach($workers as [$process,$pipes]) { $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$results[]=['exit'=>proc_close($process),'out'=>$out,'err'=>$err]; }
	return $results;
}
$race=technicalFamily();$race->dataset_key='fixture:race';
file_put_contents($work.'/race.json',$encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$race]]));
$workers=raceTechnical('import');
checkTechnical($workers[0]['exit']===0 && $workers[1]['exit']===0 && $workers[0]['out']===$workers[1]['out'],'Concurrent import lost idempotence: '.json_encode($workers));
checkTechnical((int)$db->fetchOne("SELECT COUNT(*) FROM technicaldataset WHERE dataset_key='fixture:race'")===1,'Concurrent import duplicated row');coverTechnical(18);
$raceRow=$db->fetchRow("SELECT * FROM technicaldataset WHERE dataset_key='fixture:race'");
file_put_contents($work.'/race-approval.json',json_encode(['id'=>(int)$raceRow['id'],'hash'=>$raceRow['payload_sha256'],'evidence'=>$evidence],JSON_THROW_ON_ERROR));
$workers=raceTechnical('approve');$exits=array_column($workers,'exit');sort($exits);
checkTechnical($exits===[0,1],'Concurrent approval did not enforce a single transition');

// CSV paths use the exact public column contract; complex topology requires an explicit context.
$columns=json_decode(file_get_contents($root.'/application/modules/items/configs/technical-import-columns.json'),true)['templates'];
function technicalCsv(string $name,array $rows):string {
	global $columns;
	$f=fopen('php://temp','w+');fputcsv($f,$columns[$name]['columns'],';','"','');
	foreach($rows as $row)fputcsv($f,array_map(fn($key)=>$row[$key]??'',$columns[$name]['columns']),';','"','');rewind($f);$result=stream_get_contents($f);fclose($f);return $result;
}
$csvContext=(object)['format'=>'dewawi-technical-csv-context','format_version'=>1,'bundle'=>(object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[]],'sources'=>(object)['fixture:source'=>technicalSource()]];
foreach(array_keys($columns) as $name) rejectsTechnical(fn()=>$adapter->csv($name,technicalCsv($name,[]),$encode($csvContext)),'Empty CSV template '.$name);
rejectsTechnical(fn()=>$adapter->csv('03-maschinenfamilien-gehaeuse',"wrong;header\n1;2\n",$encode($csvContext)),'Wrong CSV columns');coverTechnical(24);
$csvSource=['source_ref'=>'fixture:source','source_sha256'=>technicalSource()->sha256,'source_locator'=>'fixture:v1','revision'=>'1'];
$familyCsv=$csvSource+['family_key'=>'fixture:csv-family','family_dataset_key'=>'fixture:csv-family','family_revision'=>'1','chassis_key'=>'fixture:csv-chassis','title'=>'CSV fixture','length_mm'=>'10','width_mm'=>'20','height_mm'=>'30','installation_kind'=>'outdoor','manufacturer_ref'=>'fixture:manufacturer'];
$normalized=$adapter->csv('03-maschinenfamilien-gehaeuse',technicalCsv('03-maschinenfamilien-gehaeuse',[$familyCsv]),$encode($csvContext));
checkTechnical($service->validateJson($normalized)->datasets[0]->data->chassis[0]->dimensions->width_mm==='2e1','CSV family normalization');
rejectsTechnical(fn()=>$adapter->csv('03-maschinenfamilien-gehaeuse',technicalCsv('03-maschinenfamilien-gehaeuse',[$familyCsv,$familyCsv]),$encode($csvContext)),'Duplicate CSV chassis');
$polyCsv=[];
foreach(['cooling_capacity'=>'W','power_input'=>'W'] as $quantity=>$unit) {
	$r=$csvSource+['dataset_key'=>'fixture:csv-polynomial','schema_version'=>'1','manufacturer_name'=>'Synthetic','manufacturer_revision'=>'fixture-v1','model'=>'SYNTHETIC-CSV','refrigerant'=>'fixture-fluid','condition_set'=>'standard','frequency_Hz'=>'50','speed_rpm'=>'2900','superheat_K'=>'10','subcooling_K'=>'0','quantity'=>$quantity,'unit'=>$unit];
	for($i=0;$i<10;$i++)$r['C'.$i]=$i?'0':'123.456789012345678901234567890';$polyCsv[]=$r;
}
$normalized=$adapter->csv('02-polynomdatensaetze',technicalCsv('02-polynomdatensaetze',$polyCsv),$encode($csvContext));
checkTechnical(count((array)$service->validateJson($normalized)->datasets[0]->data->quantities)===2,'CSV quantity aggregation');
$polyCsv[1]['superheat_K']='8';rejectsTechnical(fn()=>$adapter->csv('02-polynomdatensaetze',technicalCsv('02-polynomdatensaetze',$polyCsv),$encode($csvContext)),'Mixed CSV conditions');

// Missing equipment effects block release, while exact article and option identities stay separate.
$b=technicalBundle();$b->datasets[7]->data->variant_signature->extra_option_refs=['fixture:included'];
$b->datasets[7]->data->selected_equipment=[(object)['option_ref'=>'fixture:included','component_key'=>'fixture:tank','technical_effect'=>'unknown','target_instance_key'=>'tank','replacement_component_key'=>null,'effect_dataset_ref'=>null,'source_locator'=>null,'included'=>false]];
$b->datasets[7]->dataset_key='fixture:unknown-option';
$r=$service->importJson($encode($b),$mapping);$optionId=$r['revisions']['technicaldataset:fixture:unknown-option@1']['id'];$row=$service->getById('technicaldataset',$optionId);
checkTechnical(in_array('option_effect_unverified',$service->technicalStatus('technicaldataset',$optionId)['reasons'],true),'Unknown option effect did not block readiness');
rejectsTechnical(fn()=>$service->approve('technicaldataset',$optionId,$row['payload_sha256'],$evidence),'Unverified option release');coverTechnical(59);

// Generic inquiry methods, prices and the manual pilot are exercised by the existing suites too.
checkTechnical($db->fetchOne('SELECT price FROM item WHERE id=1000')==='17.1250','Technical imports changed existing prices');
foreach($businessBefore as $table=>$expectedRows) {
	$rows=$db->fetchAll('SELECT * FROM '.$table.' ORDER BY id');
	if($table==='item') $rows=array_values(array_filter($rows,fn($row)=>(int)$row['id']!==9999));
	checkTechnical($rows===$expectedRows,'Technical import changed existing business state: '.$table);
}
coverTechnical(62);coverTechnical(63);
require __DIR__.'/technical-csv.php';
$expected=range(10,63);$missing=array_diff($expected,array_keys($coverage));
checkTechnical(!$missing,'Missing D2B matrix coverage: '.implode(',',$missing));
echo "PASS: 54 D2B matrix cases covered; $checks assertions, isolated fresh install and actual 1.1.7 upgrade, owner/FK/history/lifecycle/concurrency/CSV checks\n";
require __DIR__.'/compressor-polynomial-db.php';
require __DIR__.'/machine-stage-db.php';
require __DIR__.'/thermal-circuit-db.php';
require __DIR__.'/thermal-machine-db.php';
require __DIR__.'/fluid-hydraulics-db.php';
require __DIR__.'/hydraulic-distribution-db.php';
require __DIR__.'/operating-mode-db.php';
require __DIR__.'/thermohydraulic-circuit-db.php';
require __DIR__.'/thermohydraulic-polynomial-db.php';
require __DIR__.'/inquiry-technical-db.php';
require __DIR__.'/inquiry-technical-review-db.php';
