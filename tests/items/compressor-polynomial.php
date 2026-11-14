<?php

if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
foreach(['TechnicalJson', 'TechnicalSchema', 'CompressorPolynomial'] as $class) require_once $root.'/application/modules/items/services/'.$class.'.php';
require_once __DIR__.'/fixtures/technical.php';
$d3Checks = 0;
function checkPolynomial(bool $value, string $message): void { global $d3Checks; $d3Checks++; if(!$value) throw new RuntimeException($message); }
function rejectsPolynomial(callable $work, string $message): void {
	try { $work(); } catch(Throwable $e) { checkPolynomial(true, $message); return; }
	throw new RuntimeException('Expected rejection: '.$message);
}
function polynomialFixture(): stdClass {
	$r = technicalPolynomial();
	foreach(['cooling_capacity'=>'10000','power_input'=>'2000','current'=>'5','refrigerant_mass_flow'=>'100','discharge_temperature'=>'80'] as $q=>$value) $r->data->quantities->$q->coefficients_C0_to_C9 = array_merge([$value], array_fill(0,9,'0'));
	return $r;
}
$core = new Items_Service_CompressorPolynomial();
$point = (object)['Te_degC'=>'2','Tc_degC'=>'5','temperature_unit'=>'degC'];
$expected = [1,2,5,4,10,25,8,20,50,125];
foreach($expected as $index=>$value) {
	$r = polynomialFixture(); $r->data->quantities->cooling_capacity->coefficients_C0_to_C9 = array_fill(0,10,'0'); $r->data->quantities->cooling_capacity->coefficients_C0_to_C9[$index]='1';
	$result=$core->evaluate($r,$point);
	checkPolynomial($result['quantities']['cooling_capacity']['value']===(float)$value,'Wrong C'.$index.' term');
}
$r=polynomialFixture();$r->data->quantities->cooling_capacity->coefficients_C0_to_C9=['1e16','0.5','-2e15','0','0','0','0','0','0','0'];
checkPolynomial($core->evaluate($r,$point)['quantities']['cooling_capacity']['value']===1.0,'Cancellation compensation lost the small term');
$r=polynomialFixture();$baseline=Items_Service_TechnicalJson::encode($r);$first=$core->evaluate($r,$point);
checkPolynomial(json_encode($first,JSON_THROW_ON_ERROR)===json_encode($core->evaluate($r,$point),JSON_THROW_ON_ERROR) && Items_Service_TechnicalJson::encode($r)===$baseline,'Evaluation changed the dataset or was nondeterministic');
checkPolynomial($first['compressor_cop']['value']===5.0 && $first['eligible']===false && $first['technical_status']==='not_assessed','COP or math-only contract');
$r->data->quantities->cooling_capacity->unit='kW';$r->data->quantities->cooling_capacity->coefficients_C0_to_C9[0]='10';
checkPolynomial($core->evaluate($r,$point)['compressor_cop']['value']===5.0,'Mixed documented W/kW units were not normalized');
$r->data->quantities->power_input->unit='kW';$r->data->quantities->power_input->coefficients_C0_to_C9[0]='2';
checkPolynomial($core->evaluate($r,$point)['compressor_cop']['value']===5.0,'kW/kW normalization failed');
unset($r->data->quantities->refrigerant_mass_flow);
$missing=$core->evaluate($r,$point)['quantities']['refrigerant_mass_flow'];
checkPolynomial($missing['status']==='not_available' && $missing['value']===null,'Missing mass flow became a numeric zero');
foreach(['cooling_capacity','power_input','current','refrigerant_mass_flow'] as $quantity) foreach(['0','-1'] as $value) {
	$r=polynomialFixture();$r->data->quantities->$quantity->coefficients_C0_to_C9[0]=$value;$result=$core->evaluate($r,$point);
	checkPolynomial($result['mathematical_status']==='invalid_result','Nonphysical '.$quantity.' accepted');
	if(in_array($quantity,['cooling_capacity','power_input'],true)) checkPolynomial($result['compressor_cop']['value']===null,'COP derived from nonpositive power/capacity');
}
$r=polynomialFixture();$r->data->quantities->discharge_temperature->coefficients_C0_to_C9[0]='-273.15';
checkPolynomial($core->evaluate($r,$point)['mathematical_status']==='invalid_result','Absolute-zero discharge temperature accepted');
foreach(['NaN','Infinity','1e999'] as $value) { $r=polynomialFixture();$r->data->quantities->cooling_capacity->coefficients_C0_to_C9[0]=$value;rejectsPolynomial(fn()=>$core->evaluate($r,$point),'Invalid coefficient '.$value); }
$r=polynomialFixture();$r->data->quantities->cooling_capacity->coefficients_C0_to_C9[6]='1e308';$overflow=$core->evaluate($r,$point);
checkPolynomial($overflow['mathematical_status']==='invalid_result' && $overflow['quantities']['cooling_capacity']['value']===null,'Polynomial overflow escaped as a value');
checkPolynomial(json_encode($overflow,JSON_THROW_ON_ERROR)!=='','Overflow produced unserializable NaN/Infinity');
$overflow=$core->evaluate(polynomialFixture(),(object)['Te_degC'=>'1e200','Tc_degC'=>'2e200','temperature_unit'=>'degC']);
checkPolynomial($overflow['mathematical_status']==='invalid_result','Temperature-power overflow accepted');
$r=polynomialFixture();$r->data->quantities->power_input->coefficients_C0_to_C9[0]='1e-323';
checkPolynomial($core->evaluate($r,$point)['compressor_cop']['value']===null,'Normalization underflow produced a COP');
foreach(['equation','unit','coefficients','required_quantity','type'] as $case) {
	$r=polynomialFixture();
	if($case==='equation')$r->data->equation_id='eval(php)';
	if($case==='unit')$r->data->quantities->power_input->unit='A';
	if($case==='coefficients')array_pop($r->data->quantities->current->coefficients_C0_to_C9);
	if($case==='required_quantity')unset($r->data->quantities->power_input);
	if($case==='type')$r->dataset_type='unknown';
	rejectsPolynomial(fn()=>$core->evaluate($r,$point),'Invalid closed contract '.$case);
}
foreach([(object)['Te_degC'=>'5','Tc_degC'=>'50','temperature_unit'=>'K'],(object)['Te_degC'=>'NaN','Tc_degC'=>'50','temperature_unit'=>'degC'],(object)['Te_degC'=>'50','Tc_degC'=>'50','temperature_unit'=>'degC'],(object)['Te_degC'=>'-273.15','Tc_degC'=>'50','temperature_unit'=>'degC']] as $bad) rejectsPolynomial(fn()=>$core->evaluate(polynomialFixture(),$bad),'Invalid temperature input');
$env=polynomialFixture()->data->operating_envelope;
foreach([[5,50,'inside'],[-10,30,'inside'],[10,60,'inside'],[11,50,'outside'],[5,61,'outside'],[5,'1e308','outside'],['10.00000000000000000001',50,'outside'],['5.00000000000000000001',50,'review_required']] as [$te,$tc,$status]) checkPolynomial($core->operatingRegion($env,(object)['Te_degC'=>$te,'Tc_degC'=>$tc,'temperature_unit'=>'degC'])['status']===$status,'Operating polygon containment');
$concave=clone $env;
$concave->vertices=array_map(fn($v)=>(object)['Te'=>(string)$v[0],'Tc'=>(string)$v[1]],[[-10,30],[10,30],[10,40],[0,40],[0,60],[-10,60]]);
foreach([[-5,50,'inside'],[5,50,'outside'],[0,50,'inside']] as [$te,$tc,$status]) checkPolynomial($core->operatingRegion($concave,(object)['Te_degC'=>$te,'Tc_degC'=>$tc,'temperature_unit'=>'degC'])['status']===$status,'Concave polygon containment failed');
$env->vertices=array_reverse($env->vertices);
checkPolynomial($core->operatingRegion($env,(object)['Te_degC'=>'5','Tc_degC'=>'50','temperature_unit'=>'degC'])['status']==='inside','Clockwise polygon rejected');
$env->vertices=[(object)['Te'=>'-10','Tc'=>'30'],(object)['Te'=>'10','Tc'=>'60'],(object)['Te'=>'10','Tc'=>'30'],(object)['Te'=>'-10','Tc'=>'50']];
checkPolynomial($core->operatingRegion($env,(object)['Te_degC'=>'5','Tc_degC'=>'50','temperature_unit'=>'degC'])['status']==='review_required','Self-intersecting envelope accepted');
echo "PASS: $d3Checks database-free compressor polynomial assertions (C0-C9, units, COP, missing quantities, numerical errors and operating polygons)\n";
