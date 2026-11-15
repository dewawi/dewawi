<?php

class Items_Service_FluidProperties
{
	public const VERSION = 'bilinear_properties_piecewise_cp_integral_v1';

	public function evaluate(stdClass $record, stdClass $request): array
	{
		$record = (new Items_Service_TechnicalSchema())->dataset($record);
		if($record->dataset_type !== 'fluid_properties') throw new InvalidArgumentException('Fluid properties required');
		$s = new Items_Service_TechnicalSchema(); $d = $record->data;
		$input = $s->fields($request, ['operation' => ['properties', 'heat', 'temperature', 'mix']], ['composition' => 'object', 'temperature_degC' => 'number', 'inlet_degC' => 'number', 'outlet_degC' => 'number', 'known_end' => ['inlet', 'outlet'], 'heat_W' => 'number', 'flow' => 'object', 'branches' => 'list:object']);
		$required = ['properties' => ['composition','temperature_degC'], 'heat' => ['composition','inlet_degC','outlet_degC','flow'], 'temperature' => ['composition','temperature_degC','known_end','heat_W','flow'], 'mix' => ['branches']][$input->operation];
		if(array_diff($required, array_keys((array)$input)) || array_diff(array_keys((array)$input), array_merge(['operation'], $required))) throw new InvalidArgumentException('Incomplete or irrelevant fluid request fields');
		$result = ['calculation_version' => self::VERSION, 'eligible' => false, 'status' => 'review_required', 'calculation' => null, 'reasons' => [], 'fluid_revision' => ['dataset_key' => $record->dataset_key, 'revision' => $record->revision]];
		try {
			switch($input->operation) {
				case 'properties': $value = $this->state($d, $input->composition, $input->temperature_degC); break;
				case 'heat':
					$a = $this->state($d, $input->composition, $input->inlet_degC); $b = $this->state($d, $input->composition, $input->outlet_degC); $flow = $this->flow($d, $input->composition, $input->flow);
					$value = ['inlet' => $a, 'outlet' => $b, 'flow' => $flow, 'heat_W' => $flow['mass_flow_kg_s'] * $this->enthalpyDifference($d,$input->composition,$input->inlet_degC,$input->outlet_degC), 'secondary_spread_K' => (float)$input->outlet_degC - (float)$input->inlet_degC, 'evaporator_approach_K' => null]; break;
				case 'temperature':
					$known = $this->state($d, $input->composition, $input->temperature_degC); $flow = $this->flow($d, $input->composition, $input->flow);
					$target = $known['specific_enthalpy_J_kg'] + ($input->known_end === 'inlet' ? 1 : -1) * (float)$input->heat_W / $flow['mass_flow_kg_s'];
					if((float)$input->heat_W!==0.0 && $target===$known['specific_enthalpy_J_kg'])throw new DomainException('fluid_enthalpy_change_below_numeric_resolution');
					$value = ['unknown_end' => $input->known_end === 'inlet' ? 'outlet' : 'inlet', 'state' => $this->temperature($d, $input->composition, $target), 'flow' => $flow, 'heat_W' => (float)$input->heat_W]; break;
				case 'mix': $value = $this->mix($d, $input->branches); break;
			}
			$this->finite($value); $result['calculation'] = $value; $result['mathematical_status'] = 'calculated';
		} catch(DomainException $e) { $result['reasons'][] = $e->getMessage(); $result['mathematical_status'] = 'blocked'; }
		return $result;
	}

	public function composition(stdClass $data, stdClass $value): stdClass
	{
		$c = (new Items_Service_TechnicalSchema())->fields($value, ['basis' => ['mass_fraction', 'volume_fraction'], 'fraction' => 'nonnegative', 'volume_reference_degC' => '?number']);
		if($c->basis !== $data->concentration_basis || $c->volume_reference_degC !== $data->volume_reference_degC) throw new DomainException('fluid_concentration_basis_mismatch');
		self::bracket($data->concentrations, $c->fraction); return $c;
	}

	public function state(stdClass $data, stdClass $composition, string $temperature): array
	{
		$c = $this->composition($data, $composition); $t = Items_Service_TechnicalJson::decimal($temperature);
		[$c0,$c1,$cw] = self::bracket($data->concentrations, $c->fraction); [$t0,$t1,$tw] = self::bracket($data->temperatures_degC, $t);
		if($data->freezing_degC === null) throw new DomainException('fluid_freezing_evidence_missing');
		$freeze = self::lerp((float)$data->freezing_degC[$c0], (float)$data->freezing_degC[$c1], $cw);
		if((float)$t <= $freeze) throw new DomainException('fluid_freezing_limit_violated');
		$at = function(int $i, string $key) use ($data,$c0,$c1,$cw): float { return self::lerp((float)$data->properties[$c0][$i]->$key, (float)$data->properties[$c1][$i]->$key, $cw); };
		$r = ['temperature_degC' => (float)$t, 'composition' => $c, 'freezing_degC' => $freeze];
		foreach(['density_kg_m3','specific_heat_J_kg_K','dynamic_viscosity_Pa_s'] as $key) $r[$key] = self::lerp($at($t0,$key), $at($t1,$key), $tw);
		// The common enthalpy datum is the first temperature node, never an absolute enthalpy claim.
		$h = 0.0;
		for($i=0; $i<count($data->temperatures_degC)-1 && (float)$data->temperatures_degC[$i] < (float)$t; $i++) {
			$lo = (float)$data->temperatures_degC[$i]; $hi = min((float)$t, (float)$data->temperatures_degC[$i+1]); $width = (float)$data->temperatures_degC[$i+1] - $lo;
			$cp0 = $at($i,'specific_heat_J_kg_K'); $cp1 = $at($i+1,'specific_heat_J_kg_K');
			$h += ($hi-$lo) * ($cp0 + 0.5 * ($cp1-$cp0) * (($hi-$lo)/$width));
		}
		$r['specific_enthalpy_J_kg'] = $h; $this->finite($r); return $r;
	}

	public function enthalpyDifference(stdClass $data, stdClass $composition, string $from, string $to): float
	{
		$this->state($data,$composition,$from);$this->state($data,$composition,$to);
		if(Items_Service_TechnicalJson::compareDecimal($from,$to)===0)return 0.0;
		if((float)$from===(float)$to)throw new DomainException('fluid_temperature_difference_below_numeric_resolution');
		$low=min((float)$from,(float)$to);$high=max((float)$from,(float)$to);$nodes=[$low];
		foreach($data->temperatures_degC as $node)if((float)$node>$low && (float)$node<$high)$nodes[]=(float)$node;$nodes[]=$high;$sum=0.0;
		for($i=1;$i<count($nodes);$i++) {
			$p=$this->state($data,$composition,self::decimal($nodes[$i-1]));$q=$this->state($data,$composition,self::decimal($nodes[$i]));
			$sum+=($nodes[$i]-$nodes[$i-1])*(0.5*$p['specific_heat_J_kg_K']+0.5*$q['specific_heat_J_kg_K']);
		}
		if(!is_finite($sum) || $sum<=0)throw new DomainException('fluid_enthalpy_difference_outside_numeric_range');return (float)$to>(float)$from?$sum:-$sum;
	}

	public function flow(stdClass $data, stdClass $composition, stdClass $value): array
	{
		$f = (new Items_Service_TechnicalSchema())->fields($value, ['value' => 'positive', 'unit' => ['m3/h', 'kg/s', 'kg/h'], 'at_degC' => 'number']);
		$p = $this->state($data,$composition,$f->at_degC); $v = (float)$f->value;
		$m = $f->unit === 'm3/h' ? $v*$p['density_kg_m3']/3600 : ($f->unit === 'kg/h' ? $v/3600 : $v);
		if(!is_finite($m) || $m<=0 || !is_finite(3600*$m/$p['density_kg_m3']) || 3600*$m/$p['density_kg_m3']<=0) throw new DomainException('fluid_flow_outside_numeric_range');
		$r = ['mass_flow_kg_s' => $m, 'volume_flow_m3_h' => 3600*$m/$p['density_kg_m3'], 'at_degC' => (float)$f->at_degC]; $this->finite($r); return $r;
	}

	public function temperature(stdClass $data, stdClass $composition, float $enthalpy): array
	{
		if(!is_finite($enthalpy)) throw new DomainException('nonfinite_fluid_enthalpy');
		// Integrating cp is valid across the table; only the resulting state must be liquid.
		$c = $this->composition($data,$composition); $low = (float)$data->temperatures_degC[0]; $high = (float)end($data->temperatures_degC);
		$top = $this->state($data,$c,self::decimal($high));
		if($enthalpy < 0 || $enthalpy > $top['specific_enthalpy_J_kg']) throw new DomainException('fluid_enthalpy_outside_table');
		// The relative cp integral may use nodes below freezing; the returned liquid state may not.
		$integration = clone $data; $integration->freezing_degC = array_fill(0,count($data->concentrations),'-273.15');
		if($top['freezing_degC'] >= $low) {
			$freezing = $this->state($integration,$c,self::decimal($top['freezing_degC']));
			if($enthalpy <= $freezing['specific_enthalpy_J_kg']) throw new DomainException('fluid_freezing_limit_violated');
		}
		for($i=0;$i<80;$i++) {
			$mid = $low + ($high-$low)/2;
			$p = $this->state($integration,$c,self::decimal($mid));
			if($p['specific_enthalpy_J_kg'] < $enthalpy) $low=$mid; else $high=$mid;
			if($high-$low <= 1e-10) break;
		}
		$p = $this->state($data,$c,self::decimal($low+($high-$low)/2));
		if(abs($p['specific_enthalpy_J_kg']-$enthalpy) > max(1e-5, abs($enthalpy)*1e-12)) throw new DomainException('fluid_enthalpy_inversion_not_converged');
		return $p;
	}

	public function mix(stdClass $data, array $branches): array
	{
		if(count($branches)<2 || count($branches)>100) throw new InvalidArgumentException('Two to one hundred known fluid branches required');
		$s = new Items_Service_TechnicalSchema(); $mass = 0.0; $energy = 0.0; $volume=0.0; $identity=null; $states=[]; $seen=[];
		foreach($branches as $branch) {
			$b=$s->fields($branch,['branch_key'=>'key','composition'=>'object','temperature_degC'=>'number','flow'=>'object']);
			if(isset($seen[$b->branch_key])) throw new InvalidArgumentException('Duplicate fluid branch'); $seen[$b->branch_key]=true;
			$c=$this->composition($data,$b->composition); $signature=Items_Service_TechnicalJson::encode($c);
			if($identity!==null && $identity!==$signature) throw new DomainException('mixing_composition_conversion_not_supported'); $identity=$signature;
			$p=$this->state($data,$c,$b->temperature_degC); $f=$this->flow($data,$c,$b->flow); $mass+=$f['mass_flow_kg_s']; $energy+=$f['mass_flow_kg_s']*$p['specific_enthalpy_J_kg']; $volume+=3600*$f['mass_flow_kg_s']/$p['density_kg_m3'];
			$states[$b->branch_key]=['state'=>$p,'flow'=>$f];
		}
		$p=$this->temperature($data,$c,$energy/$mass); $residual=$mass*$p['specific_enthalpy_J_kg']-$energy;
		if(abs($residual)>max(1e-4,abs($energy)*1e-12)) throw new DomainException('fluid_mixing_energy_not_conserved');
		$r=['branches'=>$states,'mixed'=>$p,'mass_flow_kg_s'=>$mass,'branch_volume_flow_sum_m3_h'=>$volume,'mixed_volume_flow_m3_h'=>3600*$mass/$p['density_kg_m3'],'enthalpy_flow_W'=>$energy,'mass_residual_kg_s'=>0.0,'energy_residual_W'=>$residual]; $this->finite($r); return $r;
	}

	public static function bracket(array $axis, string $value): array
	{
		$value=Items_Service_TechnicalJson::decimal($value);
		if(Items_Service_TechnicalJson::compareDecimal($value,$axis[0])<0 || Items_Service_TechnicalJson::compareDecimal($value,end($axis))>0) throw new DomainException('fluid_or_hydraulic_extrapolation_forbidden');
		foreach($axis as $i=>$node) {
			$cmp=Items_Service_TechnicalJson::compareDecimal($value,$node);
			if($cmp===0) return [$i,$i,0.0];
			if($cmp<0) return [$i-1,$i,((float)$value-(float)$axis[$i-1])/((float)$node-(float)$axis[$i-1])];
		}
		throw new DomainException('invalid_interpolation_position');
	}

	public static function lerp(float $a,float $b,float $w): float { return $a+($b-$a)*$w; }
	public static function decimal(float $value): string { if(!is_finite($value)) throw new DomainException('nonfinite_fluid_hydraulic_result'); return json_encode($value,JSON_THROW_ON_ERROR); }
	public function finite($value): void { if(is_float($value) && !is_finite($value)) throw new DomainException('nonfinite_fluid_hydraulic_result'); if(is_array($value)||is_object($value)) foreach($value as $v) $this->finite($v); }
}
