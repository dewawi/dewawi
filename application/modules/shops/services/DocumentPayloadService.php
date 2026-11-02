<?php

class Shops_Service_DocumentPayloadService
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

		return [
			'template' => $template,
			'document' => $document,
			'contact' => ['id' => (int)$document['contactid']],
			'positions' => $positions,
			'options' => [],
			'optionSets' => [],
			'attributesByGroup' => [],
			'footers' => (new Shops_Model_DbTable_Footer())->getFooters((int)$template['id']),
			'settings' => $this->buildPdfSettings($document),
		];
	}

	protected function buildPdfSettings(array $document): array
	{
		return [
			'showPrices' => isset($document['pdfshowprices']) ? (int)$document['pdfshowprices'] : 1,
			'showDiscounts' => isset($document['pdfshowdiscounts']) ? (int)$document['pdfshowdiscounts'] : 0,
			'showOptions' => 0,
			'showIncludedOptions' => 0,
			'showAttributes' => 0,
			'showTotals' => 1,
			'showFooter' => 1,
			'showHeader' => 1,
			'showCover' => isset($document['pdfshowcover']) ? (int)$document['pdfshowcover'] : 0,
			'showImages' => 0,
			'showPositionImages' => 0,
		];
	}
}
