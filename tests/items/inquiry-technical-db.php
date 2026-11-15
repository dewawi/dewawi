<?php

// Included by the disposable technical database runner; no production configuration is loaded.
(function() use($service, $db, $encode, $mapping, $work, $root) {
	$count = 0;
	$check = function(bool $ok, string $message) use(&$count): void { $count++; if(!$ok) throw new RuntimeException($message); };
	$reject = function(callable $call, string $message) use($check): void {
		try { $call(); } catch(RuntimeException|InvalidArgumentException $e) { $check(true, $message); return; }
		throw new RuntimeException('Expected rejection: '.$message);
	};
	technicalContext();
	foreach(['compressor','evaporator','condenser','base','machine','pump','tank','fan','compressor-b','recovery','switch','pump-secondary'] as $i => $name) {
		$id = 2600 + $i; $mapping['items']['fixture:'.$name] = $id;
		$db->insert('item', ['id' => $id, 'parentid' => $name === 'machine' ? 2603 : null, 'catid' => 0, 'shopid' => 0, 'clientid' => 100,
			'sku' => 'DEMO-D7A-'.$name, 'title' => 'Synthetic '.$name, 'manufacturersku' => 'SYNTHETIC-'.$name]);
	}
	$saved = $service->importJson($encode(thermohydraulicPolynomialBundle('cooling', 'fixture:d7a')), $mapping)['revisions'];
	$machine = $saved['technicaldataset:fixture:d7a:machine:1@1'];
	$permission = $db->fetchRow('SELECT * FROM permission WHERE userid = 10');
	$db->update('permission', ['contacts' => json_encode(['contact' => ['view', 'edit']])], ['id = ?' => $permission['id']]);
	$db->update('permission', ['contacts' => json_encode(['contact' => ['view']])], ['userid = ?' => 11]);
	$form = ['_settings' => ['selection' => ['method' => 'manual', 'automatic_quote' => false]], '1' => [
		['name' => 'einsatzbereich', 'type' => 'select', 'label' => 'Anwendungsbereich', 'options' => ['0' => 'Prozesskühlung', '2' => 'Heizung']],
		['name' => 'kuehlleistung', 'type' => 'number', 'label' => 'Erforderliche Kälteleistung', 'unit' => 'kW', 'depends_on' => 'einsatzbereich', 'depends_value' => '0'],
		['name' => 'heizleistung', 'type' => 'number', 'label' => 'Heizleistung', 'unit' => 'kW', 'depends_on' => 'einsatzbereich', 'depends_value' => '2'],
		['name' => 'vorlauf_temp', 'type' => 'number', 'label' => 'Gewünschte Vorlauftemperatur zum Verbraucher', 'unit' => '°C'],
		['name' => 'ruecklauf_temp', 'type' => 'number', 'label' => 'Rücklauftemperatur vom Verbraucher', 'unit' => '°C'],
		['name' => 'volumenstrom', 'type' => 'number', 'label' => 'Erforderlicher Volumenstrom, falls bekannt', 'unit' => 'm³/h'],
		['name' => 'message', 'type' => 'textarea', 'label' => 'Zusatzanforderungen']]];
	$db->insert('shop', ['id' => 918, 'clientid' => 100, 'title' => 'Synthetic D7A']);
	$db->insert('shopinquiryform', ['id' => 918, 'clientid' => 100, 'shopid' => 918, 'fields' => json_encode($form)]);
	foreach([918 => 100, 919 => 200] as $id => $owner) $db->insert('shopinquirydata', ['id' => $id, 'formid' => 918, 'shopid' => 918, 'clientid' => $owner, 'token' => 'SYNTHETIC-D7A-'.$id,
		'data' => json_encode(['einsatzbereich' => '0', 'kuehlleistung' => '31', 'vorlauf_temp' => '0', 'message' => '<script>window.injected=true</script>'])]);

	require_once __DIR__.'/fixtures/hydraulic-distribution.php';
	$cases = [];
	$stageBundle = machineStageBundle(2, 2, 'fixture:d7a-stage');
	$stageBundle->template_kind = 'synthetic_selection_test'; $stageBundle->production_import_allowed = false; $stageBundle->provenance_note = 'Invented stage fixture only';
	$cases[] = [$stageBundle, 'stage', ['stage' => 'full', 'circuits' => ['r1' => ['Te_degC' => '5', 'Tc_degC' => '50'], 'r2' => ['Te_degC' => '5', 'Tc_degC' => '50']]]];
	$cases[] = [thermalBundle('water', 'fixture:d7a-thermal'), 'thermal_circuit', ['request_json' => $encode(thermalRequest('water'))]];
	$parallel = thermalMachineBundle('both_shared', 'fixture:d7a-parallel');
	$cases[] = [$parallel, 'thermal_machine', ['request_json' => $encode(thermalMachineRequest($parallel))]];
	$hydraulic = fluidMachineBundle('fixture:d7a-hydraulic');
	$cases[] = [$hydraulic, 'hydraulics', ['request_json' => $encode(fluidHydraulicRequest($hydraulic))]];
	$cases[] = [hydraulicDistributionBundle('fixture:d7a-distribution'), 'distribution', ['request_json' => $encode(hydraulicDistributionRequest())]];
	foreach(['cooling', 'partial_heat_recovery', 'full_heat_recovery', 'heat_pump'] as $mode) $cases[] = [operatingModeBundle($mode, 'fixture:d7a-mode:'.$mode), 'mode:'.$mode.':full', ['Te_degC' => '5', 'Tc_degC' => '50']];
	$caseRoots = [];
	foreach($cases as [$bundle, $key, $inputs]) {
		$revisions = $service->importJson($encode($bundle), $mapping)['revisions'];
		foreach($bundle->datasets as $record) if($record->dataset_type === 'machine_configuration') $caseRoots[] = [$revisions['technicaldataset:'.$record->dataset_key.'@'.$record->revision], $key, $inputs];
	}
	$snapshot = function() use($db) { $out = []; foreach(['technicaldataset','itemtechnicalprofile','item','itemvariantopt','shopinquiryform','shopinquirydata','quote','quotepos','contact'] as $table) $out[$table] = $db->fetchAll('SELECT * FROM '.$table.' ORDER BY id'); return $out; };
	$before = $snapshot();
	$internal = new Contacts_Service_InquiryTechnical();

	foreach($caseRoots as [$caseRoot, $key, $inputs]) {
		$caseView = $internal->open(918, '', 2604, $caseRoot['id'], $caseRoot['revision'], $caseRoot['sha256']);
		$check(isset($caseView['choices'][$key]), 'Missing contract-specific UI choice: '.$key);
		$caseResult = $internal->calculate($caseView, ['calculation' => $key, 'reference' => '1'] + $inputs);
		$check(!$caseResult['eligible'] && ($key === 'stage' ? $caseResult['result']['stage_compressor_sums']['cooling_capacity']['value'] !== null : $caseResult['result']['solution'] !== null), 'Partial or mode UI request failed: '.$key);
	}
	$opened = $internal->open(918);
	$reqs = array_column($opened['requirements'], null, 'name');
	$check($reqs['vorlauf_temp']['value'] === '0' && $reqs['vorlauf_temp']['unit'] === '°C', 'Original zero temperature or actual unit lost');
	$check($reqs['volumenstrom']['value'] === '' && !isset($reqs['heizleistung']), 'Missing value or application dependency guessed');
	$searched = $internal->open(918, 'DEMO-D7A-machine');
	$check(count($searched['matches']) === 1 && !isset($searched['matches'][0]['price']), 'Manual search leaked commercial data or lost actual item');
	$noConfig = $internal->open(918, '', 2603);
	$check(!$noConfig['revisions'], 'Base article silently mapped to machine variant');
	$open = fn() => $internal->open(918, '', 2604, $machine['id'], $machine['revision'], $machine['sha256']);
	$v = $open();
	$check($v['item']['parentid'] == 2603 && count($v['machine']['record']->data->components) >= 5, 'Variant or component binding lost');
	$check($v['machine']['articles']['fixture:compressor-b']['sku'] === 'DEMO-D7A-compressor-b', 'Component article not resolved through its bound profile');
	$choice = 'mode:cooling:full';
	$check(($v['choices'][$choice]['contract'] ?? 0) === 3 && $v['choices'][$choice]['method'] === 'evaluateThermohydraulic', 'D6B2 contract not selected');
	$post = ['calculation' => $choice, 'reference' => '1', 'cold_inlet' => '20', 'hot_inlet' => '30'];
	$evaluated = $internal->calculate($v, $post); $r = $evaluated['result'];
	$check($r['mathematical_converged'] && $r['fully_coupled'] && $r['eligible'] === false && !$evaluated['eligible'] && $r['status'] === 'review_required', 'Synthetic convergence released a machine');
	$check(abs($r['solution']['point']->Te_degC - 5) < 1e-8 && abs($r['solution']['point']->Tc_degC - 50) < 1e-8, 'Wrong coupled UI result');
	$check($r['solution']['compressor_input_kW'] > 7 && count($r['dependencies']) > 5 && $r['reasons'], 'Result conditions, dependencies or OEM blockers missing');

	$compressorChoice = array_keys(array_filter($v['choices'], fn($c) => $c['input'] === 'compressor'))[0];
	$d3 = $internal->calculate($v, ['calculation' => $compressorChoice, 'reference' => '1', 'Te_degC' => '5', 'Tc_degC' => '50']);
	$check($d3['result']['calculation']['mathematical_status'] === 'calculated' && !$d3['eligible'], 'D3 UI point, explicit unit or machine release boundary lost');
	$blocked = $internal->calculate($v, array_diff_key($post, ['reference' => true]));
	$check($blocked['result']['solution'] === null, 'Default technical mode evaluated a demo');
	$reject(fn() => $internal->calculate($v, array_diff_key($post, ['cold_inlet' => true])), 'Missing inlet guessed');
	$reject(fn() => $internal->calculate($v, array_replace($post, ['calculation' => 'mode:free_cooling:full'])), 'Unsupported mode accepted');
	$reject(fn() => $internal->calculate($v, array_replace($post, ['reference' => ['1']])), 'Malformed reference accepted');
	$reject(fn() => $internal->open(919), 'Foreign inquiry');
	$reject(fn() => $internal->open(918, '', 9999), 'Foreign article');
	$reject(fn() => $internal->open(918, '', 2603, $machine['id'], 1, $machine['sha256']), 'Machine binding tampered');
	$reject(fn() => $internal->open(918, '', 2604, $machine['id'], 2, $machine['sha256']), 'Stale revision');
	$reject(fn() => $internal->open(918, '', 2604, $machine['id'], 1, str_repeat('0', 64)), 'Wrong hash');
	$rights = json_decode($permission['items'], true);
	foreach(['technicaldataset', 'item'] as $resource) {
		$p = $rights; $p[$resource] = array_values(array_diff($p[$resource], ['view']));
		$db->update('permission', ['items' => json_encode($p)], ['id = ?' => $permission['id']]);
		$reject($open, 'Missing '.$resource.' read right');
	}
	$db->update('permission', ['items' => $permission['items'], 'contacts' => '{}'], ['id = ?' => $permission['id']]);
	$reject($open, 'Missing inquiry read right');
	$db->update('permission', ['contacts' => json_encode(['contact' => ['view','edit']])], ['id = ?' => $permission['id']]);
	$p = $rights; $p['technicaldataset'] = ['view'];
	$db->update('permission', ['items' => json_encode($p)], ['id = ?' => $permission['id']]);
	$check($open()['machine'] !== null, 'Read-only technical user cannot inspect');
	$reject(fn() => $internal->calculate($v, $post), 'Missing reference import permission');
	$db->update('permission', ['items' => $permission['items']], ['id = ?' => $permission['id']]);
	technicalContext(11, 101, 100); $client = Zend_Registry::get('Client'); $client['modules']['shops'] = 100; Zend_Registry::set('Client', $client);
	$check($open()['machine'] !== null, 'Authorized parent sharing rejected');
	$client['modules']['shops'] = 200; Zend_Registry::set('Client', $client); $reject($open, 'Foreign shop owner spoof');
	technicalContext(20, 200); $reject($open, 'Foreign tenant'); technicalContext();
	Zend_Auth::getInstance()->clearIdentity(); $reject($open, 'Guest access'); technicalContext();
	Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 918, 'clientid' => 100], [], ['inquiry','catalog'])); $reject($open, 'Public site features do not grant private access'); technicalContext();
	$db->update('user', ['activated' => 0], ['id = ?' => 10]); $reject($open, 'Inactive account'); $db->update('user', ['activated' => 1], ['id = ?' => 10]);
	$dependency = $saved['itemtechnicalprofile:fixture:d7a:profile:compressor-b@1'];
	$db->update('itemtechnicalprofile', ['status' => 'withdrawn'], ['id = ?' => $dependency['id']]);
	$check($open()['machine']['blocked'], 'Withdrawn dependency not displayed');
	$reject(fn() => $internal->calculate($v, $post), 'Old open view bypassed current withdrawal');
	$db->update('itemtechnicalprofile', ['status' => 'draft'], ['id = ?' => $dependency['id']]);
	$raw = $db->fetchRow('SELECT * FROM technicaldataset WHERE id = ?', [$machine['id']]);
	$db->update('technicaldataset', ['payload_json' => $raw['payload_json'].' '], ['id = ?' => $machine['id']]); $reject($open, 'Payload corruption');
	$db->update('technicaldataset', ['payload_json' => $raw['payload_json']], ['id = ?' => $machine['id']]);
	$check($snapshot() === $before, 'Review changed inquiry, technical or sales state');
	file_put_contents($work.'/inquiry-technical.json', json_encode(['machine' => $machine, 'itemid' => 2604, 'inquiryid' => 918]));
	if(!Zend_Session::isStarted()) Zend_Session::start(['save_path' => $work]);
	require_once $root.'/application/modules/contacts/controllers/InquiryController.php';
	class InquiryTechnicalTestController extends Contacts_InquiryController { public function init() { $this->view = new Zend_View(); } }
	$dispatch = function(array $input, string $method = 'GET') use($root) {
		$request = new Zend_Controller_Request_HttpTestCase(); $request->clearPost()->clearQuery()->setMethod($method)->setParam('id', 918);
		if($method === 'POST') $request->setPost($input); else $request->setQuery($input);
		$response = new Zend_Controller_Response_HttpTestCase();
		$controller = new InquiryTechnicalTestController($request, $response); $controller->selectAction();
		return [$controller->view, $response];
	};
	$input = ['itemid' => 2604, 'datasetid' => $machine['id'], 'revision' => 1, 'hash' => $machine['sha256'], 'calculation' => $choice];
	[$view, $response] = $dispatch($input);
	$check($response->getHttpResponseCode() === 200 && strlen($view->technicalToken) === 64, 'Private GET controller failed');
	foreach([[], ['technical_csrf' => 'wrong']] as $bad) { [, $response] = $dispatch($input + $post + $bad, 'POST'); $check($response->getHttpResponseCode() === 403, 'CSRF bypass'); }
	[$view, $response] = $dispatch($input + $post + ['technical_csrf' => $view->technicalToken], 'POST');
	$check($response->getHttpResponseCode() === 200 && $view->technical['result']['fully_coupled'], 'Controller POST failed');
	[, $response] = $dispatch($input + ['technical_csrf' => $view->technicalToken], 'POST'); $check($response->getHttpResponseCode() === 422, 'Missing fields response: '.$response->getHttpResponseCode());
	foreach(['itemid','hash','q','calculation'] as $key) { [, $response] = $dispatch(array_replace($input, [$key => ['bad']])); $check($response->getHttpResponseCode() === 422, 'Array parameter '.$key); }
	$check($snapshot() === $before, 'Controller mutated business state');
	echo "PASS: $count D7A inquiry/article/variant/revision/calculation/rights/tenant/CSRF and immutable-workflow assertions\n";
})();
