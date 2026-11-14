<?php

class Items_Service_CompressorPolynomial
{
	public const VERSION = 'compressor_polynomial_binary64_neumaier_v1';

	public function point(stdClass $point): stdClass
	{
		$p = (new Items_Service_TechnicalSchema())->fields($point, ['Te_degC' => 'number', 'Tc_degC' => 'number', 'temperature_unit' => ['degC']]);
		if((float)$p->Te_degC <= -273.15 || (float)$p->Tc_degC <= -273.15 || (float)$p->Te_degC >= (float)$p->Tc_degC) throw new InvalidArgumentException('Invalid compressor temperatures');
		return $p;
	}

	public function evaluate(stdClass $record, stdClass $point): array
	{
		$r = (new Items_Service_TechnicalSchema())->dataset($record);
		if($r->dataset_type !== 'compressor_polynomial') throw new InvalidArgumentException('A compressor polynomial dataset is required');
		$p = $this->point($point);
		$te = (float)$p->Te_degC; $tc = (float)$p->Tc_degC;
		$te2 = $te * $te; $tc2 = $tc * $tc;
		// The positions are fixed by te_tc_cubic_c0_c9_v1, including C7 and C8.
		$terms = [1.0, $te, $tc, $te2, $te * $tc, $tc2, $te2 * $te, $te2 * $tc, $te * $tc2, $tc2 * $tc];
		$reasons = []; $results = [];
		foreach(Items_Service_TechnicalSchema::QUANTITIES as $quantity => $units) {
			if(!isset($r->data->quantities->$quantity)) {
				$results[$quantity] = ['status' => 'not_available', 'value' => null, 'unit' => null];
				continue;
			}
			$curve = $r->data->quantities->$quantity;
			$value = $this->sum($curve->coefficients_C0_to_C9, $terms);
			$reason = null;
			if($value === null) $reason = 'numeric_overflow';
			elseif($quantity === 'discharge_temperature' ? $value <= -273.15 : $value <= 0.0) $reason = 'nonphysical_result';
			$result = ['status' => $reason === null ? 'calculated' : 'invalid_result', 'value' => $value, 'unit' => $curve->unit];
			if(in_array($quantity, ['cooling_capacity', 'power_input'], true) && $value !== null) {
				$kw = $curve->unit === 'W' ? $value / 1000.0 : $value;
				if(!is_finite($kw) || ($value !== 0.0 && $kw === 0.0)) { $reason = 'normalization_out_of_range'; $result['status'] = 'invalid_result'; $kw = null; }
				$result['normalized'] = ['value' => $kw, 'unit' => 'kW'];
			}
			if($reason !== null) $reasons[] = $quantity.':'.$reason;
			$results[$quantity] = $result;
		}
		$cop = ['status' => 'not_available', 'value' => null, 'unit' => '1', 'scope' => 'compressor_only'];
		if($results['cooling_capacity']['status'] === 'calculated' && $results['power_input']['status'] === 'calculated') {
			$value = $results['cooling_capacity']['normalized']['value'] / $results['power_input']['normalized']['value'];
			if(is_finite($value) && $value > 0.0) $cop = array_replace($cop, ['status' => 'calculated', 'value' => $value]);
			else $reasons[] = 'compressor_cop:numeric_out_of_range';
		}
		return ['equation_id' => $r->data->equation_id, 'calculation_version' => self::VERSION, 'numeric_format' => 'ieee754_binary64', 'inputs' => $p, 'numeric_inputs' => ['Te_degC' => $te, 'Tc_degC' => $tc],
			'mathematical_status' => $reasons ? 'invalid_result' : 'calculated', 'technical_status' => 'not_assessed', 'eligible' => false,
			'quantities' => $results, 'compressor_cop' => $cop, 'reasons' => $reasons];
	}

	private function sum(array $coefficients, array $terms): ?float
	{
		$sum = 0.0; $correction = 0.0;
		foreach($terms as $i => $basis) {
			if(!is_finite($basis)) return null;
			$term = (float)$coefficients[$i] * $basis;
			$next = $sum + $term;
			if(!is_finite($term) || !is_finite($next)) return null;
			$correction += abs($sum) >= abs($term) ? ($sum - $next) + $term : ($term - $next) + $sum;
			$sum = $next;
		}
		$result = $sum + $correction;
		return is_finite($result) ? $result : null;
	}

	// Only a valid simple Te/Tc polygon can establish a technical operating region.
	public function operatingRegion(stdClass $envelope, stdClass $point): array
	{
		$p = $this->point($point);
		$schema = new Items_Service_TechnicalSchema();
		$envelope = $schema->fields($envelope, ['kind' => ['te_tc_polygon'], 'unit' => ['degC'], 'vertices' => 'list:object', 'source_locator' => 'text']);
		$vertices = $exact = [];
		$largest = 1.0;
		foreach($envelope->vertices as $v) {
			$v = $schema->fields($v, ['Te' => 'number', 'Tc' => 'number']);
			if(!$this->preservesDecimal($v->Te) || !$this->preservesDecimal($v->Tc)) return ['status' => 'review_required', 'reason' => 'operating_envelope_precision'];
			$exact[] = [$v->Te, $v->Tc];
			$vertices[] = [(float)$v->Te, (float)$v->Tc];
			$largest = max($largest, abs((float)$v->Te), abs((float)$v->Tc));
		}
		// Power-of-two scaling avoids additional division rounding for ordinary inputs.
		$scale = 2.0 ** min(1023, floor(log($largest, 2)));
		$vertices = array_map(fn($v) => [$v[0] / $scale, $v[1] / $scale], $vertices);
		$point = [(float)$p->Te_degC / $scale, (float)$p->Tc_degC / $scale];
		$count = count($vertices);
		if($envelope->kind !== 'te_tc_polygon' || $envelope->unit !== 'degC' || $count < 3 || $count > 5000) return ['status' => 'review_required', 'reason' => 'operating_envelope_invalid'];
		$area = 0.0;
		foreach($vertices as $i => $a) {
			$b = $vertices[($i + 1) % $count];
			if($a === $b) return ['status' => 'review_required', 'reason' => 'operating_envelope_precision'];
			$area += $a[0] * $b[1] - $a[1] * $b[0];
			for($j = $i + 1; $j < $count; $j++) {
				if($j === $i + 1 || ($i === 0 && $j === $count - 1)) continue;
				$c = $vertices[$j]; $d = $vertices[($j + 1) % $count];
				if($this->intersects($a, $b, $c, $d)) return ['status' => 'review_required', 'reason' => 'operating_envelope_invalid'];
			}
		}
		if($area === 0.0) return ['status' => 'review_required', 'reason' => 'operating_envelope_precision'];
		$pointPreserved = true;
		foreach([$p->Te_degC, $p->Tc_degC] as $axis => $value) {
			$min = $max = $exact[0][$axis];
			foreach($exact as $vertex) {
				if($this->compareDecimal($vertex[$axis], $min) < 0) $min = $vertex[$axis];
				if($this->compareDecimal($vertex[$axis], $max) > 0) $max = $vertex[$axis];
			}
			if($this->compareDecimal($value, $min) < 0 || $this->compareDecimal($value, $max) > 0) return ['status' => 'outside', 'reason' => 'outside_operating_envelope'];
			$pointPreserved = $this->preservesDecimal($value) && $pointPreserved;
		}
		if(!$pointPreserved) return ['status' => 'review_required', 'reason' => 'operating_point_precision'];
		$winding = 0;
		foreach($vertices as $i => $a) {
			$b = $vertices[($i + 1) % $count];
			$cross = $this->orientation($a, $b, $point);
			if($cross === null) return ['status' => 'review_required', 'reason' => 'operating_boundary_precision'];
			if($cross === 0 && $this->between($a, $b, $point)) return ['status' => 'inside', 'reason' => null];
			if($a[1] <= $point[1] && $b[1] > $point[1] && $cross > 0) $winding++;
			if($a[1] > $point[1] && $b[1] <= $point[1] && $cross < 0) $winding--;
		}
		return ['status' => $winding === 0 ? 'outside' : 'inside', 'reason' => $winding === 0 ? 'outside_operating_envelope' : null];
	}

	private function preservesDecimal(string $value): bool
	{
		return Items_Service_TechnicalJson::decimal(json_encode((float)$value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)) === $value;
	}

	private function compareDecimal(string $a, string $b): int
	{
		if($a === $b) return 0;
		$negativeA = str_starts_with($a, '-'); $negativeB = str_starts_with($b, '-');
		if($negativeA !== $negativeB) return $negativeA ? -1 : 1;
		$sign = $negativeA ? -1 : 1;
		if($a === '0' || $b === '0') return ($a === '0' ? -1 : 1) * $sign;
		[$mantissaA, $exponentA] = array_pad(explode('e', ltrim($a, '-')), 2, '0');
		[$mantissaB, $exponentB] = array_pad(explode('e', ltrim($b, '-')), 2, '0');
		if((int)$exponentA !== (int)$exponentB) return ((int)$exponentA <=> (int)$exponentB) * $sign;
		$digitsA = str_replace('.', '', $mantissaA); $digitsB = str_replace('.', '', $mantissaB);
		$length = max(strlen($digitsA), strlen($digitsB));
		return (strcmp(str_pad($digitsA, $length, '0'), str_pad($digitsB, $length, '0')) <=> 0) * $sign;
	}

	private function orientation(array $a, array $b, array $p): ?int
	{
		$left = ($b[0] - $a[0]) * ($p[1] - $a[1]);
		$right = ($b[1] - $a[1]) * ($p[0] - $a[0]);
		$cross = $left - $right;
		if($cross === 0.0) return 0;
		if(abs($cross) <= 8.0 * PHP_FLOAT_EPSILON * (abs($left) + abs($right))) return null;
		return $cross > 0.0 ? 1 : -1;
	}

	private function between(array $a, array $b, array $p): bool
	{
		return $p[0] >= min($a[0], $b[0]) && $p[0] <= max($a[0], $b[0]) && $p[1] >= min($a[1], $b[1]) && $p[1] <= max($a[1], $b[1]);
	}

	private function intersects(array $a, array $b, array $c, array $d): bool
	{
		$abc = $this->orientation($a, $b, $c); $abd = $this->orientation($a, $b, $d);
		$cda = $this->orientation($c, $d, $a); $cdb = $this->orientation($c, $d, $b);
		if(in_array(null, [$abc, $abd, $cda, $cdb], true)) return true;
		return ($abc * $abd < 0 && $cda * $cdb < 0) || ($abc === 0 && $this->between($a, $b, $c)) || ($abd === 0 && $this->between($a, $b, $d)) || ($cda === 0 && $this->between($c, $d, $a)) || ($cdb === 0 && $this->between($c, $d, $b));
	}
}
