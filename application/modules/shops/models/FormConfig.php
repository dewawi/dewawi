<?php

class Shops_Model_FormConfig
{
	protected Shops_Model_DbTable_Inquiryform $_inquiryformDb;

	public function __construct(?Shops_Model_DbTable_Inquiryform $inquiryformDb = null)
	{
		$this->_inquiryformDb = $inquiryformDb ?? new Shops_Model_DbTable_Inquiryform();
	}

	public function getConfig(int $formId): array
	{
		$row = $this->_inquiryformDb->getInquiryform($formId);
		if(!$row) return [];
		try {
			return self::normalize(self::decode((string)($row['fields'] ?? '')));
		} catch(InvalidArgumentException $e) {
			error_log('Invalid inquiry form '.$formId.': '.$e->getMessage());
			return [];
		}
	}

	public static function decode(string $json): array
	{
		$config = json_decode($json, true);
		if(!is_array($config) || json_last_error() !== JSON_ERROR_NONE) throw new InvalidArgumentException('Invalid form JSON');
		return $config;
	}

	public static function normalize(array $config): array
	{
		$steps = [];
		$names = [];
		foreach($config as $step => $fields) {
			if($step === '_settings') {
				if(!is_array($fields)) throw new InvalidArgumentException('Invalid form settings');
				continue;
			}
			if(!is_array($fields) || !$fields) throw new InvalidArgumentException('Invalid form step');
			$steps[$step] = [];
			self::collectFields($fields, $steps[$step], $names);
			$byName = array_column($steps[$step], null, 'name');
			foreach($steps[$step] as &$definition) {
				if(isset($definition['depends_on']) && isset($byName[$definition['depends_on']])) {
					$value = $definition['depends_value'];
					$values = array_map(static fn($trigger): string => self::dependencyValue($byName[$definition['depends_on']], $trigger), (array)$value);
					$definition['depends_value'] = is_array($value) ? array_values(array_unique($values)) : $values[0];
				}
				$field = $definition;
				$visited = [$field['name']];
				while(isset($field['depends_on'])) {
					$parent = $field['depends_on'];
					if(!isset($byName[$parent]) || in_array($parent, $visited, true)) throw new InvalidArgumentException('Invalid or cyclic field dependency');
					$visited[] = $parent;
					foreach((array)$field['depends_value'] as $trigger) self::dependencyValue($byName[$parent], $trigger);
					$field = $byName[$parent];
				}
			}
			unset($definition);
		}
		return $steps;
	}

	private static function collectFields(array $fields, array &$result, array &$names, ?array $dependency = null): void
	{
		foreach($fields as $field) {
			if(!is_array($field) || !is_string($field['name'] ?? null) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $field['name'])
				|| !is_string($field['type'] ?? null) || !in_array($field['type'], ['text', 'number', 'email', 'textarea', 'select', 'radio', 'checkbox', 'hidden', 'tel', 'url', 'date', 'search'], true)
				|| in_array($field['name'], ['csrf_token', 'inquiry_token', 'step', 'back', 'next', '_settings', '_selection', '_quote'], true) || isset($names[$field['name']])) {
				throw new InvalidArgumentException('Invalid or duplicate field');
			}
			$names[$field['name']] = true;
			if($dependency !== null) {
				$field['depends_on'] = $dependency['name'];
				$field['depends_value'] = $dependency['value'];
			}
			if(isset($field['depends_on'])) {
				$values = $field['depends_value'] ?? null;
				if(!is_string($field['depends_on']) || (!is_scalar($values) && (!is_array($values) || !$values || array_values($values) !== $values))) throw new InvalidArgumentException('Invalid dependency');
				foreach((array)$values as $value) if(!is_scalar($value)) throw new InvalidArgumentException('Invalid dependency value');
			}
			if(isset($field['source']) && ($field['source'] !== 'country' || $field['type'] !== 'select')) throw new InvalidArgumentException('Unknown inquiry option source');
			$exclusiveMinimum = $field['attribs']['data-min-exclusive'] ?? null;
			if($exclusiveMinimum !== null && ($field['type'] !== 'number' || !is_numeric($exclusiveMinimum) || !is_finite((float)$exclusiveMinimum))) throw new InvalidArgumentException('Invalid exclusive minimum');
			if(in_array($field['type'], ['select', 'radio'], true)) {
				if(!is_array($field['options'] ?? null) || !$field['options']) throw new InvalidArgumentException('Missing selection options');
				foreach($field['options'] as $value => $label) {
					if(!is_string($label) && !is_numeric($label)) throw new InvalidArgumentException('Invalid selection option');
				}
			}
			$dependencies = $field['depends'] ?? [];
			unset($field['depends']);
			$result[] = $field;
			if(!is_array($dependencies)) throw new InvalidArgumentException('Invalid dependent fields');
			foreach($dependencies as $trigger => $children) {
				if(!is_array($children) || !$children) throw new InvalidArgumentException('Invalid dependent fields');
				self::collectFields($children, $result, $names, ['name' => $field['name'], 'value' => self::dependencyValue($field, $trigger)]);
			}
		}
	}

	public static function dependencyValue(array $field, $trigger): string
	{
		$trigger = (string)$trigger;
		if($field['type'] === 'checkbox' && in_array($trigger, ['0', '1'], true)) return $trigger;
		$options = $field['options'] ?? [];
		if(array_key_exists($trigger, $options)) return $trigger;
		$matches = array_keys($options, $trigger, true);
		if(count($matches) !== 1) throw new InvalidArgumentException('Unknown or ambiguous dependency value');
		return (string)$matches[0];
	}
}
