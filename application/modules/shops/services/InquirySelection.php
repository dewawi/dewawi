<?php

class Shops_Service_InquirySelection
{
	public static function settings(array $formRow): array
	{
		$config = Shops_Model_FormConfig::decode((string)($formRow['fields'] ?? '{}'));
		$settings = $config['_settings'] ?? [];
		if(!is_array($settings)) throw new InvalidArgumentException('Invalid inquiry settings');
		return $settings;
	}

	public function evaluate(array $config, array $settings, array $values, array $suggestions): array
	{
		$selection = $settings['selection'] ?? [];
		if(!is_array($selection)) throw new InvalidArgumentException('Invalid selection settings');
		if(($selection['method'] ?? '') === 'catalog') $config = Shops_Model_FormConfig::normalize($config);
		$definitions = [];
		foreach($config as $step) foreach($step as $field) $definitions[$field['name']] = $field;
		$result = ['eligible' => false, 'method' => $selection['method'] ?? 'form_calc', 'reasons' => [], 'options' => [],
			'inputs' => array_intersect_key($values, $definitions), 'candidates' => [], 'rejected' => [], 'conditions' => [], 'selected' => null,
			'technical' => ['status' => 'review_required'], 'commercial' => ['status' => 'review_required']];
		if($result['method'] === 'catalog') {
			$result = $this->evaluateCatalog($config, $selection, $values, $result);
			$id = $values[$result['item_field']] ?? '';
			if($id !== '' && $id !== null && $result['selected'] === null) $result['reasons'][] = 'catalog_selection_invalid';
			return $result;
		}
		foreach($suggestions as $suggestion) if(!empty($suggestion['itemid'])) {
			$candidate = ['itemid' => (int)$suggestion['itemid'], 'sku' => (string)$suggestion['sku'], 'title' => (string)$suggestion['title'], 'conditions' => [], 'technical' => $result['technical'], 'commercial' => $result['commercial']];
			$result['candidates'][] = $candidate;
			if($candidate['sku'] === (string)($values['sku'] ?? '')) $result['selected'] = array_intersect_key($candidate, array_flip(['itemid', 'sku', 'title']));
		}
		if(($selection['automatic_quote'] ?? false) !== true || $result['method'] !== 'form_calc') {
			$result['reasons'][] = 'manual_selection_required';
			return $result;
		}
		$fields = [];
		$calculated = [];
		foreach($config as $step) foreach($step as $field) {
			$fields[$field['name']] = $field;
			if(is_array($field['calculate'] ?? null)) $calculated[] = $field;
		}
		if(count($calculated) !== 1) $result['reasons'][] = 'selection_not_verified';
		$inputs = [];
		if(count($calculated) === 1) {
			$inputs[] = $calculated[0]['name'];
			$inputs[] = (string)($calculated[0]['calculate']['required_field'] ?? (array_key_exists($calculated[0]['name'], $values) ? $calculated[0]['name'] : 'kuehlleistung'));
			preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*/', (string)($calculated[0]['calculate']['target'] ?? ''), $matches);
			$inputs = array_merge($inputs, $matches[0]);
		}
		$selected = null;
		foreach($suggestions as $suggestion) {
			if((string)($suggestion['description'] ?? '') === (string)($values['sku'] ?? '')) $selected = $suggestion;
		}
		$required = count($calculated) === 1 ? self::decimal($values[$inputs[1]] ?? null) : null;
		if($selected === null || self::decimal($selected['capacity_raw'] ?? $selected['value'] ?? null) === null || $required === null || $required <= 0 || self::decimal($selected['capacity_raw'] ?? $selected['value'] ?? null) < $required) {
			$result['reasons'][] = 'capacity_not_verified';
		}
		if(isset($values['quantity']) && (self::decimal($values['quantity']) === null || self::decimal($values['quantity']) <= 0)) $result['reasons'][] = 'quantity_not_verified';
		$contactFields = ['sku', 'titel', 'title', 'quantity', 'firma', 'company', 'ansprechpartner', 'firstname', 'lastname', 'vorname', 'nachname', 'name', 'email', 'telefon', 'phone', 'adresse', 'street', 'plz', 'postcode', 'stadt', 'city', 'country', 'land', 'projektname', 'reference', 'privacy', 'lieferadresse_abweichend', 'lieferempfaenger', 'lieferansprechpartner', 'lieferstrasse', 'lieferplz', 'lieferstadt', 'lieferland'];
		$accepted = $selection['accepted_values'] ?? [];
		$optionFields = $selection['option_fields'] ?? [];
		if(!is_array($accepted) || !is_array($optionFields)) throw new InvalidArgumentException('Invalid selection coverage');
		foreach($values as $name => $value) {
			if(in_array($name, $contactFields, true) || (in_array($name, $inputs, true) && !in_array($name, ['pumpe', 'geraeuschreduktion'], true)) || $value === '' || $value === null) continue;
			$field = $fields[$name] ?? [];
			if(($field['type'] ?? '') === 'textarea') {
				$result['reasons'][] = 'customer_instructions_require_review';
				continue;
			}
			if(isset($optionFields[$name]) && is_array($optionFields[$name]) && array_key_exists((string)$value, $optionFields[$name])) {
				$mapping = $optionFields[$name][(string)$value];
				if($mapping === null) {
					if(in_array($name, ['pumpe', 'geraeuschreduktion'], true) && !in_array(mb_strtolower((string)($field['options'][$value] ?? $value)), ['nein', 'no', 'keine', 'none'], true)) $result['reasons'][] = 'option_not_verified';
					continue;
				}
				if(!is_array($mapping) || ($mapping['selection_verified'] ?? false) !== true || !filter_var($mapping['itemoptid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
					$result['reasons'][] = 'option_not_verified';
				} else $result['options'][] = (int)$mapping['itemoptid'];
				continue;
			}
			if(in_array($name, ['pumpe', 'geraeuschreduktion'], true) && !in_array(mb_strtolower((string)($field['options'][$value] ?? $value)), ['nein', 'no', 'keine', 'none'], true)) $result['reasons'][] = 'option_not_verified';
			if(!isset($accepted[$name]) || !is_array($accepted[$name]) || !in_array((string)$value, array_map('strval', $accepted[$name]), true)) {
				$result['reasons'][] = 'unverified_requirement:'.$name;
			}
		}
		// FORM_CALC has no fluid correction, even if a form configuration accepts the input.
		$mediumField = (string)($selection['medium_field'] ?? 'medium');
		if(isset($values[$mediumField])) {
			$label = $fields[$mediumField]['options'][$values[$mediumField]] ?? $values[$mediumField];
			if(!in_array(mb_strtolower(trim((string)$label)), ['wasser', 'water'], true)) $result['reasons'][] = 'fluid_not_verified';
		}
		foreach(['glykol_typ', 'glykol_anteil'] as $field) {
			if(isset($values[$field]) && $values[$field] !== '') $result['reasons'][] = 'fluid_not_verified';
		}
		$country = strtoupper(trim((string)($values['country'] ?? $values['land'] ?? '')));
		$taxCountry = $settings['quote']['tax_country'] ?? null;
		if(!is_string($taxCountry) || !preg_match('/^[A-Z]{2}$/D', $taxCountry) || $country !== $taxCountry) $result['reasons'][] = 'tax_review_required';
		if(isset($values['lieferadresse_abweichend']) && !in_array($values['lieferadresse_abweichend'], ['ja', 'nein'], true)) $result['reasons'][] = 'delivery_review_required';
		if(($values['lieferadresse_abweichend'] ?? '') === 'ja' && strtoupper(trim((string)($values['lieferland'] ?? ''))) !== $taxCountry) $result['reasons'][] = 'tax_review_required';
		$result['reasons'] = array_values(array_unique($result['reasons']));
		$result['options'] = array_values(array_unique($result['options']));
		$result['eligible'] = !$result['reasons'];
		return $result;
	}

	private function evaluateCatalog(array $config, array $selection, array $values, array $result): array
	{
		$catalog = Shops_Model_FormConfig::catalogSettings($config, $selection);
		$result['inputs'] = [];
		$result['reasons'] = ['catalog_suggestions_only', 'technical_review_required', 'commercial_review_required'];
		$result['limit'] = $catalog['limit'];
		$result['item_field'] = $catalog['item_field'];
		$result['scanned'] = 0;
		$result['has_more'] = false;
		$result['summary'] = [];
		$needed = [];
		foreach($config as $step => $fields) {
			foreach($fields as $field) $needed[$field['name']] = $field;
			if((string)$step === $catalog['step']) break;
		}
		$form = $needed ? new Shops_Form_DynamicForm(['inputs' => array_values($needed)], 'inputs') : null;
		if($form) {
			$countries = null;
			foreach($form->getElements() as $name => $element) if($element['source'] === 'country') {
				$countries ??= (new Shops_Model_DbTable_Country())->getCountries();
				$form->addOptions($name, ['' => $element['options'][''] ?? 'Bitte wählen'] + $countries, 'replace');
			}
			$valid = $form->isValid($values);
			$result['inputs'] = array_intersect_key(array_diff_key($form->getFilteredValues(), $form->getErrors()), $needed);
		} else $valid = true;
		foreach($catalog['filters'] as $filter) {
			$condition = $filter + ['label' => $filter['attribute']];
			unset($condition['value_map'], $condition['values']);
			$condition['status'] = 'ready';
			if(isset($filter['field'])) {
				$name = $filter['field'];
				if(!$form->isFieldActive($name, $values)) $condition['status'] = 'inactive';
				$input = $result['inputs'][$name] ?? null;
				if($condition['status'] !== 'inactive' && ($input === null || $input === '')) $condition['status'] = array_diff($form->getErrors()[$name] ?? [], ['required']) ? 'invalid_input' : 'missing_input';
				if(isset($filter['value_map']) && is_scalar($input)) $input = $filter['value_map'][(string)$input] ?? null;
				$expected = [$input];
			} else $expected = $filter['values'];
			$condition['expected'] = array_map(fn($value) => $this->catalogValue($value, $filter['type']), $expected);
			if($condition['status'] === 'ready' && in_array(null, $condition['expected'], true)) $condition['status'] = 'invalid_input';
			if(!in_array($condition['status'], ['ready', 'inactive'], true)) $valid = false;
			$result['conditions'][] = $condition;
		}
		if(!$valid) {
			$result['reasons'][] = 'catalog_inputs_incomplete';
			return $result;
		}
		$context = Zend_Registry::get('SiteContext');
		if(!$context->hasFeature('catalog')) {
			$result['reasons'][] = 'catalog_unavailable';
			return $result;
		}
		$categories = (new Shops_Model_DbTable_Category())->getCategories();
		$ids = $catalog['categories'];
		foreach($ids as $id) if(!isset($categories[$id])) {
			$result['reasons'][] = 'catalog_category_unavailable';
			return $result;
		}
		if($catalog['include_subcategories']) {
			for($index = 0; $index < count($ids); $index++) foreach($categories[$ids[$index]]['childs'] ?? [] as $id) {
				if(!in_array($id, $ids, true)) $ids[] = $id;
			}
		}
		$items = (new Shops_Model_DbTable_Item())->getInquiryItems($ids);
		$result['has_more'] = count($items) > Shops_Model_DbTable_Item::INQUIRY_SCAN_LIMIT;
		$items = array_slice($items, 0, Shops_Model_DbTable_Item::INQUIRY_SCAN_LIMIT);
		$result['scanned'] = count($items);
		$rows = (new Shops_Model_DbTable_Itematr())->getInquiryAttributes(array_column($items, 'id'), array_column($catalog['filters'], 'attribute'));
		if(count($rows) > Shops_Model_DbTable_Itematr::INQUIRY_SCAN_LIMIT) {
			$result['reasons'][] = 'catalog_attribute_limit';
			$result['has_more'] = true;
			return $result;
		}
		$attributes = [];
		foreach($rows as $row) $attributes[$row['parentid']][$row['title']][] = $row;
		foreach($items as $item) {
			$checks = [];
			$matches = true;
			foreach($result['conditions'] as $condition) {
				$check = $condition;
				if($condition['status'] !== 'inactive') {
					$found = $attributes[$item['id']][$condition['attribute']] ?? [];
					if(isset($condition['set_id'])) $found = array_values(array_filter($found, static fn(array $row): bool => (int)$row['atrsetid'] === (int)$condition['set_id']));
					$check['status'] = !$found ? 'missing_attribute' : (count($found) > 1 ? 'ambiguous_attribute' : 'matched');
					if(count($found) === 1) {
						$check['actual'] = $this->catalogValue($found[0][$condition['column']], $condition['type']);
						$check['actual_unit'] = trim((string)$found[0]['uom']);
						if($check['actual_unit'] !== $condition['unit']) $check['status'] = 'unit_mismatch';
						elseif($check['actual'] === null) $check['status'] = 'invalid_attribute';
						else {
							$actual = $check['actual'];
							$expected = $condition['expected'];
							$match = $condition['operator'] === 'eq' ? in_array($actual, $expected, true) : ($condition['operator'] === 'gte' ? $actual >= $expected[0] : $actual <= $expected[0]);
							if(!$match) $check['status'] = 'not_matched';
						}
					}
					if($check['status'] !== 'matched') {
						$matches = false;
						$result['summary'][$check['status']] = ($result['summary'][$check['status']] ?? 0) + 1;
					}
				}
				$checks[] = $check;
			}
			$record = ['itemid' => (int)$item['id'], 'sku' => (string)$item['sku'], 'title' => (string)$item['title'], 'conditions' => $checks,
				'technical' => ['status' => 'review_required'], 'commercial' => ['status' => 'review_required']];
			$key = $matches ? 'candidates' : 'rejected';
			if(count($result[$key]) < $catalog['limit']) $result[$key][] = $record;
			elseif($matches) $result['has_more'] = true;
		}
		$media = new Shops_Model_DbTable_Media();
		foreach($result['candidates'] as &$candidate) {
			$candidate['images'] = array_slice(array_values(array_filter($media->getMedia($candidate['itemid'], 'items', 'item'), static fn(array $row): bool => $row['type'] === 'image')), 0, 1);
		}
		unset($candidate);
		if(!$result['candidates']) $result['reasons'][] = 'catalog_no_matches';
		$id = $values[$catalog['item_field']] ?? '';
		if($id !== '' && $id !== null) {
			if((is_string($id) || is_int($id)) && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) foreach($result['candidates'] as $candidate) {
				if($candidate['itemid'] === (int)$id) $result['selected'] = array_intersect_key($candidate, array_flip(['itemid', 'sku', 'title', 'conditions']));
			}
		}
		return $result;
	}

	private function catalogValue($value, string $type)
	{
		if(!is_scalar($value) || is_bool($value) || trim((string)$value) === '' || strlen((string)$value) > 1000) return null;
		return $type === 'number' ? self::decimal($value) : trim((string)$value);
	}

	public function resolveOptions(array $item, array $optionIds, string $currency): array
	{
		$optionDb = new Shops_Model_DbTable_Itemopt();
		$parentId = (int)(!empty($item['parentid']) ? $item['parentid'] : $item['id']);
		$includedIds = [];
		if(!empty($item['parentid'])) {
			if(!(new Shops_Model_DbTable_Item())->getItem($parentId)) throw new UnexpectedValueException('Variant parent is unavailable');
			$includedIds = $optionDb->getAdapter()->fetchCol('SELECT itemoptid FROM itemvariantopt WHERE itemid = ? AND clientid = ? AND deleted = 0', [(int)$item['id'], $optionDb->getClientId()]);
		}
		$options = [];
		foreach(array_unique(array_merge($optionIds, $includedIds)) as $id) {
			$option = $optionDb->getPublicById((int)$id);
			if(!$option || (int)$option['parentid'] !== $parentId) throw new UnexpectedValueException('Selected option is unavailable');
			if(!empty($option['optsetid'])) {
				$set = (new Shops_Model_DbTable_Itemoptset())->getPublicById((int)$option['optsetid']);
				if(!$set || (int)$set['parentid'] !== $parentId) throw new UnexpectedValueException('Selected option group is unavailable');
			}
			$included = in_array((string)$id, array_map('strval', $includedIds), true);
			$price = self::decimal($option['price']);
			if($price === null || $price < 0 || (!empty($option['currency']) && $option['currency'] !== $currency)) throw new UnexpectedValueException('Option pricing needs review');
			if($option['taxrate'] !== null && (self::decimal($option['taxrate']) === null || (float)$option['taxrate'] < 0)) throw new UnexpectedValueException('Option tax rate needs review');
			$option['included'] = $included;
			$options[] = $option;
		}
		return $options;
	}

	public static function decimal($value): ?float
	{
		if(is_int($value) || is_float($value)) return is_finite((float)$value) ? (float)$value : null;
		if(!is_string($value)) return null;
		$value = trim((string)$value);
		if(!preg_match('/^[+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+)$/D', $value)) return null;
		$number = (float)str_replace(',', '.', $value);
		return is_finite($number) ? $number : null;
	}
}
