<?php

// Optional offline comparison: private coefficient files are never repository fixtures.
if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__,2);
foreach(['TechnicalJson','TechnicalSchema','CompressorPolynomial'] as $class) require_once $root.'/application/modules/items/services/'.$class.'.php';
$directory=isset($argv[1])?realpath($argv[1]):false;
if(!$directory || !is_dir($directory) || $directory===$root || str_starts_with($directory,$root.'/')) { fwrite(STDERR,"Supply the private D2A directory outside the repository\n");exit(1); }
$read=static function(string $name)use($directory){$path=$directory.'/'.$name;if(!is_file($path)||filesize($path)>4194304)throw new RuntimeException('Missing or oversized private reference');return Items_Service_TechnicalJson::decode(file_get_contents($path));};
$packages=$read('importvorlagen/02-polynomdatensaetze.json');$references=$read('importvorlagen/08-referenzbetriebspunkte.json');$checks=$read('pruefnachweise/polynom-pruefwerte.json');
$core=new Items_Service_CompressorPolynomial();$results=[];$maximum=[];$count=0;
foreach($packages->datasets as $package) {
	$result=$core->evaluate($package,(object)['Te_degC'=>'5','Tc_degC'=>'50','temperature_unit'=>'degC']);
	if($result['mathematical_status']!=='calculated' || $result['eligible']!==false)throw new RuntimeException('Invalid mathematical reference result');
	$results[$package->dataset_key]=[$package,$result];
}
foreach($references->records as $point) {
	$point=(new Items_Service_TechnicalSchema())->reference($point);
	[$package,$result]=$results[$point->dataset_ref->dataset_key];
	if($point->source->sha256!==$package->source->sha256 || (float)$point->inputs->Te_degC!==5.0 || (float)$point->inputs->Tc_degC!==50.0 || (int)$point->dataset_ref->revision!==(int)$package->revision)throw new RuntimeException('Private reference revision mismatch');
	$actual=$result['quantities'][$point->quantity];$expected=(float)$point->expected_value;
	$delta=abs($actual['value']-$expected);$tolerance=max((float)$point->absolute_tolerance,abs($expected)*(float)$point->relative_tolerance);
	if($actual['status']!=='calculated'||$actual['unit']!==$point->unit||$delta>$tolerance)throw new RuntimeException('Private quantity comparison failed');
	$maximum[$point->quantity]=max($maximum[$point->quantity]??0,$delta);$count++;
}
foreach($checks as $check) {
	$matches=array_filter($results,fn($pair)=>$pair[0]->data->model===$check->model && $pair[0]->source->sheet===$check->sheet);
	if(count($matches)!==1)throw new RuntimeException('Ambiguous private condition set');
	[$package,$result]=array_values($matches)[0];
	$q=$result['quantities']['cooling_capacity']['normalized']['value'];$p=$result['quantities']['power_input']['normalized']['value'];$cop=$result['compressor_cop']['value'];
	if(abs($q-(float)$check->Q_kW)>1e-8||abs($p-(float)$check->P_kW)>1e-8||abs($cop-(float)$check->COP)>5e-6)throw new RuntimeException('Private Q/P/COP comparison failed');
	if($package->source->sheet==='Standardbedingungen' && $result['quantities']['refrigerant_mass_flow']['status']!=='not_available')throw new RuntimeException('Standard mass flow was invented');
	echo json_encode(['model'=>$check->model,'condition_set'=>$check->sheet,'Q_kW'=>$q,'P_kW'=>$p,'compressor_COP'=>$cop,'technical_status'=>'not_assessed','eligible'=>false],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
}
echo json_encode(['status'=>'PASS','condition_sets'=>count($results),'quantity_comparisons'=>$count,'maximum_absolute_deviations_native_units'=>$maximum,'independent_manufacturer_acceptance'=>false,'database_used'=>false],JSON_THROW_ON_ERROR)."\n";
