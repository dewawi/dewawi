<?php

// Included by technical-data.php; the existing runner owns the disposable MariaDB.
require_once __DIR__.'/fixtures/thermal-circuit.php';
(function() use ($service, $db, $encode, $evidence, $mapping) {
	$count = 0;
	$check = function(bool $ok, string $message) use (&$count): void { $count++; if(!$ok) throw new RuntimeException($message); };
	$reject = function(callable $work, string $message) use ($check): void {
		try { $work(); } catch(RuntimeException|InvalidArgumentException $e) {
			$check(!str_contains($e->getMessage(),'fixture:thermal') && !str_contains($e->getMessage(),'SYNTHETIC'),$message.': private identity leaked'); return;
		}
		throw new RuntimeException('Expected rejection: '.$message);
	};
	$clone = fn($v) => unserialize(serialize($v));
	$snapshot = function() use ($db): array {
		$rows=[];foreach(['technicaldataset','itemtechnicalprofile','item','itemvariantopt','quote','quotepos','shopinquiryform','shopinquirydata'] as $table)$rows[$table]=$db->fetchAll('SELECT * FROM '.$table.' ORDER BY id');return $rows;
	};
	$evaluate = function(array $identity, stdClass $request, string $mode='synthetic_reference') use ($service,$snapshot,$check): array {
		$before=$snapshot();$result=$service->evaluateThermalCircuit($identity['id'],$identity['revision'],$identity['sha256'],$request,$mode);
		$check($snapshot()===$before,'Thermal evaluation wrote to stored data');
		$check($result['eligible']===false && $result['useful_machine_cooling_capacity']['value']===null && $result['machine_efficiency']['value']===null,'Thermal convergence released machine performance');
		return $result;
	};
	technicalContext();
	foreach(['compressor','evaporator','condenser','base','machine','pump','tank','fan','compressor-b'] as $index=>$name) {
		$id=1070+$index;$mapping['items']['fixture:'.$name]=$id;
		$db->insert('item',['id'=>$id,'parentid'=>$name==='machine'?1073:null,'catid'=>0,'shopid'=>0,'clientid'=>100,'sku'=>'SYNTHETIC-'.$name,'manufacturersku'=>'SYNTHETIC-'.$name]);
	}
	$import = function(stdClass $bundle) use ($service,$encode,$mapping): array {
		$saved=$service->importJson($encode($bundle),$mapping)['revisions'];
		foreach($saved as $key=>$identity) if(str_contains($key,':machine:')) return [$identity,$saved];
		throw new RuntimeException('Missing thermal fixture machine');
	};
	foreach(['air','water'] as $kind) {
		$prefix='fixture:thermal:db:'.$kind;$bundle=thermalBundle($kind,$prefix);[$identity,$saved]=$import($bundle);$request=thermalRequest($kind);
		$out=$evaluate($identity,$request);$point=$out['solution']['point'];$balance=$out['solution']['energy_balance'];
		$check($out['convergence']['status']==='converged' && abs((float)$point->Te_degC-5.0)<1e-8 && abs((float)$point->Tc_degC-50.0)<1e-8,'Stored analytical air/water solution');
		$check(abs($balance['compressor_cooling_kW']-10.0)<1e-7 && abs($balance['required_condenser_heat_kW']-12.05)<1e-7 && max(abs($balance['residual_evaporator_kW']),abs($balance['residual_condenser_kW']))<1e-7,'Stored independent energy balances');
		$check($out['machine']['article']->id===1074 && $out['machine']['sha256']===$identity['sha256'],'Thermal machine article/revision missing');
		foreach($saved as $key=>$ref) if(isset($out['dependencies'][$key]))$check($out['dependencies'][$key]['sha256']===$ref['sha256'] && $out['dependencies'][$key]['revision']===$ref['revision'],'Exact thermal dependency hash/revision lost');
		$check(json_encode($out,JSON_THROW_ON_ERROR)===json_encode($evaluate($identity,$request),JSON_THROW_ON_ERROR),'Stored thermal nondeterminism');
		$check(in_array('expansion_system_evidence_missing',$out['reasons'],true) && in_array('control_release_missing',$out['reasons'],true),'Synthetic root bypassed controls/expansion');
		if($kind==='air') { $main=$identity;$mainSaved=$saved;$mainBundle=$bundle;$historical=$out; }
	}
	$identity=$main;$saved=$mainSaved;$bundle=$mainBundle;$request=thermalRequest();
	$map=$saved['technicaldataset:fixture:thermal:db:air:evaporator-map@1'];$model=$saved['technicaldataset:fixture:thermal:db:air:thermal-model@1'];
	$spec=$saved['technicaldataset:fixture:thermal:db:air:evaporator@1'];$profile=$saved['itemtechnicalprofile:fixture:thermal:db:air:profile:evaporator@1'];
	$out=$evaluate($identity,$request,'technical');$check($out['solution']===null && in_array('released_real_thermal_model_not_available',$out['reasons'],true),'Technical path published a synthetic root');
	foreach([$map,$model,$spec,$identity] as $target)$reject(fn()=>$service->approve('technicaldataset',$target['id'],$target['sha256'],$evidence),'Synthetic model/component/machine approval');
	$check(in_array('synthetic_model_not_releasable',$service->technicalStatus('technicaldataset',$map['id'])['reasons'],true),'Synthetic map lost explicit release blocker');
	$reject(fn()=>$service->evaluateThermalCircuit($identity['id'],2,$identity['sha256'],$request),'Wrong root revision');
	$reject(fn()=>$service->evaluateThermalCircuit($identity['id'],1,str_repeat('0',64),$request),'Wrong root hash');
	foreach(['unit','map_role','section','refrigerant','balance','fraction_precision','wrong_circuit'] as $case) {
		$b=thermalBundle('air','fixture:thermal:invalid:'.$case);
		foreach($b->datasets as $r) {
			if($r->dataset_type==='heat_exchanger_map' && $r->data->role==='evaporator')$m=$r;
			if($r->dataset_type==='thermal_balance_model')$energy=$r;
		}
		switch($case) {
			case 'unit':$m->data->variables->volume_flow='kg/h';break;
			case 'map_role':$m->data->component_key='fixture:wrong';break;
			case 'section':$m->data->sections[0]->section_key='other';break;
			case 'refrigerant':$m->data->refrigerant='other-fluid';break;
			case 'balance':$energy->data->energy_balance->other_heat_input_kW='1';break;
			case 'wrong_circuit':$energy->data->circuit_key='other';break;
			case 'fraction_precision':$energy->data->energy_balance->motor_heat_to_refrigerant_fraction='1.00000000000000000001';break;
		}
		$reject(fn()=>$import($b),'Invalid thermal import '.$case);
	}
	foreach($bundle->datasets as $record) if(str_ends_with($record->dataset_key,':compressor'))$source=$record;
	$context=(object)['manufacturer_name'=>$source->manufacturer_name,'manufacturer_revision'=>$source->manufacturer_revision,'model'=>$source->data->model,'refrigerant'=>$source->data->refrigerant,'conditions'=>$clone($source->data->conditions),'temperature_basis'=>$source->data->temperature_basis,'electrical'=>$source->data->electrical];
	$context->conditions->frequency_Hz='60';$wrongContext=$clone($request);$wrongContext->compressor_contexts=(object)['r1.compressor1'=>$context];
	$out=$evaluate($identity,$wrongContext);$check($out['solution']===null && $out['status']==='unsuitable' && in_array('r1.compressor1:conditions_mismatch',$out['reasons'],true),'Thermal context mismatch bypassed D3');
	$indirect=thermalBundle('air','fixture:thermal:indirect');
	foreach($indirect->datasets as $record) { if(str_ends_with($record->dataset_key,':compressor'))$curve=$record;if($record->dataset_type==='machine_configuration')$config=$record; }
	$compressorSpec=technicalComponent('compressor','compressor',$config->data->components[0]->ports,'fixture-fluid',(object)['polynomial_ref'=>(object)['dataset_key'=>$curve->dataset_key,'revision'=>1],'oil_return_stage_limits_ref'=>null]);
	$compressorSpec->dataset_key='fixture:thermal:indirect:compressor-spec';$compressorSpec->data->model=$curve->data->model;$compressorSpec->data->electrical=$clone($curve->data->electrical);
	$indirect->datasets[]=$compressorSpec;$indirect->profiles[0]->dataset_ref=(object)['dataset_key'=>$compressorSpec->dataset_key,'revision'=>1];
	[$indirectIdentity]=$import($indirect);$check($evaluate($indirectIdentity,$request)['convergence']['status']==='converged','Indirect stored compressor specification not reused');
	$root=$service->getById('technicaldataset',$identity['id']);$mapRow=$service->getById('technicaldataset',$map['id']);$profileRow=$service->getById('itemtechnicalprofile',$profile['id']);
	technicalContext(20,200);$foreignRecord=technicalPolynomial();$foreignRecord->dataset_key='fixture:thermal:foreign';
	$foreign=$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$foreignRecord]]))['revisions']['technicaldataset:fixture:thermal:foreign@1'];technicalContext();
	foreach(['map_hash','map_revision','profile_revision','profile_header','omitted_ref','foreign_ref','article','withdrawn_map','withdrawn_component'] as $case) {
		$target=$identity;
		try {
			switch($case) {
				case 'map_hash':$db->update('technicaldataset',['payload_json'=>$mapRow['payload_json'].' '],['id = ?'=>$map['id']]);break;
				case 'map_revision':$db->update('technicaldataset',['revision'=>99],['id = ?'=>$map['id']]);break;
				case 'profile_revision':$db->update('itemtechnicalprofile',['revision'=>99],['id = ?'=>$profile['id']]);break;
				case 'profile_header':$db->update('itemtechnicalprofile',['itemid'=>1072],['id = ?'=>$profile['id']]);break;
				case 'omitted_ref':case 'foreign_ref':
					$p=json_decode($root['payload_json']);if($case==='omitted_ref')array_pop($p->references);else $p->references[0]=(object)$foreign;
					$json=$encode($p);$target['sha256']=hash('sha256',$json);$db->update('technicaldataset',['payload_json'=>$json,'payload_sha256'=>$target['sha256']],['id = ?'=>$identity['id']]);break;
				case 'article':$db->update('item',['manufacturersku'=>'changed'],['id = ?'=>1071]);break;
				// Synthetic packets cannot be approved; simulate a persisted revocation only on this disposable server.
				case 'withdrawn_map':$db->update('technicaldataset',['status'=>'withdrawn'],['id = ?'=>$map['id']]);break;
				case 'withdrawn_component':$db->update('technicaldataset',['status'=>'withdrawn'],['id = ?'=>$spec['id']]);break;
			}
			if(in_array($case,['article','withdrawn_map','withdrawn_component'],true)) {
				$out=$evaluate($target,$request);$check($out['solution']===null && $out['convergence']['evaluations']===0 && in_array('machine_dependency_blocks_thermal_calculation',$out['reasons'],true),'Changed/withdrawn dependency evaluated');
			} else $reject(fn()=>$evaluate($target,$request),'Corrupt thermal graph '.$case);
		} finally {
			$db->update('technicaldataset',['payload_json'=>$mapRow['payload_json'],'revision'=>1,'status'=>'draft'],['id = ?'=>$map['id']]);
			$db->update('technicaldataset',['payload_json'=>$root['payload_json'],'payload_sha256'=>$root['payload_sha256']],['id = ?'=>$identity['id']]);
			$db->update('technicaldataset',['status'=>'draft'],['id = ?'=>$spec['id']]);
			$db->update('itemtechnicalprofile',['revision'=>1,'itemid'=>$profileRow['itemid']],['id = ?'=>$profile['id']]);
			$db->update('item',['manufacturersku'=>'SYNTHETIC-evaporator'],['id = ?'=>1071]);
		}
	}
	$next=json_decode($mapRow['payload_json'])->record;$next->revision=2;$next->supersedes=(object)['dataset_key'=>$next->dataset_key,'revision'=>1];$next->data->sections[0]->effectiveness='0.4';
	$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$next]]));
	$out=$evaluate($identity,$request);$check(abs((float)$out['solution']['point']->Te_degC-5.0)<1e-8 && $out['dependencies']['technicaldataset:fixture:thermal:db:air:evaporator-map@1']['revision']===1,'Implicit latest exchanger revision');
	$check($historical['machine']['sha256']===$identity['sha256'] && $historical['dependencies']['technicaldataset:fixture:thermal:db:air:evaporator-map@1']['status']==='draft','Historical thermal result mutated');
	technicalContext(20,200);$reject(fn()=>$evaluate($identity,$request),'Foreign owner');
	technicalContext(11,101,100);$check($evaluate($identity,$request)['convergence']['status']==='converged','Legitimate parent Items sharing blocked');
	technicalContext(11,101);$reject(fn()=>$evaluate($identity,$request),'Unshared child owner');technicalContext();
	$rights=$db->fetchOne('SELECT items FROM permission WHERE userid=10 AND deleted=0');
	try {
		$db->update('permission',['items'=>json_encode(['item'=>['view'],'technicaldataset'=>['view']])],['userid = ?'=>10]);
		$check($evaluate($identity,$request,'technical')['solution']===null,'Read-only technical review denied');$reject(fn()=>$evaluate($identity,$request),'Missing synthetic import/edit rights');
		$db->update('permission',['items'=>json_encode(['item'=>['view'],'technicaldataset'=>[]])],['userid = ?'=>10]);$reject(fn()=>$evaluate($identity,$request,'technical'),'Missing technical view rights');
	} finally {$db->update('permission',['items'=>$rights],['userid = ?'=>10]);}
	Zend_Registry::set('SiteContext',(object)[]);$reject(fn()=>$evaluate($identity,$request),'Site context');technicalContext();
	Zend_Auth::getInstance()->clearIdentity();$reject(fn()=>$evaluate($identity,$request),'Unauthenticated');technicalContext();
	foreach(['no_cp','no_flow','nonconvergence','real_machine','shared'] as $case) {
		$b=thermalBundle('air','fixture:thermal:blocked:'.$case);$request=thermalRequest();
		foreach($b->datasets as $r) { if($r->dataset_type==='heat_exchanger_map' && $r->data->role==='evaporator')$m=$r;if($r->dataset_type==='thermal_balance_model')$energy=$r;if($r->dataset_type==='machine_configuration')$config=$r; }
		switch($case) {
			case 'no_cp':$m->data->media[0]->properties->specific_heat_kJ_kg_K=null;break;
			case 'no_flow':$request->evaporator->volume_flow_m3_h=null;break;
			case 'nonconvergence':$energy->data->numerics->max_outer_iterations=1;break;
			case 'real_machine':unset($b->template_kind,$b->production_import_allowed,$b->provenance_note);foreach($b->datasets as $r)unset($r->import_context);break;
			case 'shared':$b=machineStageBundle(2,2,'fixture:thermal:blocked:shared');break;
		}
		[$blocked]=$import($b);$out=$evaluate($blocked,$request);$check($out['solution']===null && $out['status']==='review_required','Incomplete/nonconvergent/real/shared case yielded solution');
		if($case==='shared')$check(in_array('shared_exchanger_air_or_liquid_coupling_not_supported',$out['reasons'],true),'Shared topology blocker missing');
		if($case==='nonconvergence')$check($out['convergence']['status']==='outer_iteration_limit','Nonconvergence hidden behind last point');
	}
	echo "PASS: $count stored thermal assertions (synthetic air/water coupling, pinned map/profile graph, no real/synthetic release, tenant/rights, revisions/hash, revocation and read-only behavior)\n";
})();
