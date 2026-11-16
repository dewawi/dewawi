<?php

// Included only by the disposable technical-data runner.
(function() use($db, $service, $encode, $mapping, $work) {
	$count = 0;
	$check = function(bool $ok, string $message) use(&$count): void { $count++; if(!$ok) throw new RuntimeException($message); };
	$reject = function(callable $call, string $message) use($check): void {
		try { $call(); } catch(RuntimeException|InvalidArgumentException $e) { $check(!str_contains($e->getMessage(), 'fixture:d7a'), $message.' exposed private data'); return; }
		throw new RuntimeException('Expected rejection: '.$message);
	};
	technicalContext(); putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS=100,101');
	$compare = new Contacts_Service_InquiryTechnicalComparison();
	$reviews = new Contacts_Service_InquiryTechnicalReview();
	$oldId = json_decode(file_get_contents($work.'/inquiry-review.json'), true)['id'];
	$old = $reviews->open(918, $oldId);
	$roots = [];
	foreach(['compressor','evaporator','condenser','base','machine','pump','tank','fan','compressor-b','recovery','switch','pump-secondary'] as $i => $name) $mapping['items']['fixture:'.$name] = 2600+$i;
	foreach([2804 => 'R410A', 2805 => 'R454B'] as $id => $refrigerant) {
		$db->insert('item', ['id' => $id, 'parentid' => 2603, 'catid' => 0, 'shopid' => 0, 'clientid' => 100, 'sku' => 'DEMO-D7B2A-'.$refrigerant, 'title' => 'Synthetic variant '.$refrigerant, 'manufacturersku' => 'SYNTHETIC-machine']);
		$bundle = machineStageBundle(2, 2, 'fixture:d7b2a:'.$refrigerant);
		$bundle->template_kind = 'synthetic_selection_test'; $bundle->production_import_allowed = false; $bundle->provenance_note = 'Invented comparison fixture, no OEM performance';
		$replace = function(&$value) use(&$replace, $refrigerant): void { if(is_object($value) || is_array($value)) { foreach($value as &$v) $replace($v); } elseif($value === 'fixture-fluid') $value = $refrigerant; };
		$replace($bundle); $bound = $mapping; $bound['items']['fixture:machine'] = $id;
		$imported = $service->importJson($encode($bundle), $bound)['revisions'];
		$r = $imported['technicaldataset:fixture:d7b2a:'.$refrigerant.':machine:2@1'];
		$roots[] = ['itemid' => $id, 'datasetid' => $r['id'], 'revision' => $r['revision'], 'hash' => $r['sha256']];
	}
	$db->insert('item', ['id' => 2806, 'catid' => 0, 'shopid' => 0, 'clientid' => 100, 'sku' => 'ZCE114', 'title' => 'Synthetic unbound article']);
	$db->insert('item', ['id' => 2807, 'catid' => 0, 'shopid' => 0, 'clientid' => 200, 'sku' => 'FOREIGN-COMPARE', 'title' => 'Foreign private article']);
	for($i = 0; $i < 24; $i++) $db->insert('item', ['id' => 2820+$i, 'catid' => 0, 'shopid' => 0, 'clientid' => 100, 'sku' => 'SEARCH-LIMIT-'.$i, 'title' => 'Synthetic search fixture']);
	$snapshot = function() use($db): array { $out = []; foreach(['item','itemvariantopt','quote','quotepos','contact','shopinquirydata','shopinquiryform','technicaldataset','itemtechnicalprofile','inquirytechnicalreview'] as $table) $out[$table] = $db->fetchAll('SELECT * FROM '.$table.' ORDER BY id'); return $out; };
	$before = $snapshot();
	$one = $compare->compare(918, [$roots[0]]); $c = $one['candidates'][0];
	$check($one['comparison_version'] === 1 && count($one['candidates']) === 1 && !$one['eligible'], 'Single bounded candidate contract missing');
	$check($c['article']['id'] == 2804 && $c['article']['parentid'] == 2603 && $c['configuration']['identity']['id'] === $roots[0]['datasetid'], 'Variant or pinned revision changed');
	$check(count($c['components']) > 5 && count($c['packages']) > 5 && $c['configuration']['compressors'] === 4 && $c['configuration']['circuits'] === 2, 'Exact component graph or counts lost');
	$check(count($c['coverage']) === 11 && $c['coverage']['compressor_electrical']['state'] === 'synthetic_only' && $c['coverage']['refrigerant_curve']['state'] === 'synthetic_only', 'Bound compressor/electrical/curve coverage lost');
	foreach(['evaporator_maps','condenser_maps','controls_stages','oem_references','parallel_oil'] as $area) $check($c['coverage'][$area]['state'] === 'missing', 'Unbound evidence invented: '.$area);
	$check($c['calculations']['compressor']['available'] && $c['calculations']['stage']['available'] && !$c['calculations']['thermal']['available'] && !$c['calculations']['coupled']['available'], 'Unsupported computation inferred');
	$check($c['assessments']['kuehlleistung']['state'] === 'calculation_unavailable' && $c['status'] === 'review_required' && !$c['eligible'], 'Synthetic sums treated as machine capacity');
	$req = array_column($one['requirements'], null, 'name');
	$check($req['kuehlleistung']['raw'] === '31' && $req['kuehlleistung']['unit'] === 'kW' && $req['vorlauf_temp']['value'] === '0' && $req['vorlauf_temp']['unit'] === '°C', 'Original units, decimal strings or zero changed');
	$check($req['volumenstrom']['state'] === 'requirement_missing' && $c['assessments']['einsatzbereich']['state'] === 'feature_present_comparable', 'Missing requirement or structural match conflated');
	$two = $compare->compare(918, $roots);
	$check(array_column(array_column($two['candidates'], 'article'), 'id') == [2804,2805], 'Manual order changed');
	$check(array_column(array_column($two['candidates'], 'configuration'), 'refrigerant') === ['R410A','R454B'], 'Different refrigerants merged');
	$reverse = $compare->compare(918, array_reverse($roots));
	$check(array_column(array_column($reverse['candidates'], 'article'), 'id') == [2805,2804], 'Automatic performance ranking');
	$check(!array_intersect(['ranking','score','recommended','best','auto_selection'], array_keys($two)), 'Automatic recommendation contract');
	$unbound = $compare->compare(918, [['itemid' => 2806]])['candidates'][0];
	$check($unbound['configuration'] === null && !$unbound['components'] && $unbound['coverage']['compressor_electrical']['state'] === 'missing' && !$unbound['eligible'], 'ZCE114 SKU heuristic invented components');
	$check(count($compare->compare(918, [], 'SEARCH-LIMIT')['matches']) === 20, 'Search limit not enforced');
	$check($compare->compare(918, [], 'FOREIGN-COMPARE')['matches'] === [], 'Search exposed foreign candidate');
	$check($compare->compare(918, [])['candidates'] === [], 'Empty comparison automatically selected candidates');
	$historical = ['itemid' => 2604, 'reviewid' => $oldId];
	$hist = $compare->compare(918, [$historical])['candidates'][0];
	$check($hist['historical']['result'] === $old['proof']['result'] && $hist['historical']['calculation'] === $old['proof']['calculation'] && $hist['historical']['variant'] === $old['proof']['variant'], 'History recomputed or changed');
	$check($hist['configuration']['identity']['revision'] === $old['proof']['machine']['revision'], 'History automatically upgraded to latest revision');
	$check($hist['calculations']['coupled']['available'] && $hist['assessments']['kuehlleistung']['state'] === 'evidence_incomplete', 'Model availability asserted machine fitness');
	$check($hist['historical']['current']['status'] === 'historical_changed', 'Newer available revision not separate from old result');
	$node = array_values(array_filter($old['proof']['dependencies'], fn($n) => $n['table'] === 'itemtechnicalprofile' && str_contains($n['key'], 'compressor-b')))[0];
	$previousStatus = $db->fetchOne('SELECT status FROM itemtechnicalprofile WHERE id=?', [$node['id']]);
	$db->update('itemtechnicalprofile', ['status' => 'withdrawn'], ['id = ?' => $node['id']]);
	$withdrawn = $compare->compare(918, [$historical])['candidates'][0];
	$check($withdrawn['historical']['current']['status'] === 'historical_invalid' && $withdrawn['historical']['result'] === $old['proof']['result'] && $withdrawn['calculations']['coupled']['blocked'], 'Withdrawal rewrote historical result or allowed calculation');
	$db->update('itemtechnicalprofile', ['status' => $previousStatus], ['id = ?' => $node['id']]);
	$raw = $db->fetchRow('SELECT * FROM technicaldataset WHERE id=?', [$old['proof']['machine']['id']]);
	$db->update('technicaldataset', ['payload_json' => $raw['payload_json'].' '], ['id = ?' => $raw['id']]);
	$corrupt = $compare->compare(918, [$historical])['candidates'][0];
	$check($corrupt['configuration'] === null && $corrupt['historical']['result'] === $old['proof']['result'] && $corrupt['historical']['current']['status'] === 'historical_invalid', 'Invalid current graph hid or changed valid old proof');
	$db->update('technicaldataset', ['payload_json' => $raw['payload_json']], ['id = ?' => $raw['id']]);
	$outside = $db->fetchOne("SELECT id FROM inquirytechnicalreview WHERE inquiryid=918 AND status='unsuitable' ORDER BY id LIMIT 1");
	$limit = $compare->compare(918, [['itemid' => 2604, 'reviewid' => $outside]])['candidates'][0];
	$check($limit['historical']['result']['status'] === 'unsuitable' && $limit['status'] === 'review_required' && !$limit['eligible'], 'Past point violation became overall candidate judgement');
	foreach([
		[['itemid' => 2807]], [['itemid' => '2804 OR 1=1']], [['itemid' => ['2804']]], [['itemid' => true]], [['itemid' => -1]],
		[0 => $roots[0], 2 => $roots[1]], array_fill(0, 4, $roots[0]), [$roots[0],$roots[0]], [['itemid' => 2804, 'datasetid' => $roots[1]['datasetid'], 'revision' => 1, 'hash' => $roots[1]['hash']]],
		[array_replace($roots[0], ['revision' => 2])], [array_replace($roots[0], ['hash' => str_repeat('f',64)])], [['itemid' => 2804, 'reviewid' => $oldId]],
		[['itemid' => 2604, 'reviewid' => $oldId, 'hash' => ['bad']]], [['itemid' => 2804, 'eligible' => true]], [['itemid' => 2804, 'datasetid' => $roots[0]['datasetid']]]
	] as $bad) $reject(fn() => $compare->compare(918, $bad), 'Manipulated candidate reference');
	$reject(fn() => $compare->compare(918, [], str_repeat('x',101)), 'Oversized search');
	$reject(fn() => $compare->compare(920, [$historical]), 'Cross-inquiry saved proof');
	$reject(fn() => $compare->compare(919, $roots), 'Foreign inquiry');
	$db->update('item', ['clientid' => 200], ['id = ?' => 2600]);
	$reject(fn() => $compare->compare(918, $roots), 'Indirect foreign component');
	$foreignIndirect = $compare->compare(918, [$historical])['candidates'][0];
	$check($foreignIndirect['configuration'] === null && $foreignIndirect['historical']['result'] === $old['proof']['result'] && !$foreignIndirect['components'] && !$foreignIndirect['packages'], 'Indirect ownership change leaked current data or erased own historical proof');
	$db->update('item', ['clientid' => 100], ['id = ?' => 2600]);
	$db->update('item', ['parentid' => null], ['id = ?' => 2804]);
	$reject(fn() => $compare->compare(918, $roots), 'Changed variant identity');
	$db->update('item', ['parentid' => 2603], ['id = ?' => 2804]);
	$rights = $db->fetchRow('SELECT * FROM permission WHERE userid=10');
	$items = json_decode($rights['items'], true);
	foreach([['technicaldataset','review'],['technicaldataset','view'],['item','view']] as [$resource,$action]) {
		$changed = $items; $changed[$resource] = array_values(array_diff($changed[$resource], [$action]));
		$db->update('permission', ['items' => json_encode($changed)], ['id = ?' => $rights['id']]);
		$reject(fn() => $compare->compare(918, $roots), 'Missing '.$resource.'.'.$action);
	}
	$db->update('permission', ['items' => $rights['items'], 'contacts' => '{}'], ['id = ?' => $rights['id']]);
	$reject(fn() => $compare->compare(918, $roots), 'Missing inquiry read right');
	$db->update('permission', ['contacts' => json_encode(['contact' => ['view']])], ['id = ?' => $rights['id']]);
	$check(count($compare->compare(918, $roots)['candidates']) === 2 && !$reviews->canSave(), 'Read-only comparison required inquiry edit or granted save');
	$db->update('permission', ['contacts' => $rights['contacts']], ['id = ?' => $rights['id']]);
	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS'); $reject(fn() => $compare->compare(918, $roots), 'Pilot default enabled');
	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS=101'); $reject(fn() => $compare->compare(918, $roots), 'Other tenant pilot enabled');
	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS=100,101');
	technicalContext(20,200); $reject(fn() => $compare->compare(918, $roots), 'Foreign tenant');
	technicalContext(); Zend_Auth::getInstance()->clearIdentity(); $reject(fn() => $compare->compare(918, $roots), 'Guest'); technicalContext();
	Zend_Registry::set('SiteContext', new DEEC_Site_Context(['id' => 918, 'clientid' => 100], [], ['inquiry','catalog'])); $reject(fn() => $compare->compare(918, $roots), 'Public site context');
	technicalContext(11,101,100); $client = Zend_Registry::get('Client'); $client['modules']['shops'] = 100; Zend_Registry::set('Client', $client);
	$check(count($compare->compare(918, $roots)['candidates']) === 2, 'Authorized parent sharing rejected');
	$client['modules']['items'] = 200; Zend_Registry::set('Client', $client); $reject(fn() => $compare->compare(918, $roots), 'Forged item owner'); technicalContext();
	$form = $db->fetchOne('SELECT fields FROM shopinquiryform WHERE id=918'); $data = $db->fetchOne('SELECT data FROM shopinquirydata WHERE id=918');
	$changedData = json_decode($data, true); $changedData['ruecklauf_temp'] = '-1';
	$db->update('shopinquirydata', ['data' => json_encode($changedData)], ['id = ?' => 918]);
	$conflict = array_column($compare->compare(918, $roots)['requirements'], null, 'name');
	$check($conflict['vorlauf_temp']['state'] === 'requirement_conflict' && $conflict['ruecklauf_temp']['state'] === 'requirement_conflict', 'Contradictory cooling temperatures accepted');
	$changedForm = json_decode($form, true); $changedForm[1][0]['options']['0'] = 'Heizung';
	$db->update('shopinquiryform', ['fields' => json_encode($changedForm)], ['id = ?' => 918]);
	$changedMeaning = array_column($compare->compare(918, $roots)['requirements'], null, 'name');
	$check($changedMeaning['einsatzbereich']['state'] === 'requirement_ambiguous' && $changedMeaning['vorlauf_temp']['state'] === 'requirement_present', 'Ordinal reused with different meaning or implicit cooling temperature test');
	$changedForm = json_decode($form, true); $changedForm[1][1]['unit'] = 'W';
	$db->update('shopinquiryform', ['fields' => json_encode($changedForm)], ['id = ?' => 918]);
	$wrongUnit = array_column($compare->compare(918, $roots)['requirements'], null, 'name');
	$check($wrongUnit['kuehlleistung']['state'] === 'requirement_ambiguous' && $wrongUnit['kuehlleistung']['raw'] === '31' && $wrongUnit['kuehlleistung']['unit'] === 'W', 'W silently converted or interpreted as kW');
	$db->update('shopinquiryform', ['fields' => $form], ['id = ?' => 918]);
	$changedData['kuehlleistung'] = '-3';
	$db->update('shopinquirydata', ['data' => json_encode($changedData)], ['id = ?' => 918]);
	$negative = array_column($compare->compare(918, $roots)['requirements'], null, 'name');
	$check($negative['kuehlleistung']['state'] === 'requirement_ambiguous', 'Negative capacity treated as valid demand');
	$changedData['einsatzbereich'] = 'invalid';
	$db->update('shopinquirydata', ['data' => json_encode($changedData)], ['id = ?' => 918]);
	$invalid = array_column($compare->compare(918, $roots)['requirements'], null, 'name');
	$check($invalid['einsatzbereich']['state'] === 'requirement_ambiguous', 'Ambiguous mode guessed');
	$db->update('shopinquirydata', ['data' => $data], ['id = ?' => 918]);
	$dispatch = function(array $query, string $method = 'GET') {
		$request = new Zend_Controller_Request_HttpTestCase(); $request->clearPost()->clearQuery()->setMethod($method)->setActionName('select')->setParam('id',918);
		if($method === 'POST') $request->setPost($query); else $request->setQuery($query);
		$response = new Zend_Controller_Response_HttpTestCase(); $controller = new InquiryTechnicalTestController($request,$response); $controller->selectAction(); return [$controller->view,$response];
	};
	[$view,$response] = $dispatch(['compare' => '1', 'candidates' => $roots]);
	$check($response->getHttpResponseCode() === 200 && count($view->technicalComparison['candidates']) === 2 && !$view->technical, 'Comparison controller reused receipt-producing individual flow');
	foreach([['compare' => '1', 'candidates' => 'bad'], ['compare' => ['1']], ['compare' => '1', 'q' => ['bad']], ['compare' => '1', 'candidates' => [['itemid' => 2807]]]] as $bad) { [, $response] = $dispatch($bad); $check(in_array($response->getHttpResponseCode(), [403,422], true), 'Malformed comparison controller input'); }
	[, $response] = $dispatch(['compare' => '1', 'candidates' => $roots], 'POST'); $check($response->getHttpResponseCode() === 405, 'Comparison POST accepted');
	$check($snapshot() === $before && $reviews->open(918,$oldId)['row'] === $old['row'], 'Comparison changed business data, customer SKU, form 865, or immutable reviews');
	$check(!str_contains(json_encode($two), 'coefficients_C0_to_C9') && !str_contains(json_encode($two), 'source_range'), 'Comparison copied proprietary tables');
	file_put_contents($work.'/inquiry-comparison.json', json_encode(['candidates' => $roots, 'historical' => $historical]));
	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS');
	echo "PASS: $count D7B2A candidate comparison, exact bindings, coverage, requirements, history, withdrawal, tenant/rights and bounded input assertions\n";
})();
