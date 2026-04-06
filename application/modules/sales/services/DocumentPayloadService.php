<?php

class Sales_Service_DocumentPayloadService
{
	public function build(array $document, string $controller, array $options = []): array
	{
		$locale = Zend_Registry::get('Zend_Locale');

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($document['contactid']);

		if (!empty($options['templateid'])) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate((int)$options['templateid']);
		} elseif (!empty($document['templateid'])) {
			$templateDb = new Application_Model_DbTable_Template();
			$template = $templateDb->getTemplate((int)$document['templateid']);
		} else {
			$template = null;
		}

		$calculations = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')
				->direct($document['id'], date('Y-m-d H:i:s'), Zend_Registry::get('User')['id'], $document['taxfree']);

		$payment = $this->buildPaymentData($document, $calculations);

		$document['prepayment'] = $payment['prepayment_formatted'];
		$document['prepayment_raw'] = $payment['prepayment_raw'];
		$document['balance'] = $payment['balance_formatted'];
		$document['balance_raw'] = $payment['balance_raw'];

		list($positions, $document, $optionsList, $optionSets) = $this->getPositions($document, $locale, $controller);

		$itemData = $this->getItemData($positions);

		$footerDb = new Application_Model_DbTable_Footer();
		$footerTemplateId = !empty($options['templateid'])
			? (int)$options['templateid']
			: (int)$document['templateid'];
		$footers = $footerDb->getFooters($footerTemplateId);

		return [
			'template' => $template,
			'document' => $document,
			'contact' => $contact,
			'items' => $itemData['items'],
			'categories' => $itemData['categories'],
			'media' => $itemData['media'],
			'attributesByGroup' => $itemData['attributesByGroup'],
			'options' => $optionsList,
			'optionSets' => $optionSets,
			'positions' => $positions,
			'footers' => $footers,
			'calculations' => $calculations,
			'settings' => $this->buildPdfSettings($document, $controller),
		];
	}

	protected function getPositions(array $document, $locale, $controller): array
	{
		$positionsHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Positions');
		return $positionsHelper->getPositions($document['id'], $document, $locale, $controller);
	}

	protected function getItemData($positions): array
	{
		$items = [];
		$categories = [];
		$media = [];
		$attributesByGroup = [];

		$itemDb = new Items_Model_DbTable_Item();
		$categoryDb = new Application_Model_DbTable_Category();
		$attributeSetsDb = new Items_Model_DbTable_Itematrset();
		$attributesDb = new Items_Model_DbTable_Itematr();
		$mediaDb = new Application_Model_DbTable_Media();

		foreach ($positions as $position) {
			if (!$position->itemid) {
				continue;
			}

			$items[$position->itemid] = $itemDb->getItem($position->itemid);

			if (!empty($items[$position->itemid]['catid'])) {
				$categories[$position->itemid] = $categoryDb->getCategory($items[$position->itemid]['catid']);
			}

			$media[$position->itemid] = $mediaDb->getMediaByParentID($items[$position->itemid]['id'], 'items', 'item');

			$attributeSets = $attributeSetsDb->getPositionSets($position->itemid);

			foreach ($attributeSets as $attributeSetId => $attributeSet) {
				$attributesByGroup[$position->id][$attributeSetId] = [
					'title' => $attributeSet['title'],
					'description' => $attributeSet['description'],
					'attributes' => $attributesDb->getPositions($position->itemid, $attributeSet['id']),
				];
			}

			$otherAttributes = $attributesDb->getPositions($position->itemid, 0);
			if (count($otherAttributes)) {
				$attributesByGroup[$position->id][] = [
					'title' => 'Sonstiges',
					'description' => '',
					'attributes' => $otherAttributes,
				];
			}
		}

		return [
			'items' => $items,
			'categories' => $categories,
			'media' => $media,
			'attributesByGroup' => $attributesByGroup,
		];
	}

	protected function buildPaymentData(array $document, array $calculations): array
	{
		$locale = Zend_Registry::get('Zend_Locale');

		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currencyObj = $currencyHelper->getCurrency($document['currency'], 'USE_SYMBOL');

		$prepayment = (float)($document['prepayment'] ?? 0);
		$total = (float)($calculations['row']['total'] ?? $document['total'] ?? 0);
		$balance = max(0, $total - $prepayment);

		return [
			'prepayment_raw' => $prepayment,
			'balance_raw' => $balance,
			'prepayment_formatted' => $currencyObj->toCurrency($prepayment),
			'balance_formatted' => $currencyObj->toCurrency($balance),
		];
	}

	protected function buildPdfSettings(array $document, string $controller): array
	{
		$defaults = [
			'showPrices' => 1,
			'showDiscounts' => 0,
			'showOptions' => 1,
			'showIncludedOptions' => 1,
			'showAttributes' => 1,
			'showTotals' => 1,
			'showFooter' => 1,
			'showHeader' => 1,
			'showCover' => 1,
		];

		if ($controller === 'invoice') {
			$defaults['showOptions'] = 0;
			$defaults['showIncludedOptions'] = 0;
			$defaults['showAttributes'] = 0;
			$defaults['showCover'] = 0;
		}

		if ($controller === 'deliveryorder') {
			$defaults['showPrices'] = 0;
			$defaults['showDiscounts'] = 0;
			$defaults['showOptions'] = 0;
			$defaults['showIncludedOptions'] = 0;
			$defaults['showAttributes'] = 0;
			$defaults['showCover'] = 0;
		}

		return [
			'showPrices' => isset($document['pdfshowprices']) ? (int)$document['pdfshowprices'] : $defaults['showPrices'],
			'showDiscounts' => isset($document['pdfshowdiscounts']) ? (int)$document['pdfshowdiscounts'] : $defaults['showDiscounts'],
			'showOptions' => isset($document['pdfshowoptions']) ? (int)$document['pdfshowoptions'] : $defaults['showOptions'],
			'showIncludedOptions' => isset($document['pdfshowoptions']) ? (int)$document['pdfshowoptions'] : $defaults['showIncludedOptions'],
			'showAttributes' => isset($document['pdfshowattributes']) ? (int)$document['pdfshowattributes'] : $defaults['showAttributes'],
			'showTotals' => 1,
			'showFooter' => 1,
			'showHeader' => 1,
			'showCover' => isset($document['pdfshowcover']) ? (int)$document['pdfshowcover'] : $defaults['showCover'],
		];
	}
}
