<?php

class Shops_Service_DocumentPayloadService extends Sales_Service_DocumentPayloadService
{
	public function build(array $document, string $controller, array $options = []): array
	{
		if ($controller !== 'quote') throw new InvalidArgumentException('Unsupported shop document');

		$clientId = (int)($document['clientid'] ?? 0);
		if ($clientId <= 0 || $clientId !== (int)Zend_Registry::get('SiteContext')->getClientId()) {
			throw new RuntimeException('Invalid shop document context');
		}

		$templateDb = new Shops_Model_DbTable_Template();
		$templateId = !empty($options['templateid']) ? (int)$options['templateid'] : (int)($document['templateid'] ?? 0);
		$template = $templateId > 0 ? $templateDb->getTemplate($templateId) : null;
		if (!$template) $template = $templateDb->getPrimaryTemplate();
		$document['templateid'] = (int)$template['id'];

		$positions = (new Shops_Model_DbTable_Quotepos())->getPositions((int)$document['id']);
		$calculation = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')
			->calculate($document, $positions, 'sales', 'quote', $clientId);
		$currency = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency')->getCurrency($document['currency'], 'USE_SYMBOL');
		$locale = Zend_Registry::get('Zend_Locale');

		$document['subtotal'] = $currency->toCurrency((float)$document['subtotal']);
		$document['taxes'] = $currency->toCurrency((float)$document['taxes']);
		$document['total_raw'] = (float)$document['total'];
		$document['total'] = $currency->toCurrency((float)$document['total']);
		$document['prepayment_raw'] = 0.0;
		$document['prepayment'] = $currency->toCurrency(0);
		$document['balance_raw'] = $document['total_raw'];
		$document['balance'] = $currency->toCurrency($document['balance_raw']);
		$document['taxgroups'] = [];
		foreach ($calculation['locale']['taxes'] as $rate => $value) {
			if ($rate !== 'total') $document['taxgroups'][empty($document['taxfree']) ? $rate : 0] = $value;
		}
		$rates = array_unique(array_map(static fn($position): float => (float)$position['taxrate'], $positions->toArray()));
		$document['taxrate'] = count($rates) === 1 || !empty($document['taxfree'])
			? Zend_Locale_Format::toNumber(empty($document['taxfree']) ? reset($rates) : 0, ['precision' => 2, 'locale' => $locale])
			: null;

		foreach ($positions as $position) {
			$precision = floor($position->quantity) == $position->quantity ? 0 : (round($position->quantity, 2) == $position->quantity ? 2 : 4);
			$position->manufacturerid = $calculation['row'][$position->id]['price'];
			$position->total = $calculation['locale'][$position->id]['total'];
			$position->price = $calculation['locale'][$position->id]['price'];
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity, ['precision' => $precision, 'locale' => $locale]);
		}

		$itemData = $this->getItemData($positions);
		[$selectedOptions, $optionSets] = $this->getSelectedOptions($document);
		return array_merge($itemData, [
			'template' => $template,
			'document' => $document,
			'contact' => ['id' => (int)$document['contactid']],
			'positions' => $positions,
			'options' => $selectedOptions,
			'optionSets' => $optionSets,
			'footers' => (new Shops_Model_DbTable_Footer())->getFooters((int)$template['id']),
			'settings' => $this->buildPdfSettings($document, 'quote'),
		]);
	}

	protected function getItemData($positions): array
	{
		$itemDb = new Shops_Model_DbTable_Item();
		$setsDb = new Shops_Model_DbTable_Itematrset();
		$attributesDb = new Shops_Model_DbTable_Itematr();
		$mediaDb = new Shops_Model_DbTable_Media();
		$items = $media = $attributes = [];
		foreach($positions as $position) {
			$itemId = (int)$position->itemid;
			if($itemId <= 0 || !($item = $itemDb->getItem($itemId))) continue;
			$items[$itemId] = $item;
			$media[$itemId] = array_values(array_filter($mediaDb->getMedia($itemId, 'items', 'item'), static fn(array $row): bool => $row['type'] === 'image'));
			$attributes[$position->id] = $this->getAttributeGroups($itemId, $setsDb, $attributesDb);
		}
		return ['items' => $items, 'media' => $media, 'attributesByGroup' => $attributes];
	}

	protected function getSelectedOptions(array $document): array
	{
		$inquiry = (new Shops_Model_DbTable_Inquirydata())->getByQuoteId((int)$document['id']);
		$data = $inquiry ? json_decode((string)$inquiry['data'], true) : [];
		$options = $sets = [];
		$positions = (new Shops_Model_DbTable_Quotepos())->getPositions((int)$document['id'])->toArray();
		$positionIds = array_column($positions, 'id');
		foreach((array)($data['_quote']['included_options'] ?? []) as $positionId => $selected) {
			if(!$selected) continue;
			if(!in_array((string)$positionId, array_map('strval', $positionIds), true)) continue;
			$sets[$positionId] = new Zend_Db_Table_Rowset(['data' => [['id' => 0, 'title' => 'Gewählte Ausstattung']]]);
			$options[$positionId][0] = new Zend_Db_Table_Rowset(['data' => $selected]);
		}
		return [$options, $sets];
	}
}
