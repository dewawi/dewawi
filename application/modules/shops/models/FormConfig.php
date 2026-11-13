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
		if(($config['_settings']['selection']['method'] ?? '') === 'catalog') self::catalogSettings($steps, $config['_settings']['selection']);
		return $steps;
	}

	public static function catalogSettings(array $steps, array $selection): array
	{
		$step = (string)($selection['step'] ?? array_key_first($steps));
		$stepIds = array_map('strval', array_keys($steps));
		if(!isset($steps[$step]) || $step === end($stepIds)) throw new InvalidArgumentException('Catalog selection needs a following form step');
		$fields = $inputFields = [];
		foreach($steps as $id => $definitions) foreach($definitions as $field) {
			$fields[$field['name']] = $field;
			if(array_search((string)$id, $stepIds, true) <= array_search($step, $stepIds, true)) $inputFields[$field['name']] = $field;
		}
		$itemField = $selection['item_field'] ?? 'itemid';
		if(!is_string($itemField) || !isset($fields[$itemField]) || isset($inputFields[$itemField]) || $fields[$itemField]['type'] !== 'hidden'
			|| isset($fields[$itemField]['depends_on']) || !empty($fields[$itemField]['required']) || !empty($fields[$itemField]['default']) || !empty($fields[$itemField]['value'])) throw new InvalidArgumentException('Invalid catalog item field');
		$categories = $selection['categories'] ?? [];
		if(!is_array($categories) || !$categories || count($categories) > 20 || array_values($categories) !== $categories) throw new InvalidArgumentException('Invalid catalog categories');
		foreach($categories as $id) if((!is_int($id) && !is_string($id)) || !filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('Invalid catalog category ID');
		$limit = $selection['limit'] ?? 12;
		if(!is_int($limit) || $limit < 1 || $limit > 24 || (isset($selection['include_subcategories']) && !is_bool($selection['include_subcategories']))) throw new InvalidArgumentException('Invalid catalog result bounds');
		$filters = $selection['filters'] ?? [];
		if(!is_array($filters) || array_values($filters) !== $filters || count($filters) > 10) throw new InvalidArgumentException('Invalid catalog filters');
		foreach($filters as &$filter) {
			if(!is_array($filter) || array_diff(array_keys($filter), ['field', 'values', 'value_map', 'attribute', 'column', 'type', 'operator', 'unit', 'set_id', 'label'])) throw new InvalidArgumentException('Unknown catalog filter setting');
			if(!is_string($filter['attribute'] ?? null) || trim($filter['attribute']) === '' || strlen($filter['attribute']) > 255
				|| !in_array($filter['column'] ?? '', ['value', 'description'], true) || !in_array($filter['type'] ?? '', ['text', 'number'], true)
				|| !in_array($filter['operator'] ?? '', $filter['type'] === 'number' ? ['eq', 'gte', 'lte'] : ['eq'], true)) throw new InvalidArgumentException('Invalid catalog attribute comparison');
			if(($filter['type'] === 'number' && !array_key_exists('unit', $filter)) || (array_key_exists('unit', $filter) && (!is_string($filter['unit']) || strlen($filter['unit']) > 32))) throw new InvalidArgumentException('Explicit catalog units are required');
			$filter['unit'] = trim($filter['unit'] ?? '');
			if(isset($filter['set_id']) && ((!is_int($filter['set_id']) && !is_string($filter['set_id'])) || filter_var($filter['set_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false)) throw new InvalidArgumentException('Invalid catalog attribute group');
			if(isset($filter['label']) && (!is_string($filter['label']) || strlen($filter['label']) > 255)) throw new InvalidArgumentException('Invalid catalog filter label');
			if(isset($filter['field']) === isset($filter['values'])) throw new InvalidArgumentException('Catalog filter needs a field or fixed values');
			if(isset($filter['field'])) {
				$name = $filter['field'];
				if(!is_string($name) || !isset($inputFields[$name]) || trim((string)($inputFields[$name]['unit'] ?? '')) !== $filter['unit']) throw new InvalidArgumentException('Invalid catalog input mapping or unit');
			}
			if(isset($filter['value_map']) && (!isset($filter['field']) || !is_array($filter['value_map']) || !$filter['value_map'] || count($filter['value_map']) > 20)) throw new InvalidArgumentException('Invalid catalog input value mapping');
			if(isset($filter['value_map']) && in_array($inputFields[$filter['field']]['type'], ['select', 'radio', 'checkbox'], true)) {
				$options = $inputFields[$filter['field']]['type'] === 'checkbox' ? ['0' => '', '1' => ''] : $inputFields[$filter['field']]['options'];
				foreach($filter['value_map'] as $key => $value) if(!array_key_exists((string)$key, $options)) throw new InvalidArgumentException('Unknown catalog input option');
			}
			$values = $filter['values'] ?? array_values($filter['value_map'] ?? []);
			if(!is_array($values) || (isset($filter['values']) && (!$values || array_values($values) !== $values || count($values) > 20 || ($filter['operator'] !== 'eq' && count($values) !== 1)))) throw new InvalidArgumentException('Invalid catalog value list');
			foreach($values as $value) {
				if(!is_scalar($value) || is_bool($value) || trim((string)$value) === '' || strlen((string)$value) > 255 || ($filter['type'] === 'number' && Shops_Service_InquirySelection::decimal($value) === null)) throw new InvalidArgumentException('Invalid catalog comparison value');
			}
		}
		unset($filter);
		return ['step' => $step, 'item_field' => $itemField, 'categories' => array_values(array_unique(array_map('intval', $categories))), 'include_subcategories' => $selection['include_subcategories'] ?? false, 'limit' => $limit, 'filters' => $filters];
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
