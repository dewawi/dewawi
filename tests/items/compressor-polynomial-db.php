<?php

// Included by technical-data.php; the existing runner owns the disposable MariaDB.
(function() use ($service, $db, $encode, $evidence) {
	$count = 0;
	$check = function(bool $ok, string $message) use (&$count): void { $count++; if(!$ok) throw new RuntimeException($message); };
	$reject = function(callable $call, string $message, ?string $expected = null) use ($check): void {
		try { $call(); } catch(RuntimeException|InvalidArgumentException $e) {
			$check($expected === null || str_contains($e->getMessage(),$expected),$message.': unexpected error');
			$check(!str_contains($e->getMessage(),'fixture:d3') && !str_contains($e->getMessage(),'SYNTHETIC'),$message.': private identity leaked');
			return;
		}
		throw new RuntimeException('Expected rejection: '.$message);
	};
	$snapshot = fn() => [$db->fetchAll('SELECT * FROM technicaldataset ORDER BY id'),$db->fetchAll('SELECT * FROM itemtechnicalprofile ORDER BY id'),$db->fetchAll('SELECT * FROM item ORDER BY id'),$db->fetchAll('SELECT * FROM quote ORDER BY id'),$db->fetchAll('SELECT * FROM shopinquiryform ORDER BY id')];
	$evaluate = function(array $identity, stdClass $request, string $mode='technical') use ($service,$snapshot,$check): array {
		$before=$snapshot();$result=$service->evaluateCompressor($identity['id'],$identity['revision'],$identity['sha256'],$request,$mode);
		$check($snapshot()===$before,'A read-only calculation changed stored data');
		$check($result['eligible']===false && ($result['calculation']['eligible']??false)===false,'A compressor evaluation enabled machine/quote eligibility');
		return $result;
	};
	$record=technicalPolynomial();$record->dataset_key='fixture:d3:compressor';
	foreach(['cooling_capacity'=>'10000','power_input'=>'2000','current'=>'5','refrigerant_mass_flow'=>'100','discharge_temperature'=>'80'] as $q=>$value)$record->data->quantities->$q->coefficients_C0_to_C9=array_merge([$value],array_fill(0,9,'0'));
	$import = function(stdClass $record) use($service,$encode):array {
		$r=$service->importJson($encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$record]]));return $r['revisions']['technicaldataset:'.$record->dataset_key.'@'.$record->revision];
	};
	$context = fn($r) => (object)['manufacturer_name'=>$r->manufacturer_name,'manufacturer_revision'=>$r->manufacturer_revision,'model'=>$r->data->model,'refrigerant'=>$r->data->refrigerant,'conditions'=>clone $r->data->conditions,'temperature_basis'=>clone $r->data->temperature_basis,'electrical'=>clone $r->data->electrical];
	$point=(object)['Te_degC'=>'5','Tc_degC'=>'50','temperature_unit'=>'degC'];
	$request=(object)['point'=>$point,'context'=>$context($record)];
	$identity=$import($record);
	$draft=$evaluate($identity,$request);
	$check($draft['status']==='review_required' && $draft['calculation']===null && in_array('revision_not_approved',$draft['reasons'],true),'Technical use of a draft');
	$reference=$evaluate($identity,(object)['point'=>$point],'mathematical_reference');
	$check($reference['status']==='review_required' && $reference['calculation']['compressor_cop']['value']===5.0 && in_array('mathematical_reference_only',$reference['reasons'],true),'Authorized draft mathematical reference failed');
	$service->approve('technicaldataset',$identity['id'],$identity['sha256'],$evidence);
	$valid=$evaluate($identity,$request);
	$check($valid['status']==='valid_compressor_point' && $valid['calculation']['compressor_cop']['value']===5.0,'Complete approved synthetic point failed');
	$check($valid['package']['id']===$identity['id'] && $valid['package']['payload_sha256']===$identity['sha256'] && $valid['package']['approval_sha256']!==null,'Missing exact revision/approval evidence');
	$check(json_encode($valid,JSON_THROW_ON_ERROR)===json_encode($evaluate($identity,$request),JSON_THROW_ON_ERROR),'Repeated stored evaluation differs');
	foreach([[11,50],[5,61],[-11,50],[5,'1e308'],['10.00000000000000000001',50]] as [$te,$tc]) {
		$r=(object)['point'=>(object)['Te_degC'=>$te,'Tc_degC'=>$tc,'temperature_unit'=>'degC'],'context'=>$context($record)];
		$out=$evaluate($identity,$r);$check($out['status']==='unsuitable' && $out['calculation']===null && in_array('outside_operating_envelope',$out['reasons'],true),'Outside approved region was evaluated');
	}
	$precision=$evaluate($identity,(object)['point'=>(object)['Te_degC'=>'5.00000000000000000001','Tc_degC'=>'50','temperature_unit'=>'degC'],'context'=>$context($record)]);
	$check($precision['status']==='review_required' && $precision['calculation']===null && in_array('operating_point_precision',$precision['reasons'],true),'Silent temperature precision reduction');
	$outsideReference=$evaluate($identity,(object)['point'=>(object)['Te_degC'=>'11','Tc_degC'=>'50','temperature_unit'=>'degC']],'mathematical_reference');
	$check($outsideReference['calculation']===null,'Stored reference evaluation extrapolated outside a known region');
	$edge=$evaluate($identity,(object)['point'=>(object)['Te_degC'=>'10','Tc_degC'=>'60','temperature_unit'=>'degC'],'context'=>$context($record)]);
	$check($edge['status']==='valid_compressor_point','Exact envelope boundary not accepted');
	$noContext=$evaluate($identity,(object)['point'=>$point]);$check($noContext['calculation']===null && in_array('operating_context_required',$noContext['reasons'],true),'Technical context was guessed');
	foreach(['manufacturer_name','manufacturer_revision','model','refrigerant','speed_rpm','frequency_Hz','superheat_K','subcooling_K','temperature_basis','electrical'] as $field) {
		$c=$context($record);
		if(in_array($field,['speed_rpm','frequency_Hz','superheat_K','subcooling_K'],true))$c->conditions->$field='60';
		elseif($field==='temperature_basis')$c->temperature_basis->Te='dew';
		elseif($field==='electrical')$c->electrical->voltage_V='230';
		else $c->$field='different';
		$out=$evaluate($identity,(object)['point'=>$point,'context'=>$c]);$check($out['calculation']===null && $out['violations'],'Mismatched '.$field.' was silently adjusted');
	}
	$c=$context($record);$c->conditions->frequency_Hz='50.000';$equivalent=$evaluate($identity,(object)['point'=>$point,'context'=>$c]);
	$check($equivalent['status']==='valid_compressor_point','Equivalent exact decimal condition was mismatched');
	$c=$context($record);$c->conditions->frequency_Hz='60';$out=$evaluate($identity,(object)['point'=>$point,'context'=>$c],'mathematical_reference');
	$check($out['calculation']===null,'Reference mode silently changed source conditions');
	$missing=clone $record;$missing->data=clone $record->data;$missing->dataset_key='fixture:d3:missing';$missing->data_owner=null;$missing->data->operating_envelope=null;$missing->data->electrical=(object)['voltage_V'=>null,'phases'=>null,'execution_code'=>null];$missing->data->temperature_basis=(object)['Te'=>null,'Tc'=>null];
	$missingId=$import($missing);$out=$evaluate($missingId,(object)['point'=>$point],'mathematical_reference');
	foreach(['data_owner_missing','operating_envelope_missing','electrical_execution_missing','temperature_basis_missing'] as $reason)$check(in_array($reason,$out['reasons'],true),'Missing data blocker '.$reason);
	$check($out['calculation']['mathematical_status']==='calculated','Valid draft math was blocked by unknown engineering facts');
	$out=$evaluate($missingId,(object)['point'=>$point,'context'=>$context($missing)]);$check($out['calculation']===null && $out['status']==='review_required','Unknown manufacturer facts obtained technical suitability');
	// A nonzero-area self-intersection passes the old structural envelope check, not D3 use.
	$cross=clone $record;$cross->data=clone $record->data;$cross->dataset_key='fixture:d3:crossed';$cross->data->operating_envelope=(object)['kind'=>'te_tc_polygon','unit'=>'degC','source_locator'=>'synthetic malformed region','vertices'=>[(object)['Te'=>'-10','Tc'=>'30'],(object)['Te'=>'10','Tc'=>'60'],(object)['Te'=>'10','Tc'=>'30'],(object)['Te'=>'-10','Tc'=>'50']]];
	$crossId=$import($cross);$service->approve('technicaldataset',$crossId['id'],$crossId['sha256'],$evidence);
	$out=$evaluate($crossId,(object)['point'=>$point,'context'=>$context($cross)]);$check($out['status']==='review_required' && $out['calculation']===null,'Invalid approved polygon enabled technical use');
	$zero=clone $record;$zero->data=clone $record->data;$zero->data->quantities=clone $record->data->quantities;$zero->data->quantities->power_input=clone $record->data->quantities->power_input;$zero->dataset_key='fixture:d3:zero-power';$zero->data->quantities->power_input->coefficients_C0_to_C9=array_fill(0,10,'0');
	$zeroId=$import($zero);$service->approve('technicaldataset',$zeroId['id'],$zeroId['sha256'],$evidence);
	$out=$evaluate($zeroId,(object)['point'=>$point,'context'=>$context($zero)]);$check($out['status']==='review_required' && $out['calculation']['compressor_cop']['value']===null,'Zero power yielded a usable COP');
	$selected=clone $record;$selected->data=clone $record->data;$selected->data->conditions=clone $record->data->conditions;$selected->data->quantities=clone $record->data->quantities;
	$selected->dataset_key='fixture:d3:selected';$selected->data->condition_set='selected';$selected->data->conditions->superheat_K='8';$selected->data->conditions->subcooling_K='2';
	foreach(['cooling_capacity'=>'10','power_input'=>'2'] as $q=>$value){$selected->data->quantities->$q=clone $record->data->quantities->$q;$selected->data->quantities->$q->unit='kW';$selected->data->quantities->$q->coefficients_C0_to_C9=array_merge([$value],array_fill(0,9,'0'));}
	$selectedId=$import($selected);$service->approve('technicaldataset',$selectedId['id'],$selectedId['sha256'],$evidence);
	$out=$evaluate($selectedId,(object)['point'=>$point,'context'=>$context($selected)]);$check($out['status']==='valid_compressor_point' && $out['calculation']['compressor_cop']['value']===5.0 && $out['calculation']['quantities']['cooling_capacity']['unit']==='kW','Stored selected-set kW conditions failed');
	$out=$evaluate($selectedId,$request);$check($out['calculation']===null && in_array('conditions_mismatch',$out['reasons'],true),'Standard conditions were transferred to a selected set');
	$noMass=clone $record;$noMass->data=clone $record->data;$noMass->data->quantities=clone $record->data->quantities;$noMass->dataset_key='fixture:d3:no-mass';unset($noMass->data->quantities->refrigerant_mass_flow);
	$noMassId=$import($noMass);$service->approve('technicaldataset',$noMassId['id'],$noMassId['sha256'],$evidence);
	$out=$evaluate($noMassId,(object)['point'=>$point,'context'=>$context($noMass)]);$check($out['calculation']['quantities']['refrigerant_mass_flow']['status']==='not_available' && $out['calculation']['quantities']['refrigerant_mass_flow']['value']===null,'Stored missing mass flow was estimated');
	$badNumbers=clone $record;$badNumbers->data=clone $record->data;$badNumbers->data->quantities=clone $record->data->quantities;$badNumbers->data->quantities->cooling_capacity=clone $record->data->quantities->cooling_capacity;$badNumbers->dataset_key='fixture:d3:overflow';$badNumbers->data->quantities->cooling_capacity->coefficients_C0_to_C9=array_merge(['1e308'],array_fill(0,5,'0'),['1e308'],array_fill(0,3,'0'));
	$badId=$import($badNumbers);$service->approve('technicaldataset',$badId['id'],$badId['sha256'],$evidence);
	$out=$evaluate($badId,(object)['point'=>$point,'context'=>$context($badNumbers)]);$check($out['status']==='review_required' && $out['calculation']['quantities']['cooling_capacity']['value']===null && json_encode($out,JSON_THROW_ON_ERROR)!=='','Stored numeric overflow became usable');
	$oldPayload=$service->getById('technicaldataset',$identity['id'])['payload_json'];
	$next=clone $record;$next->data=clone $record->data;$next->data->quantities=clone $record->data->quantities;$next->data->quantities->cooling_capacity=clone $record->data->quantities->cooling_capacity;$next->revision=2;$next->supersedes=(object)['dataset_key'=>$record->dataset_key,'revision'=>1];$next->data->quantities->cooling_capacity->coefficients_C0_to_C9=array_merge(['12000'],array_fill(0,9,'0'));
	$nextId=$import($next);$service->approve('technicaldataset',$nextId['id'],$nextId['sha256'],$evidence);
	$check($evaluate($identity,$request)['calculation']['compressor_cop']['value']===5.0 && $evaluate($nextId,$request)['calculation']['compressor_cop']['value']===6.0,'Exact revisions were mixed or latest was selected implicitly');
	$check($service->getById('technicaldataset',$identity['id'])['payload_json']===$oldPayload,'Original payload changed after new revision');
	$reject(fn()=>$service->evaluateCompressor($identity['id'],2,$identity['sha256'],$request),'Wrong revision','unavailable');
	$reject(fn()=>$service->evaluateCompressor($identity['id'],1,str_repeat('0',64),$request),'Wrong expected hash','unavailable');
	$row=$service->getById('technicaldataset',$identity['id']);
	try {
		$db->update('technicaldataset',['payload_json'=>$row['payload_json'].' '],['id = ?'=>$identity['id']]);
		$reject(fn()=>$service->evaluateCompressor($identity['id'],1,$identity['sha256'],$request),'Tampered payload','hash mismatch');
	} finally {$db->update('technicaldataset',['payload_json'=>$row['payload_json']],['id = ?'=>$identity['id']]);}
	try {
		$db->update('technicaldataset',['revision'=>999],['id = ?'=>$identity['id']]);
		$reject(fn()=>$service->evaluateCompressor($identity['id'],999,$identity['sha256'],$request),'Tampered revision header','revision integrity');
	} finally {$db->update('technicaldataset',['revision'=>1],['id = ?'=>$identity['id']]);}
	try {
		$badProof=json_decode($row['approval_json']);$badProof->revision_sha256=str_repeat('0',64);$db->update('technicaldataset',['approval_json'=>json_encode($badProof,JSON_THROW_ON_ERROR)],['id = ?'=>$identity['id']]);
		$reject(fn()=>$service->evaluateCompressor($identity['id'],1,$identity['sha256'],$request),'Mismatched approval proof','approval integrity');
	} finally {$db->update('technicaldataset',['approval_json'=>$row['approval_json']],['id = ?'=>$identity['id']]);}
	technicalContext(20,200);$reject(fn()=>$service->evaluateCompressor($identity['id'],1,$identity['sha256'],$request),'Foreign owner','unavailable');
	technicalContext(11,101,100);$check($evaluate($identity,$request)['status']==='valid_compressor_point','Authorized parent Items sharing broke');
	technicalContext(11,101);$reject(fn()=>$service->evaluateCompressor($identity['id'],1,$identity['sha256'],$request),'Unshared child owner','unavailable');technicalContext();
	$rights=$db->fetchOne('SELECT items FROM permission WHERE userid=10 AND deleted=0');
	try {
		$db->update('permission',['items'=>json_encode(['item'=>['view'],'technicaldataset'=>['view']])],['userid = ?'=>10]);
		$check($evaluate($identity,$request)['status']==='valid_compressor_point','Read-only technical use was blocked');
		$reject(fn()=>$service->evaluateCompressor($identity['id'],1,$identity['sha256'],$request,'mathematical_reference'),'Reference requires private import rights','permission denied');
		$db->update('permission',['items'=>json_encode(['item'=>['view'],'technicaldataset'=>[]])],['userid = ?'=>10]);
		$reject(fn()=>$service->evaluateCompressor($identity['id'],1,$identity['sha256'],$request),'Missing read right','permission denied');
	} finally {$db->update('permission',['items'=>$rights],['userid = ?'=>10]);}
	$service->withdraw('technicaldataset',$identity['id'],$identity['sha256'],'Synthetic withdrawal');
	foreach(['technical','mathematical_reference'] as $mode) { $out=$evaluate($identity,$request,$mode);$check($out['calculation']===null && in_array('revision_withdrawn',$out['reasons'],true),'Withdrawn revision was evaluated'); }
	$check($evaluate($nextId,$request)['status']==='valid_compressor_point','Withdrawal of old revision affected new revision');
	echo "PASS: $count stored compressor evaluation assertions (D2B rights, hash/revision/approval integrity, lifecycle, region/context gates and read-only behavior)\n";
})();
