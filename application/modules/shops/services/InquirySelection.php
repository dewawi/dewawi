<?php

class Shops_Service_InquirySelection
{
	// Existing quote/option quantities use DECIMAL(12,4).
	private const POSITION_QUANTITY_MAX = 99999999.9999;

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
		if(in_array($selection['method'] ?? '', ['catalog', 'variant_config'], true)) $config = Shops_Model_FormConfig::normalize($config);
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
		if($result['method'] === 'variant_config') return $this->evaluateVariant($config, $selection, $values, $result);
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

	public function variantFormConfig(array $config, array $selection, array $values): array
	{
		$settings = Shops_Model_FormConfig::variantSettings($config, $selection);
		$data = $this->variantData($settings, $values);
		return $this->hydrateVariantFields($config, $settings, $data);
	}

	private function hydrateVariantFields(array $config, array $settings, array $data): array
	{
		$options = [$settings['base_field'] => ['' => 'Bitte wählen']];
		foreach($data['bases'] as $value => $base) $options[$settings['base_field']][$value] = $base['sku'].' – '.$base['title'];
		// Fields belonging only to another family remain empty and cannot accept stale values.
		foreach($settings['bases'] as $base) foreach($base['option_fields'] ?? [] as $name => $mapping) $options[$name] = ['' => 'Bitte wählen'];
		foreach($data['mapping']['option_fields'] ?? [] as $name => $mapping) foreach($mapping['choices'] as $value => $id) {
			if($id === null) $options[$name][$value] = 'Keine zusätzliche Auswahl';
			elseif(isset($data['options'][$id]) && (int)$data['options'][$id]['optsetid'] === (int)$mapping['group_id']) $options[$name][$value] = $data['options'][$id]['title'];
		}
		foreach($config as &$fields) {
			foreach($fields as $index => &$field) {
				$name = $field['name'];
				if($name === $settings['quantity_field']) $field['attribs']['data-variant-choice'] = '1';
				if(!isset($options[$name])) continue;
				if($name !== $settings['base_field'] && !isset($data['mapping']['option_fields'][$name])) { unset($fields[$index]); continue; }
				$field['options'] = $options[$name];
				$field['attribs']['data-variant-choice'] = '1';
			}
			unset($field);
			$fields = array_values($fields);
		}
		unset($fields);
		return $config;
	}

	private function variantData(array $settings, array $values): array
	{
		$data = ['bases' => [], 'base' => null, 'mapping' => [], 'options' => [], 'groups' => [], 'variants' => [], 'relations' => [], 'reasons' => []];
		if(!Zend_Registry::get('SiteContext')->hasFeature('catalog')) {
			$data['reasons'][] = 'variant_catalog_unavailable';
			return $data;
		}
		$items = new Shops_Model_DbTable_Item();
		$categories = (new Shops_Model_DbTable_Category())->getCategories();
		$public = static fn(?array $item): bool => $item && (int)$item['shopenabled'] === 1 && isset($categories[$item['shopcatid']]) && trim((string)$item['sku']) !== '';
		$bases = array_column($items->getInquiryBases(array_column($settings['bases'], 'itemid')), null, 'id');
		foreach($settings['bases'] as $value => $mapping) {
			$item = $bases[(int)$mapping['itemid']] ?? null;
			if($public($item) && empty($item['parentid'])) $data['bases'][$value] = $item;
		}
		$value = $values[$settings['base_field']] ?? '';
		if(!is_scalar($value) || !isset($data['bases'][(string)$value])) {
			$data['reasons'][] = 'variant_base_unavailable';
			return $data;
		}
		$data['base'] = $data['bases'][(string)$value];
		$data['mapping'] = $settings['bases'][(string)$value];
		$baseId = (int)$data['base']['id'];
		$groups = (new Shops_Model_DbTable_Itemoptset())->getInquiryGroups($baseId);
		$options = (new Shops_Model_DbTable_Itemopt())->getInquiryOptions($baseId);
		$variants = $items->getInquiryVariants($baseId);
		if(count($groups) > Shops_Model_DbTable_Itemoptset::INQUIRY_GROUP_LIMIT || count($options) > Shops_Model_DbTable_Itemopt::INQUIRY_OPTION_LIMIT || count($variants) > Shops_Model_DbTable_Item::INQUIRY_VARIANT_LIMIT) {
			$data['reasons'][] = 'variant_scan_limit';
			return $data;
		}
		foreach($groups as $group) $data['groups'][(int)$group['id']] = $group;
		foreach($options as $option) if(isset($data['groups'][(int)$option['optsetid']])) $data['options'][(int)$option['id']] = $option;
		foreach($variants as $variant) if($public($variant)) $data['variants'][(int)$variant['id']] = $variant;
		$relations = (new Shops_Model_DbTable_Itemvariantopt())->getInquiryRelations(array_keys($data['variants']));
		if(count($relations) > Shops_Model_DbTable_Itemvariantopt::INQUIRY_RELATION_LIMIT) {
			$data['variants'] = [];
			$data['reasons'][] = 'variant_scan_limit';
			return $data;
		}
		foreach($relations as $relation) $data['relations'][(int)$relation['itemid']][] = (int)$relation['itemoptid'];
		return $data;
	}

	private function evaluateVariant(array $config, array $selection, array $values, array $result): array
	{
		$settings = Shops_Model_FormConfig::variantSettings($config, $selection);
		$data = $this->variantData($settings, $values);
		$result['reasons'] = array_merge(['technical_review_required', 'commercial_review_required'], $data['reasons']);
		$result['variant_field'] = $settings['variant_field'];
		$result['base'] = $data['base'] ? $this->variantIdentity($data['base']) : null;
		$result['invalid'] = false;
		$result['inputs'] = [];
		$hydrated = $this->hydrateVariantFields($config, $settings, $data);
		$valid = true;
		foreach($hydrated as $step => $fields) {
			$form = new Shops_Form_DynamicForm([$step => $fields], $step);
			$this->hydrateCountries($form);
			$valid = $form->isValid($values) && $valid;
			$result['inputs'] += array_intersect_key(array_diff_key($form->getFilteredValues(), $form->getErrors()), array_column($fields, null, 'name'));
			if((string)$step === $settings['step']) break;
		}
		if(!$data['base'] || $data['reasons']) {
			$result['invalid'] = true;
			return $result;
		}
		$mapping = $data['mapping'];
		$target = array_map('intval', $mapping['included_options'] ?? []);
		$extraIds = $requested = $rules = $known = [];
		$complete = $valid;
		foreach($target as $id) $known[$id] = true;
		foreach($mapping['option_fields'] ?? [] as $name => $field) {
			$groupId = (int)$field['group_id'];
			$rule = $field['exclusivity'] ?? 'unknown';
			if(isset($rules[$groupId]) && $rules[$groupId] !== $rule) $result['reasons'][] = 'variant_group_rules_conflict';
			$rules[$groupId] = $rule;
			foreach($field['choices'] as $id) if($id !== null) {
				$known[(int)$id] = true;
				if($field['role'] === 'additional') $extraIds[] = (int)$id;
			}
			$value = $result['inputs'][$name] ?? '';
			if($value === '' || $value === null) {
				$complete = false;
				if(isset($values[$name]) && $values[$name] !== '' && $values[$name] !== null) $result['invalid'] = true;
				continue;
			}
			$id = $field['choices'][(string)$value] ?? null;
			if($id === null) continue;
			if(!isset($data['options'][$id]) || (int)$data['options'][$id]['optsetid'] !== $groupId) {
				$result['invalid'] = true;
				$complete = false;
				continue;
			}
			if(isset($requested[(int)$id])) $result['reasons'][] = 'variant_duplicate_option';
			$requested[(int)$id] = $field;
			if($field['role'] === 'variant') $target[] = (int)$id;
		}
		foreach($settings['bases'] as $base) foreach($base['option_fields'] ?? [] as $name => $field) if(!isset($mapping['option_fields'][$name]) && isset($values[$name]) && $values[$name] !== '' && $values[$name] !== null) $result['invalid'] = true;
		$target = array_values(array_unique($target));
		sort($target);
		foreach(array_keys($requested) as $id) {
			$option = $data['options'][$id];
			$result['options'][] = ['itemoptid' => $id, 'itemid' => null, 'group_id' => (int)$option['optsetid'], 'sku' => (string)$option['sku'], 'title' => (string)$option['title'], 'included' => null, 'price' => null, 'status' => 'review_required'];
		}
		$matches = [];
		foreach($data['variants'] as $id => $variant) {
			$included = $data['relations'][$id] ?? [];
			if(array_diff($included, array_keys($data['options'])) || array_diff($included, array_keys($known)) || count($included) !== count(array_unique($included))) {
				$result['reasons'][] = 'variant_relations_unverified';
				continue;
			}
			$core = array_values(array_diff($included, $extraIds));
			sort($core);
			$fieldValues = [];
			$configurable = !array_diff(array_map('intval', $mapping['included_options'] ?? []), $included);
			foreach($mapping['option_fields'] ?? [] as $name => $field) {
				$choices = array_keys(array_filter($field['choices'], static fn($option): bool => $option !== null && in_array((int)$option, $included, true) && (int)$data['options'][$option]['optsetid'] === (int)$field['group_id']));
				if(count($choices) === 1) $fieldValues[$name] = (string)$choices[0];
				elseif($field['role'] === 'variant') {
					$none = array_keys($field['choices'], null, true);
					if(!$choices && count($none) === 1) $fieldValues[$name] = (string)$none[0];
					else $configurable = false;
				}
			}
			$candidate = $this->variantIdentity($variant) + ['included_options' => $included, 'option_labels' => array_map(static fn($option): string => (string)$data['options'][$option]['title'], $included), 'field_values' => $fieldValues, 'configurable' => $configurable,
				'commercial' => ['status' => 'review_required'], 'technical' => ['status' => 'review_required']];
			$result['candidates'][] = $candidate;
			if($complete && $configurable && $core === $target) $matches[$id] = $variant;
		}
		$explicit = $values[$settings['variant_field']] ?? '';
		if($explicit !== '' && $explicit !== null && ((!is_string($explicit) && !is_int($explicit)) || !filter_var($explicit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || !isset($matches[(int)$explicit]))) $result['invalid'] = true;
		if(!$complete) $result['reasons'][] = 'variant_inputs_incomplete';
		elseif(!$matches) $result['reasons'][] = 'variant_no_match';
		elseif(count($matches) > 1) $result['reasons'][] = 'variant_ambiguous';
		elseif(!$result['invalid']) {
			$variant = reset($matches);
			$result['selected'] = $this->variantIdentity($variant);
			$included = $data['relations'][(int)$variant['id']] ?? [];
			$chosen = array_values(array_unique(array_merge($included, array_keys($requested))));
			$groups = [];
			foreach($chosen as $id) $groups[(int)$data['options'][$id]['optsetid']][] = $id;
			foreach($groups as $group => $ids) {
				if(($rules[$group] ?? 'unknown') === 'unknown') $result['reasons'][] = 'variant_group_rules_unknown';
				if(($rules[$group] ?? '') === 'single' && count($ids) > 1) $result['reasons'][] = 'variant_group_conflict';
			}
			if(array_intersect($result['reasons'], ['variant_group_conflict', 'variant_group_rules_conflict', 'variant_duplicate_option'])) $result['selected'] = null;
			else $result = $this->variantPrices($variant, $chosen, $included, $requested, $data, $settings, $result);
		}
		if($result['invalid']) $result['reasons'][] = 'variant_selection_invalid';
		$result['reasons'] = array_values(array_unique($result['reasons']));
		return $result;
	}

	private function variantIdentity(array $item): array
	{
		return ['itemid' => (int)$item['id'], 'parentid' => (int)$item['parentid'], 'sku' => (string)$item['sku'], 'title' => (string)$item['title']];
	}

	private function variantPrices(array $variant, array $chosen, array $included, array $requested, array $data, array $settings, array $result): array
	{
		$result['options'] = [];
		try { $currency = (string)(new Shops_Model_DbTable_Currency())->getPrimaryCurrency()['code']; }
		catch(RuntimeException $e) { $currency = ''; }
		$owner = (int)Zend_Registry::get('SiteContext')->getClientId();
		$uoms = (new Application_Model_DbTable_Uom())->setClientId($owner)->getUoms();
		$tax = (new Shops_Model_DbTable_Taxrate())->getTaxRate((int)$variant['taxid']);
		$rate = self::decimal($tax['rate'] ?? null);
		$uom = trim((string)($uoms[(int)$variant['uomid']] ?? ''));
		$quantity = $settings['quantity_field'] === null ? 1.0 : self::decimal($result['inputs'][$settings['quantity_field']] ?? null);
		if($quantity !== null && ($quantity <= 0 || $quantity > self::POSITION_QUANTITY_MAX)) $quantity = null;
		$list = self::decimal($variant['price']);
		$special = $variant['specialprice'] === null ? 0.0 : self::decimal($variant['specialprice']);
		$price = $special !== null && $special > 0 ? $special : $list;
		$valid = $list !== null && $list >= 0 && $special !== null && $special >= 0 && $price !== null && $quantity !== null && $quantity > 0
			&& preg_match('/^[A-Z]{3}$/D', $currency) && $variant['currency'] === $currency && $rate !== null && $rate >= 0 && $rate <= 100 && $uom !== '';
		$positions = [];
		$base = ['itemid' => (int)$variant['id'], 'itemoptid' => null, 'included' => false, 'price' => $price, 'quantity' => $quantity, 'uom' => $uom, 'currency' => (string)$variant['currency'], 'taxrate' => $rate,
			'price_source' => $special > 0 ? 'item.specialprice' : 'item.price', 'list_price' => $list, 'status' => $valid ? 'priced' : 'review_required'];
		$positions[] = $base;
		$priceRules = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');
		foreach($chosen as $id) {
			$option = $data['options'][$id];
			$isIncluded = in_array($id, $included, true);
			$optionPrice = self::decimal($option['price']);
			$multiplier = $option['quantity'] === null ? 1.0 : self::decimal($option['quantity']);
			$lineQuantity = $quantity !== null && $multiplier !== null ? $quantity * $multiplier : null;
			if($lineQuantity !== null && (!is_finite($lineQuantity) || $lineQuantity <= 0 || $lineQuantity > self::POSITION_QUANTITY_MAX)) $lineQuantity = null;
			$optionRate = $option['taxrate'] === null ? $rate : self::decimal($option['taxrate']);
			$optionUnit = trim((string)$option['uom']) ?: $uom;
			$action = (string)$option['priceruleaction'];
			$amount = $option['priceruleamount'] === null ? 0.0 : self::decimal($option['priceruleamount']);
			$available = $optionPrice !== null && $optionPrice >= 0;
			$priced = $available && $lineQuantity !== null && $optionRate !== null && $optionRate >= 0 && $optionRate <= 100
				&& ($option['currency'] === null || $option['currency'] === '' || $option['currency'] === $currency) && $optionUnit !== '' && in_array($optionUnit, $uoms, true)
				&& empty($option['masterid']) && $amount !== null && ($action === '' || in_array($action, ['bypercent', 'byfixed', 'topercent', 'tofixed'], true));
			if($priced && $action !== '') $optionPrice = $priceRules->usePriceRules([['action' => $action, 'amount' => $amount]], $optionPrice);
			if($optionPrice !== null && (!is_finite($optionPrice) || $optionPrice < 0)) $priced = false;
			$accessoryId = (int)$option['itemid'];
			if($accessoryId > 0) {
				$accessory = (new Shops_Model_DbTable_Item())->getItem($accessoryId);
				if(!$accessory || !(int)$accessory['shopenabled'] || !(new Shops_Model_DbTable_Category())->getCategory((int)$accessory['shopcatid'])) { $priced = false; $accessoryId = 0; }
			}
			if(!$isIncluded && (($requested[$id]['compatibility_verified'] ?? false) !== true || !isset($requested[$id]['exclusivity']))) $priced = false;
			$record = ['itemoptid' => $id, 'itemid' => $accessoryId ?: null, 'variantid' => (int)$variant['id'], 'group_id' => (int)$option['optsetid'],
				'sku' => (string)$option['sku'], 'title' => (string)$option['title'], 'included' => $isIncluded, 'catalog_price' => self::decimal($option['price']),
				'price' => $isIncluded ? 0.0 : ($priced ? $optionPrice : null), 'quantity' => $lineQuantity,
				'uom' => $optionUnit, 'currency' => $option['currency'] ?: $currency, 'taxrate' => $optionRate, 'price_source' => $isIncluded ? $base['price_source'].'_included' : 'itemopt.price',
				'price_rule' => ['action' => $action, 'amount' => $amount], 'status' => $priced ? ($isIncluded ? 'included' : 'priced') : ($optionPrice === -2.0 ? 'unavailable' : ($optionPrice === -1.0 ? 'on_request' : 'review_required'))];
			$result['options'][] = $record;
			$positions[] = $record;
			if(!$priced) { $valid = false; $result['reasons'][] = 'variant_option_pricing_review'; }
		}
		$result['commercial'] += ['pricing_status' => 'review_required', 'currency' => $currency, 'positions' => $positions];
		if(!$valid) { $result['reasons'][] = 'variant_pricing_review'; return $result; }
		$rows = [];
		foreach($positions as $index => $position) {
			if(!is_finite($position['price'] * $position['quantity'])) { $result['reasons'][] = 'variant_pricing_review'; return $result; }
			// These transient rows have no persisted position rules. Option rules were applied above.
			$rows[] = (object)($position + ['id' => $index + 1, 'masterid' => -1, 'pricerulemaster' => 0]);
		}
		$calculated = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->calculate(['currency' => $currency, 'taxfree' => 0], $rows, 'shops', 'quote', $owner);
		$result['commercial']['pricing_status'] = 'complete';
		$result['commercial']['totals'] = array_intersect_key($calculated['row'], array_flip(['subtotal', 'taxes', 'total']));
		return $result;
	}

	private function hydrateCountries(Shops_Form_DynamicForm $form): void
	{
		$countries = null;
		foreach($form->getElements() as $name => $element) if($element['source'] === 'country') {
			$countries ??= (new Shops_Model_DbTable_Country())->getCountries();
			$form->addOptions($name, ['' => $element['options'][''] ?? 'Bitte wählen'] + $countries, 'replace');
		}
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
			$this->hydrateCountries($form);
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
