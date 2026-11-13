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
		$result = ['eligible' => false, 'method' => $selection['method'] ?? 'form_calc', 'reasons' => [], 'options' => []];
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
		$contactFields = ['sku', 'titel', 'title', 'quantity', 'firma', 'company', 'ansprechpartner', 'firstname', 'lastname', 'vorname', 'nachname', 'name', 'email', 'telefon', 'phone', 'adresse', 'street', 'plz', 'postcode', 'stadt', 'city', 'country', 'land', 'projektname', 'reference', 'privacy'];
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
		$result['reasons'] = array_values(array_unique($result['reasons']));
		$result['options'] = array_values(array_unique($result['options']));
		$result['eligible'] = !$result['reasons'];
		return $result;
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
