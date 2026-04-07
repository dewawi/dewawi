<?php

class Purchases_Service_DocumentFinalizeService
{
	public function finalize(array $document, string $controller): array
	{
		$docIdField = $controller . 'id';
		$docDateField = $controller . 'date';

		if (!empty($document[$docIdField])) {
			return $document;
		}

		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement($docIdField);

		$filenameDb = new Application_Model_DbTable_Filename();
		$filename = $filenameDb->getFilename($controller, $document['language']);
		$filename = str_replace('%NUMBER%', $increment, $filename);

		$documentDb = $this->getDocumentDb($controller);

		$values = [
			$docIdField => $increment,
			'filename' => $filename,
			'state' => 105,
		];

		if (array_key_exists($docDateField, $document) && empty($document[$docDateField])) {
			$values[$docDateField] = date('Y-m-d');
		}

		$documentDb->updateById($document['id'], $values);
		$incrementDb->setIncrement($increment, $docIdField);

		return $documentDb->getById($document['id']);
	}

	protected function getDocumentDb(string $controller)
	{
		$class = 'Purchases_Model_DbTable_' . ucfirst($controller);
		return new $class();
	}
}
