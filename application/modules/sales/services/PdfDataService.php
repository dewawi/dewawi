<?php

class Sales_Service_PdfDataService
{
	public function build(array $document, string $controller, array $options = []): array
	{
		$locale = Zend_Registry::get('Zend_Locale');

		if (!empty($options['ensureDocumentId'])) {
			$document = $this->ensureNumberAndFilename($document, $controller);
		}

		if (!empty($document['language'])) {
			$translate = new Zend_Translate('array', BASE_PATH . '/languages/' . $document['language']);
			Zend_Registry::set('Zend_Translate', $translate);
		}

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

		list($positions, $document, $optionsList, $optionSets) = $this->getPositions($document, $locale);

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
			'calculations' => Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')
				->direct($document['id'], date('Y-m-d H:i:s'), Zend_Registry::get('User')['id'], $document['taxfree']),
		];
	}

	protected function ensureNumberAndFilename(array $document, $controller): array
	{
		$docIdField = $this->getDocumentIdField($controller);

		if (!empty($document[$docIdField])) {
			return $document;
		}

		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement($docIdField);

		$filenameDb = new Application_Model_DbTable_Filename();
		$filename = $filenameDb->getFilename($controller, $document['language']);
		$filename = str_replace('%NUMBER%', $increment, $filename);

		$documentDb = $this->getDocumentDb($controller);
		$document[$docIdField] = $increment;
		$document['filename'] = $filename;
		$document['state'] = 105;
		$documentDb->updateById($document['id'], $document);
		$incrementDb->setIncrement($increment, $docIdField);

		return $documentDb->getById($document['id']);
	}

	protected function getPositions(array $document, $locale): array
	{
		$positionsHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Positions');
		return $positionsHelper->getPositions($document['id'], $document, $locale);
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

	protected function getDocumentIdField(string $controller): string
	{
		return $controller . 'id';
	}

	protected function getDocumentDb(string $controller)
	{
		$class = 'Sales_Model_DbTable_' . ucfirst($controller);
		return new $class();
	}
}
