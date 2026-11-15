<?php

// Included by technical-data.php; no other database is loaded or used.
require_once __DIR__.'/fixtures/thermal-machine.php';
(function() use ($service,$db,$encode,$evidence,$mapping) {
	$count=0;$check=function(bool $ok,string $message)use(&$count):void{$count++;if(!$ok)throw new RuntimeException($message);};
	$reject=function(callable $work,string $message)use($check):void{try{$work();}catch(RuntimeException|InvalidArgumentException $e){$check(!str_contains($e->getMessage(),'fixture:parallel')&&!str_contains($e->getMessage(),'SYNTHETIC'),$message.': private identity leaked');return;}throw new RuntimeException('Expected rejection: '.$message);};
	$clone=fn($v)=>unserialize(serialize($v));
	$snapshot=function()use($db):array{$rows=[];foreach(['technicaldataset','itemtechnicalprofile','item','itemvariantopt','quote','quotepos','shopinquiryform','shopinquirydata'] as $t)$rows[$t]=$db->fetchAll('SELECT * FROM '.$t.' ORDER BY id');return $rows;};
	$evaluate=function(array $identity,stdClass $request,string $mode='synthetic_reference')use($service,$snapshot,$check):array{
		$before=$snapshot();$r=$service->evaluateThermalMachine($identity['id'],$identity['revision'],$identity['sha256'],$request,$mode);
		$check($snapshot()===$before,'Parallel calculation changed stored data');$check($r['eligible']===false&&$r['useful_machine_cooling_capacity']['value']===null&&$r['machine_efficiency']['value']===null,'Shared calculation released machine');return $r;
	};
	technicalContext();
	foreach(['compressor','evaporator','condenser','base','machine','pump','tank','fan','compressor-b'] as $i=>$name){$id=1100+$i;$mapping['items']['fixture:'.$name]=$id;$db->insert('item',['id'=>$id,'parentid'=>$name==='machine'?1103:null,'catid'=>0,'shopid'=>0,'clientid'=>100,'sku'=>'SYNTHETIC-'.$name,'manufacturersku'=>'SYNTHETIC-'.$name]);}
	$import=function($bundle)use($service,$encode,$mapping):array{$saved=$service->importJson($encode($bundle),$mapping)['revisions'];foreach($saved as $key=>$identity)if(str_contains($key,':machine:'))return [$identity,$saved];throw new RuntimeException('Missing synthetic machine');};
	$find=function($bundle,$suffix){foreach($bundle->datasets as $r)if(str_ends_with($r->dataset_key,$suffix))return $r;throw new RuntimeException('Missing fixture');};
	foreach(['independent','shared_evaporator','shared_condenser','both_shared'] as $kind){
		$prefix='fixture:parallel:db:'.$kind;$bundle=thermalMachineBundle($kind,$prefix);[$identity,$saved]=$import($bundle);$request=thermalMachineRequest($bundle);$r=$evaluate($identity,$request);$s=$r['solution'];
		$check($r['convergence']['status']==='converged'&&abs((float)$s['circuits']['r1']['point']->Te_degC-5.0)<1e-8&&abs((float)$s['circuits']['r2']['point']->Te_degC-2.5)<1e-8,'Stored independent circuit roots');
		$check(abs($s['thermal_totals']['compressor_cooling_kW']-59.4)<1e-7&&abs($s['thermal_totals']['compressor_power_kW']-14.225)<1e-7&&abs($s['thermal_totals']['condenser_heat_kW']-73.625)<1e-7,'Stored independently specified balances');
		$check($r['machine']['article']->id===1104&&$r['machine']['sha256']===$identity['sha256'],'Exact machine article evidence');
		foreach($r['dependencies'] as $key=>$dependency)$check($saved[$key]['sha256']===$dependency['sha256']&&$saved[$key]['revision']===$dependency['revision'],'Exact shared graph revision/hash');
		foreach($s['secondary_groups'] as $g)$check($g['balance']['volume_flow_residual_m3_h']==='0'&&max(array_map('abs',$g['balance']['secondary_heat_residual_enclosure_kW']))<1e-7,'Stored secondary conservation');
		$check(json_encode($r,JSON_THROW_ON_ERROR)===json_encode($evaluate($identity,$request),JSON_THROW_ON_ERROR),'Stored shared evaluation not deterministic');
		if($kind==='both_shared'){$main=$identity;$mainSaved=$saved;$mainBundle=$bundle;$historical=$r;}
	}
	$legacySections=thermalMachineBundle('independent','fixture:parallel:legacy_sections');
	foreach($legacySections->datasets as $record){if($record->dataset_type==='heat_exchanger_map'&&$record->data->role==='water_condenser')$record->data->sections[0]->section_key='rated';if($record->dataset_type==='component_spec'&&$record->data->role==='water_condenser')$record->data->specification->sections=['rated'];if($record->dataset_type==='thermal_balance_model'&&$record->data->contract_version===2)foreach($record->data->secondary_groups as $g)if($g->side==='condenser')$g->branches[0]->section_key='rated';}
	[$legacyIdentity]=$import($legacySections);$check($evaluate($legacyIdentity,thermalMachineRequest($legacySections))['convergence']['status']==='converged','Stored non-main condenser section compatibility');
	$identity=$main;$saved=$mainSaved;$bundle=$mainBundle;$request=thermalMachineRequest($bundle);
	foreach(range(1,4) as $stage){$r=$evaluate($identity,thermalMachineRequest($bundle,'stage'.$stage));$check($r['convergence']['status']==='converged'&&count(array_filter($r['solution']['compressors'],fn($c)=>$c['active']))===$stage,'Stored selected-stage thermal activation');}
	$out=$evaluate($identity,$request,'technical');$check($out['solution']===null&&in_array('released_real_thermal_model_not_available',$out['reasons'],true),'Technical mode published synthetic machine');
	$prefix='fixture:parallel:db:both_shared';$model=$saved['technicaldataset:'.$prefix.':parallel-model@1'];$map=$saved['technicaldataset:'.$prefix.':evaporator-map@1'];$inactiveProfile=$saved['itemtechnicalprofile:'.$prefix.':profile:compressor-b@1'];
	foreach([$identity,$model,$map] as $target)$reject(fn()=>$service->approve('technicaldataset',$target['id'],$target['sha256'],$evidence),'Synthetic shared machine/model/map approval');
	$reject(fn()=>$service->evaluateThermalMachine($identity['id'],2,$identity['sha256'],$request),'Wrong root revision');
	$reject(fn()=>$service->evaluateThermalMachine($identity['id'],1,str_repeat('0',64),$request),'Wrong root hash');
	$contextSource=$find($bundle,':compressor');$context=(object)['manufacturer_name'=>$contextSource->manufacturer_name,'manufacturer_revision'=>$contextSource->manufacturer_revision,'model'=>$contextSource->data->model,'refrigerant'=>$contextSource->data->refrigerant,'conditions'=>$clone($contextSource->data->conditions),'temperature_basis'=>$contextSource->data->temperature_basis,'electrical'=>$contextSource->data->electrical];$context->conditions->frequency_Hz='60';
	$wrong=$clone($request);$wrong->compressor_contexts=(object)['r1.compressor1'=>$context];$out=$evaluate($identity,$wrong);$check($out['solution']===null&&$out['status']==='unsuitable'&&in_array('r1:r1.compressor1:conditions_mismatch',$out['reasons'],true),'Shared thermal context bypassed D3');
	foreach(['total','tiny_total','missing_branch','unallocated_section','section','map_section','wrong_circuit_model','ports','nonisolated','serial','loop','wrong_profile'] as $case){
		$b=thermalMachineBundle('both_shared','fixture:parallel:invalid:'.$case);$m=$find($b,':parallel-model')->data;$config=$find($b,':machine:2')->data;
		switch($case){
			case 'total':$m->secondary_groups[0]->total_volume_flow_m3_h='20';break;
			case 'tiny_total':$m->secondary_groups[0]->total_volume_flow_m3_h='19.620000000000000000001';break;
			case 'missing_branch':array_pop($m->secondary_groups[0]->branches);break;
			case 'unallocated_section':$extraMap=$find($b,':evaporator-map');$extra=$clone($extraMap->data->sections[0]);$extra->section_key='spare';$extraMap->data->sections[]=$extra;$find($b,':evaporator')->data->specification->sections[]='spare';break;
			case 'section':$m->secondary_groups[0]->branches[1]->section_key='r1';break;
			case 'map_section':$find($b,':condenser-map')->data->sections[1]->section_key='other';break;
			case 'wrong_circuit_model':$find($b,':circuit-model:r2')->data->circuit_key='r1';break;
			case 'ports':$config->circuits[1]->condenser_section_key='r1';break;
			case 'nonisolated':foreach($config->shared_components as $s)if($s->instance_key==='cond')$s->refrigerant_sections_are_isolated=false;break;
			case 'serial':$m->secondary_groups[0]->topology_kind='serial_sections';break;
			case 'loop':$m->secondary_groups[1]->hydraulic_loop_key='load';break;
			case 'wrong_profile':$config->components[0]->profile_revision=99;break;
		}
		$reject(fn()=>$import($b),'Invalid shared import '.$case);
	}
	$root=$service->getById('technicaldataset',$identity['id']);$mapRow=$service->getById('technicaldataset',$map['id']);
	technicalContext(20,200);$foreignRecord=technicalPolynomial();$foreignRecord->dataset_key='fixture:parallel:foreign';$foreign=$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$foreignRecord]]))['revisions']['technicaldataset:fixture:parallel:foreign@1'];technicalContext();
	foreach(['hash','revision','omitted_ref','foreign_ref','article','withdrawn_inactive','withdrawn_map'] as $case){
		$target=$identity;
		try{
			switch($case){
				case 'hash':$db->update('technicaldataset',['payload_json'=>$mapRow['payload_json'].' '],['id = ?'=>$map['id']]);break;
				case 'revision':$db->update('technicaldataset',['revision'=>99],['id = ?'=>$map['id']]);break;
				case 'omitted_ref':case 'foreign_ref':$p=json_decode($root['payload_json']);if($case==='omitted_ref')array_pop($p->references);else $p->references[0]=(object)$foreign;$json=$encode($p);$target['sha256']=hash('sha256',$json);$db->update('technicaldataset',['payload_json'=>$json,'payload_sha256'=>$target['sha256']],['id = ?'=>$identity['id']]);break;
				case 'article':$db->update('item',['manufacturersku'=>'changed'],['id = ?'=>1101]);break;
				// Synthetic packets cannot be approved; revocation states exist only in this disposable negative fixture.
				case 'withdrawn_inactive':$db->update('itemtechnicalprofile',['status'=>'withdrawn'],['id = ?'=>$inactiveProfile['id']]);break;
				case 'withdrawn_map':$db->update('technicaldataset',['status'=>'withdrawn'],['id = ?'=>$map['id']]);break;
			}
			if(in_array($case,['article','withdrawn_inactive','withdrawn_map'],true)){$out=$evaluate($target,thermalMachineRequest($bundle,'stage1'));$check($out['solution']===null&&$out['convergence']['evaluations']===0&&in_array('machine_dependency_blocks_thermal_calculation',$out['reasons'],true),'Inactive/changed dependency bypassed full graph checks');}
			else $reject(fn()=>$evaluate($target,$request),'Corrupt shared dependency '.$case);
		}finally{
			$db->update('technicaldataset',['payload_json'=>$mapRow['payload_json'],'revision'=>1,'status'=>'draft'],['id = ?'=>$map['id']]);$db->update('technicaldataset',['payload_json'=>$root['payload_json'],'payload_sha256'=>$root['payload_sha256']],['id = ?'=>$identity['id']]);
			$db->update('itemtechnicalprofile',['status'=>'draft'],['id = ?'=>$inactiveProfile['id']]);$db->update('item',['manufacturersku'=>'SYNTHETIC-evaporator'],['id = ?'=>1101]);
		}
	}
	$next=json_decode($mapRow['payload_json'])->record;$next->revision=2;$next->supersedes=(object)['dataset_key'=>$next->dataset_key,'revision'=>1];$next->data->sections[0]->effectiveness='0.6';$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$next]]));
	$out=$evaluate($identity,$request);$check(abs((float)$out['solution']['circuits']['r1']['point']->Te_degC-5.0)<1e-8&&$out['dependencies']['technicaldataset:'.$prefix.':evaporator-map@1']['revision']===1,'Implicit latest shared section map');
	$check($historical['machine']['sha256']===$identity['sha256']&&$historical['dependencies']['technicaldataset:'.$prefix.':evaporator-map@1']['status']==='draft','Historical shared result changed');
	technicalContext(20,200);$reject(fn()=>$evaluate($identity,$request),'Foreign owner');technicalContext(11,101,100);$check($evaluate($identity,$request)['convergence']['status']==='converged','Legitimate parent sharing');technicalContext(11,101);$reject(fn()=>$evaluate($identity,$request),'Unshared child owner');technicalContext();
	$rights=$db->fetchOne('SELECT items FROM permission WHERE userid=10 AND deleted=0');
	try{$db->update('permission',['items'=>json_encode(['item'=>['view'],'technicaldataset'=>['view']])],['userid = ?'=>10]);$check($evaluate($identity,$request,'technical')['solution']===null,'Technical review denied');$reject(fn()=>$evaluate($identity,$request),'Missing synthetic import/edit rights');$db->update('permission',['items'=>json_encode(['item'=>['view'],'technicaldataset'=>[]])],['userid = ?'=>10]);$reject(fn()=>$evaluate($identity,$request,'technical'),'Missing view rights');}finally{$db->update('permission',['items'=>$rights],['userid = ?'=>10]);}
	Zend_Registry::set('SiteContext',(object)[]);$reject(fn()=>$evaluate($identity,$request),'Public site context');technicalContext();Zend_Auth::getInstance()->clearIdentity();$reject(fn()=>$evaluate($identity,$request),'Missing identity');technicalContext();
	foreach(['split','no_map','nonconvergence','real'] as $case){$b=thermalMachineBundle('both_shared','fixture:parallel:blocked:'.$case);switch($case){case 'split':$find($b,':parallel-model')->data->secondary_groups[0]->branches[1]->volume_flow_m3_h=null;break;case 'no_map':$find($b,':evaporator')->data->specification->thermal_map_ref=null;break;case 'nonconvergence':$find($b,':circuit-model:r2')->data->numerics->max_outer_iterations=1;break;case 'real':unset($b->template_kind,$b->production_import_allowed,$b->provenance_note);foreach($b->datasets as $r)unset($r->import_context);break;}[$blocked]=$import($b);$out=$evaluate($blocked,thermalMachineRequest($b));$check($out['solution']===null&&$out['status']==='review_required','Incomplete shared case yielded solution');}
	echo "PASS: $count stored parallel thermal assertions (shared/independent profiles/maps, 4/2 balances, activation, tenant/rights, graph hashes/revisions, inactive withdrawal and immutable reads)\n";
})();
