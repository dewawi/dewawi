<?php

// Included by technical-data.php; all writes target the runner's disposable MariaDB.
require_once __DIR__.'/fixtures/machine-stage.php';
(function() use ($service, $db, $encode, $evidence, $mapping) {
	$count = 0;
	$check = function(bool $ok, string $message) use (&$count): void { $count++; if(!$ok) throw new RuntimeException($message); };
	$reject = function(callable $work, string $message) use ($check): void {
		try { $work(); } catch(RuntimeException|InvalidArgumentException $e) {
			$check(!str_contains($e->getMessage(), 'fixture:d4') && !str_contains($e->getMessage(), 'SYNTHETIC'), $message.': identity leaked'); return;
		}
		throw new RuntimeException('Expected rejection: '.$message);
	};
	$clone = fn($v) => unserialize(serialize($v));
	$snapshot = function() use ($db) {
		$rows = []; foreach(['technicaldataset', 'itemtechnicalprofile', 'item', 'itemvariantopt', 'quote', 'quotepos', 'shopinquiryform', 'shopinquirydata'] as $table) $rows[$table] = $db->fetchAll('SELECT * FROM '.$table.' ORDER BY id');
		return $rows;
	};
	$evaluate = function(array $identity, stdClass $request, string $mode = 'mathematical_reference') use ($service, $snapshot, $check) {
		$before = $snapshot(); $result = $service->evaluateMachineStage($identity['id'], $identity['revision'], $identity['sha256'], $request, $mode);
		$check($snapshot() === $before, 'Machine evaluation changed persisted data');
		$check($result['eligible'] === false && $result['useful_machine_cooling_capacity']['value'] === null, 'Mathematical sum became released machine cooling');
		return $result;
	};
	technicalContext();
	$mapping['items']['fixture:compressor'] = 1060;
	$db->insert('item', ['id' => 1060, 'catid' => 0, 'shopid' => 0, 'clientid' => 100, 'sku' => 'SYNTHETIC-compressor', 'manufacturersku' => 'SYNTHETIC-compressor']);
	$mapping['items']['fixture:compressor-b'] = 1050;
	$db->insert('item', ['id' => 1050, 'catid' => 0, 'shopid' => 0, 'clientid' => 100, 'sku' => 'SYNTHETIC-compressor-b', 'manufacturersku' => 'SYNTHETIC-compressor-b']);
	$import = function(stdClass $bundle) use ($service, $encode, $mapping): array {
		$saved = $service->importJson($encode($bundle), $mapping)['revisions'];
		foreach($saved as $key => $identity) if(str_contains($key, ':machine:')) return [$identity, $saved];
		throw new RuntimeException('Missing synthetic machine');
	};
	foreach([[1,1,10.0,2.05],[1,2,30.0,7.15],[2,2,60.0,14.3],[4,2,120.0,28.6]] as [$circuits,$compressors,$q,$p]) {
		$prefix = 'fixture:d4:'.$circuits.':'.$compressors; $bundle = machineStageBundle($circuits, $compressors, $prefix);
		[$identity,$saved] = $import($bundle); $request = machineStageRequest($circuits); $result = $evaluate($identity,$request);
		$check(abs($result['stage_compressor_sums']['cooling_capacity']['value'] - $q) < 1e-12 && abs($result['stage_compressor_sums']['power_input']['value'] - $p) < 1e-12, 'Stored independent Q/P sum');
		$check(abs($result['stage_compressor_sums']['compressor_cop']['value'] - $q/$p) < 1e-12, 'Stored aggregate compressor COP');
		$check($result['physical_component_count'] === $circuits * ($compressors + 1) + 4 && isset($result['components']['evap']), 'Shared physical device duplicated');
		$check($result['machine']['article']->id === 1007 && $result['machine']['sha256'] === $identity['sha256'], 'Machine article/revision evidence missing');
		$check(json_encode($result, JSON_THROW_ON_ERROR) === json_encode($evaluate($identity,$request), JSON_THROW_ON_ERROR), 'Stored determinism');
		foreach($result['compressors'] as $instance) $check($instance['evaluation']['package']['revision'] === 1 && $instance['evaluation']['calculation']['eligible'] === false, 'Exact D3 revision missing');
		foreach($result['dependencies'] as $dependency) $check(strlen($dependency['sha256']) === 64 && $dependency['normalization_version'] === 1 && $dependency['sources'] !== null, 'Incomplete dependency evidence');
		if($circuits === 2) { $main = $identity; $mainSaved = $saved; $mainBundle = $bundle; }
	}
	$identity = $main; $saved = $mainSaved; $bundle = $mainBundle; $request = machineStageRequest();
	$out = $evaluate($identity,$request,'technical');
	$check($out['status'] === 'review_required' && $out['stage_compressor_sums']['cooling_capacity']['value'] === null && in_array('machine_release_required_for_technical_inputs',$out['reasons'],true), 'Technical path accepted unapproved machine inputs');
	$check($out['original_request'] == $request, 'Original inputs lost');
	$r = $clone($request); $r->circuit_points[1]->point->Te_degC = '0'; $r->circuit_points[1]->point->Tc_degC = '40'; $out = $evaluate($identity,$r);
	$check(abs($out['stage_compressor_sums']['cooling_capacity']['value'] - 58.8) < 1e-12 && abs($out['stage_compressor_sums']['power_input']['value'] - 14.15) < 1e-12 && $out['stage_compressor_sums']['refrigerant_mass_flow']['value'] === null, 'Distinct stored circuit points');
	foreach(range(1,4) as $stage) {
		$r = machineStageRequest(2,'stage'.$stage); $out = $evaluate($identity,$r);
		$check($out['stage_compressor_sums']['active_compressor_count'] === $stage, 'Stored active stage');
		foreach($out['compressors'] as $compressor) if(!$compressor['active']) $check($compressor['evaluation'] === null, 'Inactive instance evaluated');
	}
	foreach(['reversible_heating','partial_heat_recovery','full_heat_recovery'] as $mode) { $r=$clone($request); $r->operating_mode=$mode; $reject(fn()=>$evaluate($identity,$r),'Unsupported mode'); }
	$r=$clone($request);$r->stage_key='missing';$reject(fn()=>$evaluate($identity,$r),'Missing stage');
	$r=$clone($request);unset($r->circuit_points[0]->point->Tc_degC);$reject(fn()=>$evaluate($identity,$r),'Missing Tc');
	$r=$clone($request);$r->eligible=true;$reject(fn()=>$evaluate($identity,$r),'Caller requests eligibility');
	$reject(fn()=>$service->evaluateMachineStage($identity['id'],2,$identity['sha256'],$request),'Wrong machine revision');
	$reject(fn()=>$service->evaluateMachineStage($identity['id'],1,str_repeat('0',64),$request),'Wrong machine hash');
	$reject(fn()=>$service->approve('technicaldataset',$identity['id'],$identity['sha256'],$evidence),'Machine approval from mathematical sum');
	$outside=$clone($request);$outside->circuit_points[1]->point->Tc_degC='61';$out=$evaluate($identity,$outside);
	$check($out['status']==='unsuitable' && $out['stage_compressor_sums']['cooling_capacity']['value']===null && $out['violations'], 'Stored outside operating limit');
	foreach($bundle->datasets as $record) if($record->dataset_key==='fixture:d4:2:2:compressor')$source=$record;
	$context=(object)['manufacturer_name'=>$source->manufacturer_name,'manufacturer_revision'=>$source->manufacturer_revision,'model'=>$source->data->model,'refrigerant'=>$source->data->refrigerant,'conditions'=>$clone($source->data->conditions),'temperature_basis'=>$clone($source->data->temperature_basis),'electrical'=>$clone($source->data->electrical)];
	$context->conditions->frequency_Hz='60';$r=$clone($request);$r->compressor_contexts=(object)['r1.compressor1'=>$context];$out=$evaluate($identity,$r);
	$check($out['status']==='unsuitable' && $out['stage_compressor_sums']['cooling_capacity']['value']===null && in_array('r1.compressor1:conditions_mismatch',$out['violations'],true),'Mismatched stored operating context');
	foreach(['missing_profile','wrong_profile_revision','mixed_refrigerant','incompatible_conditions','missing_optional','indirect','missing_polynomial','wrong_model','pretend_approved'] as $case) {
		$b=machineStageBundle(2,2,'fixture:d4:'.$case);
		foreach($b->datasets as $d) if($d->dataset_type==='machine_configuration')$m=$d;
		switch($case) {
			case 'missing_profile': $m->data->components[0]->profile_key='missing';break;
			case 'wrong_profile_revision': $m->data->components[0]->profile_revision=99;break;
			case 'mixed_refrigerant': $b->datasets[1]->data->refrigerant='other-fluid';break;
			case 'incompatible_conditions': $b->datasets[1]->data->conditions->superheat_K='8';break;
			case 'missing_optional': unset($b->datasets[1]->data->quantities->refrigerant_mass_flow);break;
			case 'pretend_approved':
				foreach($m->data->stages as $stage)$stage->release_status='approved';
				$m->data->approved_stage_keys=array_map(fn($stage)=>$stage->stage_key,$m->data->stages);$m->data->mode_topologies[0]->release_status='approved';break;
			default:
				$curve=$b->datasets[1];
				$spec=technicalComponent('compressor','compressor',$m->data->components[0]->ports,'fixture-fluid',(object)['polynomial_ref'=>$case==='missing_polynomial'?null:(object)['dataset_key'=>$curve->dataset_key,'revision'=>1],'oil_return_stage_limits_ref'=>null]);
				$spec->dataset_key='fixture:d4:'.$case.':compressor-spec';$spec->data->model=$curve->data->model;$spec->data->electrical=$clone($curve->data->electrical);
				if($case==='wrong_model')$spec->data->model='SYNTHETIC-WRONG';
				$b->datasets[]=$spec;$b->profiles[0]->dataset_ref=(object)['dataset_key'=>$spec->dataset_key,'revision'=>1];$b->profiles[0]->binding->model=$spec->data->model;
		}
		if(in_array($case,['missing_profile','wrong_profile_revision','mixed_refrigerant'],true)) { $reject(fn()=>$import($b),'Invalid profile/refrigerant import');continue; }
		[$testIdentity]=$import($b);
		if($case==='wrong_model') { $reject(fn()=>$evaluate($testIdentity,$request),'Indirect polynomial model mismatch');continue; }
		$out=$evaluate($testIdentity,$request);
		if(in_array($case,['incompatible_conditions','missing_polynomial'],true)) $check($out['stage_compressor_sums']['cooling_capacity']['value']===null,'Incomplete/incompatible stored sum');
		else $check($out['stage_compressor_sums']['cooling_capacity']['value']===60.0,'Indirect curve or optional missing quantity');
		if($case==='pretend_approved') {
			$check($out['eligible']===false && in_array('stage_control_release_missing',$out['reasons'],true),'Declared stage approval bypassed controls');
			$check($evaluate($testIdentity,$request,'technical')['stage_compressor_sums']['cooling_capacity']['value']===null,'Declared flags released machine inputs');
			$reject(fn()=>$service->approve('technicaldataset',$testIdentity['id'],$testIdentity['sha256'],$evidence),'Declared flags approved machine physics');
		}
		if($case==='missing_optional')$check($out['stage_compressor_sums']['refrigerant_mass_flow']['value']===null,'Missing stored mass flow estimated');
	}
	$equipmentBundle=machineStageBundle(2,2,'fixture:d4:equipment');
	foreach($equipmentBundle->datasets as $dataset) if($dataset->dataset_type==='machine_configuration')$equipmentMachine=$dataset->data;
	$equipmentMachine->variant_signature->extra_option_refs=['fixture:included'];
	$equipmentMachine->selected_equipment=[(object)['option_ref'=>'fixture:included','component_key'=>'fixture:tank','technical_effect'=>'unknown','target_instance_key'=>'tank','replacement_component_key'=>null,'effect_dataset_ref'=>null,'source_locator'=>null,'included'=>false,'option_group_ref'=>'fixture:group','component_item_ref'=>'fixture:tank','base_item_ref'=>'fixture:base','variant_item_ref'=>'fixture:machine']];
	[$equipmentIdentity]=$import($equipmentBundle);$equipmentResult=$evaluate($equipmentIdentity,$request);
	$check($equipmentResult['stage_compressor_sums']['cooling_capacity']['value']===60.0 && in_array('option_effect_unverified',$equipmentResult['reasons'],true),'Selected equipment lost its pinned article binding or blocker');
	$poly=$saved['technicaldataset:fixture:d4:2:2:compressor@1'];
	$profile=$saved['itemtechnicalprofile:fixture:d4:2:2:profile:compressor@1'];
	technicalContext(20,200);$foreignRecord=technicalPolynomial();
	$foreign=$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$foreignRecord]]))['revisions']['technicaldataset:fixture:compressor@1'];technicalContext();
	$rootRow=$service->getById('technicaldataset',$identity['id']);
	$profileRow=$service->getById('itemtechnicalprofile',$profile['id']);
	foreach(['hash','revision','profile_revision','missing_reference','cross_owner','article','profile_header','variant'] as $case) {
		$oldPoly=$service->getById('technicaldataset',$poly['id']);
		try {
			switch($case) {
				case 'hash': $db->update('technicaldataset',['payload_json'=>$oldPoly['payload_json'].' '],['id = ?'=>$poly['id']]); break;
				case 'revision': $db->update('technicaldataset',['revision'=>999],['id = ?'=>$poly['id']]); break;
				case 'profile_revision': $db->update('itemtechnicalprofile',['revision'=>999],['id = ?'=>$profile['id']]); break;
				case 'missing_reference':
					$payload=json_decode($rootRow['payload_json']);array_pop($payload->references);$json=$encode($payload);$db->update('technicaldataset',['payload_json'=>$json,'payload_sha256'=>hash('sha256',$json)],['id = ?'=>$identity['id']]);break;
				case 'cross_owner': $payload=json_decode($rootRow['payload_json']);$payload->references[0]=(object)$foreign;$json=$encode($payload);$db->update('technicaldataset',['payload_json'=>$json,'payload_sha256'=>hash('sha256',$json)],['id = ?'=>$identity['id']]);break;
				case 'article': $db->update('item',['manufacturersku'=>'changed'],['id = ?'=>1060]);break;
				case 'profile_header': $db->update('itemtechnicalprofile',['itemid'=>1004],['id = ?'=>$profile['id']]);break;
				case 'variant': $db->insert('itemvariantopt',['itemid'=>1007,'itemoptid'=>51,'clientid'=>100]);break;
			}
			$changed=$identity;if(in_array($case,['missing_reference','cross_owner'],true))$changed['sha256']=hash('sha256',$json);
			if($case==='article') { $out=$evaluate($changed,$request);$check($out['stage_compressor_sums']['cooling_capacity']['value']===null && in_array('article_identity_changed',$out['reasons'],true),'Changed article binding evaluated'); }
			else $reject(fn()=>$evaluate($changed,$request),'Corrupt dependency '.$case);
		} finally {
			$db->update('technicaldataset',['payload_json'=>$oldPoly['payload_json'],'revision'=>1],['id = ?'=>$poly['id']]);
			$db->update('technicaldataset',['payload_json'=>$rootRow['payload_json'],'payload_sha256'=>$rootRow['payload_sha256']],['id = ?'=>$identity['id']]);
			$db->update('itemtechnicalprofile',['revision'=>1,'itemid'=>$profileRow['itemid']],['id = ?'=>$profile['id']]);
			$db->update('item',['manufacturersku'=>'SYNTHETIC-compressor'],['id = ?'=>1060]);
			if($case==='variant')$db->delete('itemvariantopt',['itemid = ?'=>1007,'clientid = ?'=>100]);
		}
	}
	technicalContext(20,200);$reject(fn()=>$evaluate($identity,$request),'Foreign owner');
	technicalContext(11,101,100);$check($evaluate($identity,$request)['stage_compressor_sums']['cooling_capacity']['value']===60.0,'Legitimate parent sharing');
	technicalContext(11,101);$reject(fn()=>$evaluate($identity,$request),'Unshared child owner');technicalContext();
	$rights=$db->fetchOne('SELECT items FROM permission WHERE userid=10 AND deleted=0');
	try {
		$db->update('permission',['items'=>json_encode(['item'=>['view'],'technicaldataset'=>['view']])],['userid = ?'=>10]);
		$check($evaluate($identity,$request,'technical')['status']==='review_required','Read-only technical review denied');
		$reject(fn()=>$evaluate($identity,$request),'Missing mathematical import/edit rights');
		$db->update('permission',['items'=>json_encode(['item'=>['view'],'technicaldataset'=>[]])],['userid = ?'=>10]);
		$reject(fn()=>$evaluate($identity,$request,'technical'),'Missing technical view rights');
	} finally {$db->update('permission',['items'=>$rights],['userid = ?'=>10]);}
	Zend_Registry::set('SiteContext',(object)[]);$reject(fn()=>$evaluate($identity,$request),'Public site context');technicalContext();
	Zend_Auth::getInstance()->clearIdentity();$reject(fn()=>$evaluate($identity,$request),'Missing identity');technicalContext();
	// A new curve revision must not change a machine pinned to revision 1.
	foreach($bundle->datasets as $record) if($record->dataset_key==='fixture:d4:2:2:compressor')$next=$clone($record);
	$next->revision=2;$next->supersedes=(object)['dataset_key'=>$next->dataset_key,'revision'=>1];$next->data->quantities->cooling_capacity->coefficients_C0_to_C9[0]='99000';
	$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$next]]));
	$check($evaluate($identity,$request)['stage_compressor_sums']['cooling_capacity']['value']===60.0,'Implicit latest curve revision');
	$service->approve('technicaldataset',$poly['id'],$poly['sha256'],$evidence);
	$proof=$service->getById('technicaldataset',$poly['id']);
	$profileEvidence=json_decode($evidence);$profileEvidence->scope_key='fixture:compressor';
	$service->approve('itemtechnicalprofile',$profile['id'],$profile['sha256'],$encode($profileEvidence));
	$check($evaluate($identity,$request)['dependencies']['technicaldataset:fixture:d4:2:2:compressor@1']['approval_sha256']!==null,'Approved dependency proof absent');
	$profileProof=$service->getById('itemtechnicalprofile',$profile['id']);
	foreach([['technicaldataset',$poly,$proof],['itemtechnicalprofile',$profile,$profileProof]] as [$table,$target,$original]) {
		try {
			$bad=json_decode($original['approval_json']);$bad->revision_sha256=str_repeat('0',64);$db->update($table,['approval_json'=>$encode($bad)],['id = ?'=>$target['id']]);
			$reject(fn()=>$evaluate($identity,$request),'Forged dependency approval');
		} finally {$db->update($table,['approval_json'=>$original['approval_json']],['id = ?'=>$target['id']]);}
	}
	foreach(['reference_identity','dependency_proof'] as $case) {
		try {
			if($case==='reference_identity') { $bad=json_decode($profileProof['approval_json']);$bad->references[0]->revision->key='missing';$db->update('itemtechnicalprofile',['approval_json'=>$encode($bad)],['id = ?'=>$profile['id']]); }
			else { $bad=json_decode($proof['approval_json']);$bad->evidence->engineering_reference='Changed synthetic approval';$db->update('technicaldataset',['approval_json'=>$encode($bad)],['id = ?'=>$poly['id']]); }
			$reject(fn()=>$evaluate($identity,$request),'Forged approval dependency');
		} finally {
			$db->update('itemtechnicalprofile',['approval_json'=>$profileProof['approval_json']],['id = ?'=>$profile['id']]);
			$db->update('technicaldataset',['approval_json'=>$proof['approval_json']],['id = ?'=>$poly['id']]);
		}
	}
	$secondCurve=$saved['technicaldataset:fixture:d4:2:2:compressor-b@1'];$secondProfile=$saved['itemtechnicalprofile:fixture:d4:2:2:profile:compressor-b@1'];
	$service->approve('technicaldataset',$secondCurve['id'],$secondCurve['sha256'],$evidence);
	$secondEvidence=json_decode($evidence);$secondEvidence->scope_key='fixture:compressor-b';
	$service->approve('itemtechnicalprofile',$secondProfile['id'],$secondProfile['sha256'],$encode($secondEvidence));
	$historical=$evaluate($identity,$request);
	$service->withdraw('itemtechnicalprofile',$secondProfile['id'],$secondProfile['sha256'],'Synthetic inactive dependency withdrawal');
	$out=$evaluate($identity,machineStageRequest(2,'stage1'));
	$check($out['stage_compressor_sums']['cooling_capacity']['value']===null && in_array('dependency_withdrawn',$out['reasons'],true),'Inactive dependency withdrawal ignored');

	$service->withdraw('itemtechnicalprofile',$profile['id'],$profile['sha256'],'Synthetic D4 withdrawal');
	$after=$evaluate($identity,machineStageRequest(2,'stage1'));
	$check($after['stage_compressor_sums']['cooling_capacity']['value']===null && in_array('dependency_withdrawn',$after['reasons'],true),'Withdrawn profile evaluated');
	$check($historical['stage_compressor_sums']['cooling_capacity']['value']===60.0 && $historical['dependencies']['itemtechnicalprofile:fixture:d4:2:2:profile:compressor@1']['status']==='approved','Historical result mutated');
	$service->withdraw('technicaldataset',$poly['id'],$poly['sha256'],'Synthetic D4 curve withdrawal');
	$check($evaluate($identity,$request)['stage_compressor_sums']['cooling_capacity']['value']===null,'Withdrawn polynomial evaluated');
	echo "PASS: $count stored machine-stage assertions (D3 reuse, complete dependency evidence, tenant/rights, article/profile binding, revisions, approvals, withdrawal and read-only behavior)\n";
})();
