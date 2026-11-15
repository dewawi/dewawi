<?php

// Closed technical imports need duplicate-key detection and lossless decimal tokens.
class Items_Service_TechnicalJson
{
	private string $json;
	private int $offset = 0;

	public static function decode(string $json)
	{
		if(strlen($json) > 4194304 || !mb_check_encoding($json, 'UTF-8')) throw new InvalidArgumentException('Invalid technical JSON size or encoding');
		$reader = new self();
		$reader->json = $json;
		$value = $reader->value(0);
		$reader->space();
		if($reader->offset !== strlen($json)) throw new InvalidArgumentException('Trailing technical JSON input');
		return $value;
	}

	private function space(): void
	{
		while(isset($this->json[$this->offset]) && str_contains(" \r\n\t", $this->json[$this->offset])) $this->offset++;
	}

	private function value(int $depth)
	{
		if($depth > 32) throw new InvalidArgumentException('Technical JSON nesting limit exceeded');
		$this->space();
		$char = $this->json[$this->offset] ?? '';
		if($char === '{' || $char === '[') {
			$object = $char === '{';
			$close = $object ? '}' : ']';
			$this->offset++;
			$this->space();
			$result = [];
			if(($this->json[$this->offset] ?? '') !== $close) {
				while(true) {
					$key = null;
					if($object) {
						$key = $this->string();
						if(array_key_exists($key, $result)) throw new InvalidArgumentException('Duplicate technical JSON key');
						$this->space();
						if(($this->json[$this->offset++] ?? '') !== ':') throw new InvalidArgumentException('Invalid technical JSON object');
					}
					$value = $this->value($depth + 1);
					if($object) $result[$key] = $value;
					else $result[] = $value;
					$this->space();
					if(($this->json[$this->offset] ?? '') !== ',') break;
					$this->offset++;
					$this->space();
				}
			}
			if(($this->json[$this->offset++] ?? '') !== $close) throw new InvalidArgumentException('Invalid technical JSON container');
			return $object ? (object)$result : $result;
		}
		if($char === '"') return $this->string();
		foreach(['true' => true, 'false' => false, 'null' => null] as $token => $value) {
			if(substr($this->json, $this->offset, strlen($token)) === $token) { $this->offset += strlen($token); return $value; }
		}
		if(preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/A', $this->json, $match, 0, $this->offset)) {
			$this->offset += strlen($match[0]);
			// Never pass a JSON numeric token through a PHP float.
			return $match[0];
		}
		throw new InvalidArgumentException('Invalid technical JSON value');
	}

	private function string(): string
	{
		$this->space();
		if(!preg_match('/\G"(?:[^"\\\\\x00-\x1f]|\\\\(?:["\\\\\/bfnrt]|u[0-9a-fA-F]{4}))*"/A', $this->json, $match, 0, $this->offset)) throw new InvalidArgumentException('Invalid technical JSON string');
		$this->offset += strlen($match[0]);
		$value = json_decode($match[0], false, 2, JSON_THROW_ON_ERROR);
		if(str_contains($value, "\0") || preg_match('/[\x{10000}-\x{10ffff}]/u', $value)) throw new InvalidArgumentException('Unsupported technical text');
		return $value;
	}

	public static function decimal($value): string
	{
		if(is_int($value)) $value = (string)$value;
		if(!is_string($value) || strlen($value) > 1024 || !preg_match('/^(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?$/D', $value, $m)) throw new InvalidArgumentException('Invalid exact decimal');
		if(isset($m[4]) && (strlen(ltrim($m[4], '+-0')) > 3 || abs((int)$m[4]) > 999)) throw new InvalidArgumentException('Decimal exponent exceeds numeric range');
		$fraction = $m[3] ?? '';
		$digits = ltrim($m[2].$fraction, '0');
		if($digits === '') return '0';
		$exponent = (int)($m[4] ?? 0) - strlen($fraction) + strlen($digits) - 1;
		$digits = rtrim($digits, '0');
		$result = $m[1].$digits[0].(strlen($digits) > 1 ? '.'.substr($digits, 1) : '').($exponent ? 'e'.$exponent : '');
		if(!is_finite((float)$result) || (float)$result == 0.0) throw new InvalidArgumentException('Decimal is outside finite calculation range');
		return $result;
	}

	public static function addNonnegativeDecimals(string $a, string $b): string
	{
		$parts = [];
		foreach([$a, $b] as $value) {
			$value = self::decimal($value);
			if(str_starts_with($value, '-')) throw new InvalidArgumentException('Nonnegative decimal sum required');
			[$mantissa, $exponent] = array_pad(explode('e', $value), 2, '0');
			$digits = str_replace('.', '', $mantissa); $parts[] = [$digits, (int)$exponent - strlen($digits) + 1];
		}
		$scale = min($parts[0][1], $parts[1][1]);
		$x = $parts[0][0].str_repeat('0', $parts[0][1] - $scale); $y = $parts[1][0].str_repeat('0', $parts[1][1] - $scale);
		$length = max(strlen($x), strlen($y)); $x = str_pad($x, $length, '0', STR_PAD_LEFT); $y = str_pad($y, $length, '0', STR_PAD_LEFT);
		$sum = ''; $carry = 0;
		for($i = $length - 1; $i >= 0; $i--) { $digit = (int)$x[$i] + (int)$y[$i] + $carry; $sum = ($digit % 10).$sum; $carry = intdiv($digit, 10); }
		if($carry) $sum = $carry.$sum;
		$sum = ltrim($sum, '0');
		if($sum === '') return '0';
		$exponent = $scale + strlen($sum) - 1; $sum = rtrim($sum, '0');
		return self::decimal($sum[0].(strlen($sum) > 1 ? '.'.substr($sum, 1) : '').'e'.$exponent);
	}

	public static function compareDecimal(string $a, string $b): int
	{
		$a = self::decimal($a); $b = self::decimal($b);
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

	public static function encode($value): string
	{
		if(is_object($value)) {
			$fields = get_object_vars($value);
			ksort($fields, SORT_STRING);
			foreach($fields as &$field) $field = self::ordered($field);
			$value = (object)$fields;
		} else $value = self::ordered($value);
		return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}

	private static function ordered($value)
	{
		if(is_object($value)) {
			$fields = get_object_vars($value);
			ksort($fields, SORT_STRING);
			foreach($fields as &$field) $field = self::ordered($field);
			return (object)$fields;
		}
		if(is_array($value)) return array_map([self::class, 'ordered'], $value);
		if(is_float($value)) throw new InvalidArgumentException('Use exact decimal text instead of a float');
		return $value;
	}
}
