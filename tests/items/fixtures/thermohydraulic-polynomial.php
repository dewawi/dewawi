<?php

require_once __DIR__.'/thermohydraulic-circuit.php';

function thermohydraulicPolynomialBundle(string $mode='cooling', string $prefix='fixture:d6b2', bool $variableFluid=false): stdClass
{
	$b=thermohydraulicBundle($mode,$prefix,$variableFluid);
	foreach($b->datasets as $record){
		if($record->dataset_type==='operating_mode_model'){$record->data->contract_version=3;$record->data->coupling->model_id='synthetic_polynomial_compressor_two_liquid_loops_v1';}
		if($record->dataset_type==='compressor_polynomial'){
			$second=$record->data->model==='SYNTHETIC-COMPRESSOR-B';
			$record->data->quantities->cooling_capacity->coefficients_C0_to_C9=array_pad($second?['22500','300','-80']:['10500','100','-20'],10,'0');
			$record->data->quantities->power_input->coefficients_C0_to_C9=array_pad($second?['4075','15','20']:['1475','5','10'],10,'0');
		}
	}
	$b->template_kind='synthetic_dynamic_coupled_test_v1';$b->provenance_note='Invented independent compressor polynomials and liquid-loop reference data; no proprietary OEM coefficients.';
	foreach($b->datasets as $record)$record->import_context=(object)['template_kind'=>$b->template_kind,'production_import_allowed'=>false,'provenance_note'=>$b->provenance_note];return $b;
}
