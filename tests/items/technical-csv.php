<?php

// Included by technical-data.php with synthetic fixtures and a disposable database.
$ctx=(object)['format'=>'dewawi-technical-csv-context','format_version'=>1,'bundle'=>technicalBundle(),'sources'=>(object)['fixture:source'=>technicalSource()],'component_datasets'=>(object)['fixture:compressor'=>(object)['dataset_key'=>'fixture:compressor','revision'=>1],'fixture:evaporator'=>(object)['dataset_key'=>'fixture:evaporator','revision'=>1],'fixture:pump'=>(object)['dataset_key'=>'fixture:pump','revision'=>1],'fixture:tank'=>(object)['dataset_key'=>'fixture:tank','revision'=>1]]];
$r=$csvSource+['component_key'=>'fixture:compressor','item_ref'=>'fixture:compressor','component_type'=>'compressor','manufacturer_ref'=>'fixture:manufacturer','model'=>'SYNTHETIC-COMPRESSOR','manufacturersku'=>'SYNTHETIC-compressor','electrical_execution_code'=>'SYNTHETIC-400-3','voltage_V'=>'400','phases'=>'3','frequency_Hz'=>'50','speed_rpm'=>'2900'];
$output=$adapter->csv('01-komponentenartikel',technicalCsv('01-komponentenartikel',[$r]),$encode($ctx));
checkTechnical(count($service->validateJson($output)->profiles)===7,'Component CSV crosswalk adapter');
$r['frequency_Hz']='60';rejectsTechnical(fn()=>$adapter->csv('01-komponentenartikel',technicalCsv('01-komponentenartikel',[$r]),$encode($ctx)),'CSV cannot silently replace frequency');
$r=$csvSource+['machine_dataset_key'=>'fixture:machine:2','machine_revision'=>'1','circuit_key'=>'r1','refrigerant'=>'fixture-fluid','topology_kind'=>'parallel_compressors_single_evaporator_single_condenser_v1','instance_key'=>'r1.compressor1','component_key'=>'fixture:compressor','profile_key'=>'fixture:profile:compressor','profile_revision'=>'1','suction_group'=>'suction','discharge_group'=>'discharge','evaporator_instance'=>'evap','evaporator_section'=>'r1','condenser_instance'=>'cond.r1','expansion_device_ref'=>'exp.r1'];
$output=$adapter->csv('04-kreislauf-verdichterzuordnungen',technicalCsv('04-kreislauf-verdichterzuordnungen',[$r]),$encode($ctx));
checkTechnical($service->validateJson($output)->datasets[7]->data->circuit_count===2,'Complete CSV circuit view');
$r['profile_revision']='1garbage';rejectsTechnical(fn()=>$adapter->csv('04-kreislauf-verdichterzuordnungen',technicalCsv('04-kreislauf-verdichterzuordnungen',[$r]),$encode($ctx)),'CSV profile revision must not be coerced');
$hx=[];
foreach(['r1','r2'] as $section)$hx[]=$csvSource+['component_key'=>'fixture:evaporator','profile_key'=>'fixture:profile:evaporator','profile_revision'=>'1','role'=>'evaporator','exchanger_type'=>'plate','refrigerant_section_key'=>$section,'refrigerant'=>'fixture-fluid'];
$output=$adapter->csv('05-waermetauscher',technicalCsv('05-waermetauscher',$hx),$encode($ctx));
checkTechnical($service->validateJson($output)->datasets[2]->data->specification->sections===['r1','r2'],'Heat exchanger CSV lost shared sections');
$r=$csvSource+['component_key'=>'fixture:pump','profile_key'=>'fixture:profile:pump','profile_revision'=>'1','loop_key'=>'load','speed_rpm'=>'2900','frequency_Hz'=>'50','pressure_curve_definition'=>'gross','tank_kind'=>'pressurized','usable_volume_L'=>'80'];
$output=$adapter->csv('06-pumpen-hydraulik',technicalCsv('06-pumpen-hydraulik',[$r]),$encode($ctx));
checkTechnical($service->validateJson($output)->datasets[4]->data->specification->curve_definition==='gross','Pump CSV with exact hydraulic context');
$r['usable_volume_L']='100';rejectsTechnical(fn()=>$adapter->csv('06-pumpen-hydraulik',technicalCsv('06-pumpen-hydraulik',[$r]),$encode($ctx)),'CSV must not replace usable volume with nominal volume');
$ctx->bundle->datasets[7]->data->variant_signature->extra_option_refs=['fixture:included'];
$ctx->bundle->datasets[7]->data->selected_equipment=[(object)['option_ref'=>'fixture:included','component_key'=>'fixture:tank','technical_effect'=>'neutral_verified','target_instance_key'=>'tank','replacement_component_key'=>null,'effect_dataset_ref'=>null,'source_locator'=>'fixture:v1','included'=>false]];
$r=$csvSource+['option_ref'=>'fixture:included','base_item_ref'=>'fixture:base','option_group_ref'=>'fixture:group','component_item_ref'=>'fixture:tank','component_key'=>'fixture:tank','technical_effect'=>'neutral_verified','target_instance_key'=>'tank','action'=>'neutral_verified','included_in_variant'=>'false','variant_item_ref'=>'fixture:machine'];
$output=$adapter->csv('07-optionen-zubehoer',technicalCsv('07-optionen-zubehoer',[$r]),$encode($ctx));
checkTechnical($service->validateJson($output)->datasets[7]->data->selected_equipment[0]->option_group_ref==='fixture:group','CSV option identities were lost');
$r=$csvSource+['reference_key'=>'fixture:csv-point','dataset_key'=>'fixture:compressor','dataset_revision'=>'1','mode'=>'cooling','reference_kind'=>'mathematical_polynomial','Te_degC'=>'5','Tc_degC'=>'50','quantity'=>'cooling_capacity','expected_value'=>'1.234567890123456789','unit'=>'W','absolute_tolerance'=>'0.00001'];
$output=$adapter->csv('08-referenzbetriebspunkte',technicalCsv('08-referenzbetriebspunkte',[$r]),$encode($csvContext));
checkTechnical($service->validateJson($output)->datasets[0]->data->records[0]->expected_value==='1.234567890123456789','CSV reference value lost decimal precision');
rejectsTechnical(fn()=>$adapter->csv('08-referenzbetriebspunkte',technicalCsv('08-referenzbetriebspunkte',[$r,$r]),$encode($csvContext)),'Duplicate reference CSV row');
rejectsTechnical(fn()=>$service->importJson($encode($changed),$mapping,true),'Dry-run must detect immutable conflicts');
$b=technicalBundle();$b->datasets[0]->data->title=str_repeat('x',1048577);
rejectsTechnical(fn()=>$service->validateJson($encode($b)),'1MiB individual payload budget');
// A completely disconnected hydraulic subcycle is rejected even if every port exists.
$b=technicalBundle();$b->datasets[7]->data->hydraulic_loops[0]->edges=[['return_water','tank.in'],['tank.out','supply_water'],['pump.out','pump.in'],['evap.water_out','evap.water_in']];
rejectsTechnical(fn()=>$service->validateJson($encode($b)),'Disconnected hydraulic subcycle');

$b=technicalBundle();$b->datasets[8]->data->records[0]->dataset_ref->dataset_key='fixture:family';
rejectsTechnical(fn()=>$service->importJson($encode($b),$mapping),'Mathematical reference cannot target family data');
$db->update('user',['activated'=>0],['id = ?'=>10]);
rejectsTechnical(fn()=>$service->getById('technicaldataset',$id),'Persisted disabled account cannot use cached registry credentials');
$db->update('user',['activated'=>1],['id = ?'=>10]);

$restricted=technicalFamily();$restricted->dataset_key='fixture:restricted-source';
$restrictedBundle=(object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$restricted],'provenance_note'=>'Synthetic conflict evidence, kept immutable','production_import_allowed'=>false];
$imported=$service->importJson($encode($restrictedBundle));$restrictedId=$imported['revisions']['technicaldataset:fixture:restricted-source@1']['id'];
$restrictedRow=$service->getById('technicaldataset',$restrictedId);
checkTechnical(str_contains($restrictedRow['payload_json'],'Synthetic conflict evidence'),'Root provenance was dropped');
rejectsTechnical(fn()=>$service->approve('technicaldataset',$restrictedId,$restrictedRow['payload_sha256'],$evidence),'An explicitly restricted source cannot be approved');

$db->insert('client',['id'=>0,'company'=>'Forbidden global fixture']);$db->update('user',['admin'=>1],['id = ?'=>10]);
technicalContext(10,0);
rejectsTechnical(fn()=>$service->getById('technicaldataset',$id),'Even an admin cannot use global client zero for technical data');
$db->update('user',['admin'=>0],['id = ?'=>10]);technicalContext();

// Exercise the actual private CLI, not just its underlying service.
function runTechnicalCli(array $arguments):array {
	global $root;
	$process=proc_open(array_merge([PHP_BINARY,$root.'/tools/technical-import.php'],$arguments),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
	if(!is_resource($process))throw new RuntimeException('Cannot launch private CLI');
	fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return ['exit'=>proc_close($process),'out'=>$out,'err'=>$err];
}
$cliFamily=technicalFamily();$cliFamily->dataset_key='fixture:cli';
file_put_contents($work.'/cli.json',$encode((object)['format'=>'dewawi-technical-import','format_version'=>1,'datasets'=>[$cliFamily]]));
$command=['--file',$work.'/cli.json','--socket',$argv[1],'--database','technical_fresh','--db-user','root','--user','10','--client','100'];
$cli=runTechnicalCli($command);
checkTechnical($cli['exit']===0 && json_decode($cli['out'],true)['dry_run']===true && (int)$db->fetchOne("SELECT COUNT(*) FROM technicaldataset WHERE dataset_key='fixture:cli'")===0,'CLI dry-run wrote data: '.$cli['err']);
$cli=runTechnicalCli(array_merge($command,['--write']));
checkTechnical($cli['exit']===0 && (int)$db->fetchOne("SELECT COUNT(*) FROM technicaldataset WHERE dataset_key='fixture:cli' AND status='draft'")===1,'Explicit CLI write failed: '.$cli['err']);
$denied=$command;$denied[array_search('--user',$denied,true)+1]='20';$cli=runTechnicalCli(array_merge($denied,['--write']));
checkTechnical($cli['exit']===1,'CLI accepted an actor outside the owner scope');
file_put_contents($work.'/cli-points.json',$encode($rp));
$cli=runTechnicalCli(['--file',$work.'/cli-points.json','--references','fixture:cli-points','--revision','1invalid','--validate']);
checkTechnical($cli['exit']===1 && str_contains($cli['err'],'Invalid reference revision'),'CLI coerced a manipulated reference revision');
$cli=runTechnicalCli(['--file',$work.'/cli-points.json','--references','fixture:cli-points','--revision','2','--validate']);
checkTechnical($cli['exit']===0 && json_decode($cli['out'],true)['validation_only']===true,'Offline CLI reference adapter failed');

$ownerContext=clone $csvContext;$ownerContext->data_owner='Explicit synthetic CSV owner';
$ownerOutput=$adapter->csv('03-maschinenfamilien-gehaeuse',technicalCsv('03-maschinenfamilien-gehaeuse',[$familyCsv]),$encode($ownerContext));
checkTechnical($service->validateJson($ownerOutput)->datasets[0]->data_owner==='Explicit synthetic CSV owner','CSV context dropped the explicit data owner');
