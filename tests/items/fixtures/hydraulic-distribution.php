<?php

require_once __DIR__.'/fluid-hydraulics.php';

function hydraulicDistributionBundle(string $prefix='fixture:d5b', bool $thermalReference=false): stdClass
{
	$b=fluidMachineBundle($prefix);
	foreach($b->datasets as $r) {
		if($r->dataset_type === 'heat_exchanger_map') foreach($r->data->media as $medium) { $medium->fluid_ref = (object)['dataset_key' => $prefix.':fluid', 'revision' => 1]; $medium->fluid_composition = fluidComposition(); }
		if($r->dataset_type==='hydraulic_path' && $r->data->loop_key==='load') {
			$r->data->contract_version=2;$r->data->topology_kind='fixed_speed_two_parallel_sections_v1';$r->data->parallel_instance='evap';$r->data->temperature_model='prescribed_uniform_temperature_v1';
			$r->data->parallel_sections=[(object)['section_key'=>'r1','flow_min_m3_h'=>'0.1','flow_max_m3_h'=>'30'],(object)['section_key'=>'r2','flow_min_m3_h'=>'0.1','flow_max_m3_h'=>'30']];
			$r->data->numerics=(object)['pressure_tolerance_Pa'=>'0.000001','flow_tolerance_m3_h'=>'0.000000001','mass_tolerance_kg_s'=>'0.0000000001','max_iterations'=>80];
		}
		if($r->dataset_type==='pressure_loss_map' && in_array($r->data->role,['evaporator','tank'],true) || ($r->dataset_type==='pressure_loss_map' && str_ends_with($r->dataset_key,':external:load'))) {
			$d=$r->data;$d->volume_flows_m3_h=['0.1','2','4','6','10','20','40'];
			foreach($d->sections as $section) {
				$coefficient=$d->role==='evaporator'?($section->section_key==='r1'?8000/6:8000/4):($d->role==='tank'?150:50);
				if($thermalReference)$coefficient=$d->role==='evaporator'?4000/($section->section_key==='r1'?10.8:8.82):($d->role==='tank'?1500/19.62:500/19.62);
				$grid=[];foreach($d->concentrations as $c){$rows=[];foreach($d->temperatures_degC as $t)$rows[]=array_map(fn($q)=>(string)($coefficient*(float)$q*(0.08-0.0005*(float)$t)/0.075),$d->volume_flows_m3_h);$grid[]=$rows;}$section->pressure_losses=$grid;
			}
		}
		if($r->dataset_type==='pump_curve') {
			$r->data->ordinate_unit='Pa';
			foreach($r->data->points as $point)$point->ordinate=(string)($thermalReference?12000-6000*(float)$point->volume_flow_m3_h/19.62:20000-1000*(float)$point->volume_flow_m3_h);
			// The documented pump domain ends before its ordinate becomes negative.
			$r->data->points[1]->volume_flow_m3_h=$thermalReference?'35':'19';$r->data->points[1]->ordinate=(string)($thermalReference?12000-6000*35/19.62:1000);
		}
	}
	return $b;
}

function hydraulicDistributionRequest(string $temperature='10'): stdClass
{
	return (object)['loop_key'=>'load','composition'=>fluidComposition(),'temperature_degC'=>$temperature,'speed_rpm'=>'2900','frequency_Hz'=>'50','operating_mode'=>'fixed'];
}
