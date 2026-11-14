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
