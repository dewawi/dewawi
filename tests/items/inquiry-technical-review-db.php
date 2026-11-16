<?php

// Included only by the disposable technical-data runner.
(function() use($db, $service, $work, $root) {
	$count = 0;
	$check = function(bool $ok, string $message) use(&$count): void { $count++; if(!$ok) throw new RuntimeException($message); };
	$reject = function(callable $call, string $message) use($check): void {
		try { $call(); } catch(RuntimeException|InvalidArgumentException $e) { $check(!str_contains($e->getMessage(), 'fixture:d7a'), $message.' leaked private identity'); return; }
		throw new RuntimeException('Expected rejection: '.$message);
	};
	technicalContext();
	$fixture = json_decode(file_get_contents($work.'/inquiry-technical.json'), true);
	$r = $fixture['machine'];
	$internal = new Contacts_Service_InquiryTechnical();
	$reviews = new Contacts_Service_InquiryTechnicalReview();
	$view = fn() => $internal->open(918, '', 2604, $r['id'], $r['revision'], $r['sha256']);
	$post = ['calculation' => 'mode:cooling:full', 'reference' => '1', 'cold_inlet' => '20', 'hot_inlet' => '30'];
	$rights = $db->fetchRow('SELECT * FROM permission WHERE userid=10');
	$items = json_decode($rights['items'], true); $items['technicaldataset'][] = 'review';
	$reviewRights = json_encode($items);
	$db->update('permission', ['items' => $reviewRights], ['id = ?' => $rights['id']]);
	$other = $db->fetchRow('SELECT * FROM shopinquirydata WHERE id=918'); unset($other['id']); $other['token'] = 'SYNTHETIC-D7B1-OTHER'; $other['id'] = 920; $db->insert('shopinquirydata', $other);
	$snapshot = function() use($db): array { $out = []; foreach(['item','quote','quotepos','contact','shopinquirydata','shopinquiryform','technicaldataset','itemtechnicalprofile'] as $table) $out[$table] = $db->fetchAll('SELECT * FROM '.$table.' ORDER BY id'); return $out; };
	$before = $snapshot();
	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS');
	$check(!$reviews->internalEnabled() && !$reviews->canSave(), 'Missing entitlement automatically enabled persistence');
	$reject(fn() => $reviews->history(918), 'Ungated history');
	foreach(['*', '0', '100,', 'all', '101'] as $setting) { putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS='.$setting); $check(!$reviews->internalEnabled(), 'Invalid or foreign internal pilot setting'); }
	$db->update('user', ['admin' => 1], ['id = ?' => 10]); technicalContext();
	$check(!$reviews->internalEnabled(), 'Administrator implied a product entitlement');
	$db->update('user', ['admin' => 0], ['id = ?' => 10]); technicalContext();
	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS=100,101');
	$check($reviews->internalEnabled() && $reviews->canSave(), 'Explicit internal pilot or review right rejected');
	$db->update('permission', ['items' => $rights['items']], ['id = ?' => $rights['id']]);
	$check(!$reviews->canSave(), 'Technical read/import rights implied review write');
	$reject(fn() => $reviews->inspect($view()), 'Missing review permission');
	$db->update('permission', ['items' => $reviewRights, 'contacts' => json_encode(['contact' => ['view']])], ['id = ?' => $rights['id']]);
	$reject(fn() => $reviews->inspect($view()), 'Missing inquiry edit permission');
	$db->update('permission', ['contacts' => $rights['contacts']], ['id = ?' => $rights['id']]);
	$calculated = $reviews->calculate($view(), $post);
	$check(strlen($calculated['review_token']) === 64 && $calculated['result']['fully_coupled'], 'No bounded server receipt for result');
	$id = $reviews->save(918, $calculated['review_token']);
	$saved = $reviews->open(918, $id); $first = $saved['row']; $p = $saved['proof'];
	$check($p['result']['eligible'] === false && $p['result']['commercial_status'] === 'rollout_blocked' && $p['result']['saas_entitlement_status'] === 'unavailable', 'Saved review grants release or entitlement');
	$check($p['result']['mathematical_status'] === 'calculated' && $p['result']['status'] === 'review_required' && $saved['current']['status'] === 'review_required', 'Historical/current status or empty-object identity changed');
	$metric = array_column($p['result']['metrics'], null, 'name');
	$check(abs($metric['compressor_input_kW']['value'] - 7.15) < 1e-8 && $metric['compressor_input_kW']['unit'] === 'kW', 'Historical numeric result or unit lost');
	$check($p['article']['parentid'] === 2603 && count($p['components']) >= 5 && count($p['dependencies']) > 5, 'Variant/component/dependency evidence incomplete');
	$check($p['calculation']['mode'] === 'cooling' && $p['calculation']['stage'] === 'full' && $p['calculation']['request']['secondary_boundaries']['evaporator']['inlet_degC'] === '20', 'Mode/stage/typed request missing');
	$check(!str_contains($first['payload_json'], 'coefficients_C0') && !str_contains($first['payload_json'], 'pressure_losses') && !str_contains($first['payload_json'], 'source_range'), 'Proprietary source tables duplicated');
	$check($reviews->save(918, $calculated['review_token']) === $id, 'Repeated form created a new review');

	$identity=clone Zend_Auth::getInstance()->getIdentity();$switched=clone $identity;$switched->clientid=101;Zend_Auth::getInstance()->getStorage()->write($switched);
	$reject(fn()=>$reviews->save(918,$calculated['review_token']),'Receipt crossed selected tenant context');
	Zend_Auth::getInstance()->getStorage()->write($identity);
	$check($p['context']['clientid']===100 && $p['context']['actorid']===10,'Selected tenant audit context missing');
	$inspection = $reviews->inspect($view(), $id, 'mode:cooling:full');
	$secondId = $reviews->save(918, $inspection['review_token']);
	$second = $reviews->open(918, $secondId);
	$check($second['proof']['result']['status'] === 'technical_incomplete' && $second['proof']['result']['mathematical_status'] === 'not_calculated' && !$second['proof']['result']['metrics'], 'Blocked inspection invented numeric results');
	$check($second['proof']['calculation']['mode']==='cooling' && $second['proof']['calculation']['stage']==='full' && $second['proof']['calculation']['method']==='inspection','Explicit planned mode/stage lost in an uncalculated review');
	$check((int)$second['row']['previousid'] === $id && (int)$second['row']['version'] === 2 && (int)$second['row']['createdby'] === 10 && $second['row']['created'], 'Version, predecessor or audit conventions lost');
	$check($reviews->open(918, $id)['row'] === $first, 'New review overwrote history');
	$history = $reviews->history(918);
	$check(count($history) === 2 && (int)$history[0]['id'] === $secondId && !isset($history[0]['payload_json']), 'Inquiry history missing or overexposed');
	$check(count($reviews->history(918, 2)) === 1, 'Version cursor ignored');
	$start = $reviews->startingInput(918, $id);
	$check($start['cold_inlet'] === '20' && $start['hot_inlet'] === '30' && $start['fromreview'] === $id && !isset($start['result']), 'Reuse recalculated or changed historical inputs');
	$reject(fn() => $reviews->open(920, $id), 'Cross-inquiry review');
	$reject(fn() => $reviews->save(920, $calculated['review_token']), 'Cross-inquiry receipt');
	$reject(fn() => $reviews->open(919, $id), 'Foreign inquiry history');
	$reject(fn() => $reviews->inspect($internal->open(918, '', 2603)), 'Missing machine reference');
	$forged = $view(); $forged['item']['id'] = 9999; $reject(fn() => $reviews->inspect($forged), 'Foreign article receipt');
	$forged = $view(); $forged['item']['id'] = 2603; $reject(fn() => $reviews->inspect($forged), 'Invalid variant receipt');
	$forged = $view(); $forged['machine']['identity']['revision'] = 2; $reject(fn() => $reviews->inspect($forged), 'Wrong technical revision');
	$reject(fn() => $reviews->save(918, str_repeat('f',64)), 'Invented browser receipt');
	$records = new Contacts_Model_DbTable_Inquirytechnicalreview();
	$reject(fn() => $records->update(['status' => 'approved'], 'id='.$id), 'Historical update');
	$reject(fn() => $records->delete('id='.$id), 'Historical delete');
	$reject(fn() => $records->insert(['clientid' => 100]), 'Unchecked direct insert');
	$pending = (new Zend_Session_Namespace('InquiryTechnicalReview'))->pending;
	$expired = $pending; $expired[$calculated['review_token']]['expires'] = time()-1;
	$reviewSession = new Zend_Session_Namespace('InquiryTechnicalReview'); $reviewSession->pending = $expired;
	$reject(fn() => $reviews->save(918, $calculated['review_token']), 'Expired receipt');
	$reviewSession->pending = $pending;
	foreach(['items' => json_encode(['item' => ['view','edit']]), 'contacts' => '{}'] as $field => $value) {
		$db->update('permission', [$field => $value], ['id = ?' => $rights['id']]);
		$reject(fn() => $reviews->open(918, $id), 'Missing '.$field.' historical access');
		$db->update('permission', ['items' => $reviewRights, 'contacts' => $rights['contacts']], ['id = ?' => $rights['id']]);
	}
	technicalContext(20, 200); $reject(fn() => $reviews->open(918, $id), 'Foreign tenant review ID'); technicalContext();
	Zend_Auth::getInstance()->clearIdentity(); $reject(fn() => $reviews->open(918, $id), 'Guest historical result'); technicalContext();
	$child = $db->fetchRow('SELECT * FROM permission WHERE userid=11'); $childItems = json_decode($child['items'],true); $childItems['technicaldataset'][]='review';
	$db->update('permission',['items'=>json_encode($childItems),'contacts'=>json_encode(['contact'=>['view','edit']])],['id = ?'=>$child['id']]);
	technicalContext(11,101,100);$c=Zend_Registry::get('Client');$c['modules']['shops']=100;Zend_Registry::set('Client',$c);

	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS=100');$reject(fn()=>$reviews->open(918,$id),'Parent data sharing implied pilot enablement');putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS=100,101');
	$check($reviews->open(918,$id)['proof']['article']['id']===2604,'Authorized parent/child history rejected');
	$c['modules']['items']=200;Zend_Registry::set('Client',$c);$reject(fn()=>$reviews->open(918,$id),'Forged item owner through review');technicalContext();
	$profile = array_values(array_filter($p['dependencies'], fn($node) => $node['table']==='itemtechnicalprofile' && str_contains($node['key'],'compressor-b')))[0];
	$stale = $reviews->inspect($view());
	$db->update('itemtechnicalprofile',['status'=>'withdrawn'],['id = ?'=>$profile['id']]);
	$withdrawn = $reviews->open(918,$id);
	$check($withdrawn['current']['status']==='historical_invalid' && $withdrawn['row']===$first && $withdrawn['proof']===$p,'Withdrawal overwrote/recalculated historical result');
	$reject(fn()=>$reviews->save(918,$stale['review_token']),'Receipt preceding withdrawal');
	$blocked = $reviews->inspect($view()); $blockedId=$reviews->save(918,$blocked['review_token']);
	$check($reviews->open(918,$blockedId)['proof']['result']['status']==='technical_incomplete','Withdrawn inspection could not be stored as blocked');
	$db->update('itemtechnicalprofile',['status'=>'draft'],['id = ?'=>$profile['id']]);
	$raw=$db->fetchRow('SELECT * FROM technicaldataset WHERE id=?',[$r['id']]);
	$db->update('technicaldataset',['payload_json'=>$raw['payload_json'].' '],['id = ?'=>$r['id']]);
	$check($reviews->open(918,$id)['current']['status']==='historical_invalid','Technical hash tampering not separated from historical result');
	$db->update('technicaldataset',['payload_json'=>$raw['payload_json']],['id = ?'=>$r['id']]);
	foreach(['payload_json'=>$first['payload_json'].' ', 'status'=>'approved', 'itemid'=>2603, 'previousid'=>$secondId] as $field=>$changed) {
		$db->update('inquirytechnicalreview',[$field=>$changed],['id = ?'=>$id]);
		$reject(fn()=>$reviews->open(918,$id),'Tampered review '.$field);
		$db->update('inquirytechnicalreview',[$field=>$first[$field]],['id = ?'=>$id]);
	}
	$originalData=$db->fetchOne('SELECT data FROM shopinquirydata WHERE id=918');
	$stale=$reviews->inspect($view());$db->update('shopinquirydata',['data'=>'{"changed":"explicit test only"}'],['id = ?'=>918]);
	$check(in_array('inquiry_values_changed',$reviews->open(918,$id)['current']['reasons'],true),'Changed customer requirements not recognized');
	$reject(fn()=>$reviews->save(918,$stale['review_token']),'Stale customer boundary');
	$db->update('shopinquirydata',['data'=>$originalData],['id = ?'=>918]);
	$payload=Items_Service_TechnicalJson::decode($raw['payload_json']);$next=clone $payload->record;$next->revision=2;$next->supersedes=(object)['dataset_key'=>$next->dataset_key,'revision'=>1];
	$map=['items'=>[]];foreach($view()['machine']['items'] as $ref=>$binding)$map['items'][$ref]=$binding->id;
	$service->importJson(Items_Service_TechnicalJson::encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$next]]),$map);
	$check($reviews->open(918,$id)['current']['status']==='historical_changed' && $reviews->open(918,$id)['row']===$first,'New revision replaced historical evidence');
	$before['technicaldataset']=$db->fetchAll('SELECT * FROM technicaldataset ORDER BY id');
	// Actual concurrent processes use the same bounded receipt, never a production socket.
	$parallel = function(array $tokens) use($work,$root,$check): array {
		$pending=(new Zend_Session_Namespace('InquiryTechnicalReview'))->pending;$jobs=[];
		$barrier=$work.'/review-start';if(is_file($barrier))unlink($barrier);
		foreach($tokens as $token){$file=$work.'/review-worker-'.bin2hex(random_bytes(8)).'.bin';file_put_contents($file,serialize(['token'=>$token,'receipt'=>$pending[$token]]));$pipes=[];$process=proc_open(['php',$root.'/tests/items/inquiry-review-worker.php',$work.'/mysql.sock',$file],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$jobs[]=[$process,$pipes,$file];}
		file_put_contents($barrier,'start');$ids=[];
		foreach($jobs as [$process,$pipes,$file]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);$check($code===0&&ctype_digit(trim($out)),'Concurrent save failed: '.$err);$ids[]=(int)trim($out);unlink($file);}return $ids;
	};
	$parallelView=$reviews->inspect($view(),$id);$ids=$parallel([$parallelView['review_token'],$parallelView['review_token']]);$check($ids[0]===$ids[1],'Parallel retry duplicated a review');
	$a=$reviews->inspect($view(),$id);$b=$reviews->inspect($view(),$id);$ids=$parallel([$a['review_token'],$b['review_token']]);
	$check($ids[0]!==$ids[1] && abs((int)$reviews->open(918,$ids[0])['row']['version']-(int)$reviews->open(918,$ids[1])['row']['version'])===1,'Concurrent new reviews lost version ordering');
	$check($reviews->open(918,$id)['row']===$first && $snapshot()===$before,'Persistence changed original inquiries, articles, offers or historical proof');
	// Exercise the real save action, including direct and AJAX-style invalid inputs.
	$dispatch = function(array $post,string $method='POST') {
		$request=new Zend_Controller_Request_HttpTestCase();$request->clearPost()->clearQuery()->setMethod($method)->setActionName('save')->setParam('id',918)->setPost($post);
		$response=new Zend_Controller_Response_HttpTestCase();$controller=new InquiryTechnicalTestController($request,$response);$controller->saveAction();return [$controller->view,$response];
	};
	$token=$reviews->inspect($view())['review_token'];$csrf=(new Zend_Session_Namespace('InquiryTechnical'))->token;
	foreach([[],['technical_csrf'=>'wrong'],['technical_csrf'=>['bad']]] as $bad){[, $response]=$dispatch($bad+['review_token'=>$token]);$check($response->getHttpResponseCode()===403,'Save CSRF bypass');}
	foreach([['review_token'=>['bad']],['review_token'=>str_repeat('x',100000)],['review_token'=>$token,'payload_json'=>'{"eligible":true}']] as $bad){[, $response]=$dispatch($bad+['technical_csrf'=>$csrf]);$check($response->getHttpResponseCode()===422,'Unchecked or oversized save input accepted');}
	[, $response]=$dispatch(['review_token'=>$token,'technical_csrf'=>$csrf],'GET');$check($response->getHttpResponseCode()===405,'GET mutated a review');
	[$savedView,$response]=$dispatch(['review_token'=>$token,'technical_csrf'=>$csrf]);$check($response->getHttpResponseCode()===200 && $savedView->technicalSavedId,'Controller save failed');
	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS');[, $response]=$dispatch(['review_token'=>$token,'technical_csrf'=>$csrf]);$check($response->getHttpResponseCode()===403,'Rollout configuration bypassed');
	$check($snapshot()===$before,'Controller review save changed business state');

	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS=100,101');

	$failed=$reviews->calculate($view(),array_diff_key($post,['reference'=>true]));$failedId=$reviews->save(918,$failed['review_token']);
	$check($reviews->open(918,$failedId)['proof']['result']['mathematical_status']==='failed','Blocked valid calculation not distinguished from no calculation');
	$key=array_keys(array_filter($view()['choices'],fn($c)=>$c['input']==='compressor'))[0];
	$outside=$reviews->calculate($view(),['calculation'=>$key,'reference'=>'1','Te_degC'=>'5','Tc_degC'=>'100']);$outsideId=$reviews->save(918,$outside['review_token']);
	$check($reviews->open(918,$outsideId)['proof']['result']['status']==='unsuitable','Operating envelope violation not stored as unsuitable');
	$partialCases=[
		['fixture:d7a-thermal:machine:1','thermal_circuit',['request_json'=>Items_Service_TechnicalJson::encode(thermalRequest('water'))]],
		['fixture:d7a-parallel:machine:2','thermal_machine',['request_json'=>Items_Service_TechnicalJson::encode(thermalMachineRequest(thermalMachineBundle('both_shared','fixture:d7a-parallel')))]],
		['fixture:d7a-hydraulic:machine:2','hydraulics',['request_json'=>Items_Service_TechnicalJson::encode(fluidHydraulicRequest(fluidMachineBundle('fixture:d7a-hydraulic')))]],
		['fixture:d7a-distribution:machine:2','distribution',['request_json'=>Items_Service_TechnicalJson::encode(hydraulicDistributionRequest())]]
	];
	foreach($partialCases as [$datasetKey,$choice,$conditions]){
		$target=$db->fetchRow('SELECT * FROM technicaldataset WHERE clientid=100 AND dataset_key=?',[$datasetKey]);
		$check((bool)$target,'Missing partial fixture '.$datasetKey);
		$partial=$internal->open(918,'',2604,(int)$target['id'],1,$target['payload_sha256']);
		$computed=$reviews->calculate($partial,['calculation'=>$choice,'reference'=>'1']+$conditions);$partialId=$reviews->save(918,$computed['review_token']);


		$check(count($reviews->open(918,$partialId)['proof']['result']['metrics'])>0,'Partial calculation result omitted: '.$choice);
		if($choice==='thermal_circuit'){
			$extended=json_decode($conditions['request_json']);$extended->compressor_contexts=(object)[];
			$readOnly=$reviews->calculate($partial,['calculation'=>$choice,'reference'=>'1','request_json'=>json_encode($extended)]);
			$check($readOnly['result']['solution']!==null&&!empty($readOnly['review_unavailable'])&&!isset($readOnly['review_token']),'Existing extended read-only calculation was changed or unchecked inputs were staged');
		}
		$blockedPost=['calculation'=>$choice]+$conditions;$blockedResult=$reviews->calculate($partial,$blockedPost);$blockedSaved=$reviews->save(918,$blockedResult['review_token']);
		$check($reviews->open(918,$blockedSaved)['proof']['result']['status']==='technical_incomplete','Valid blocked base inputs cannot be saved: '.$choice);
		$reject(fn()=>$reviews->calculate($partial,['calculation'=>$choice,'request_json'=>'{"unvalidated_private_payload":"must not persist"}']),'Unknown root JSON on a blocked calculation: '.$choice);
		$bad=json_decode($conditions['request_json']);
		if($choice==='thermal_circuit')$bad->evaporator->unvalidated_private_payload='must not persist';
		elseif($choice==='thermal_machine')$bad->secondary_boundaries[0]->boundary->unvalidated_private_payload='must not persist';
		elseif($choice==='hydraulics')$bad->loops[0]->composition->unvalidated_private_payload='must not persist';
		else $bad->composition->unvalidated_private_payload='must not persist';
		$reject(fn()=>$reviews->calculate($partial,['calculation'=>$choice,'request_json'=>json_encode($bad)]),'Unknown nested JSON on a blocked calculation: '.$choice);
	}
	// Explicit existing manual offer creation must not consume the saved machine selection.
	$db->insert('contact',['id'=>6500,'contactid'=>6500,'catid'=>0,'clientid'=>100,'name1'=>'Synthetic D7B1 manual customer']);
	$db->insert('currency',['code'=>'EUR','name'=>'Synthetic Euro','symbol'=>'EUR','clientid'=>100,'ordering'=>0]);
	$db->insert('language',['code'=>'de_DE','name'=>'Synthetic German','clientid'=>100,'ordering'=>0]);
	$db->insert('template',['id'=>6500,'clientid'=>100,'activated'=>1,'default'=>1]);
	$db->update('shopinquirydata',['contactid'=>6500],['id = ?'=>918]);
	$workflow=new Contacts_Service_InquiryWorkflow();$manual=$workflow->createQuoteDraft(918);
	$check($manual['created'] && (int)$db->fetchOne('SELECT COUNT(*) FROM quotepos WHERE parentid=?',[$manual['id']])===0,'Stored technical selection became an automatic offer position');
	$check($db->fetchOne('SELECT total FROM quote WHERE id=?',[$manual['id']])=='0' && $db->fetchOne('SELECT data FROM shopinquirydata WHERE id=918')===$originalData,'Manual offer copied technical values or changed customer data');
	$check($workflow->createQuoteDraft(918)===['id'=>$manual['id'],'created'=>false],'Existing manual quote replay changed');
	$check($reviews->open(918,$id)['row']===$first,'Manual quote creation changed historical technical evidence');
	file_put_contents($work.'/inquiry-review.json',json_encode(['id'=>$id]));
	putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS');
	echo "PASS: $count D7B1 persistence, immutable evidence, current status, tenant/rights, rollout, CSRF, concurrency and replay assertions\n";
})();
