<?php

class DEEC_Document_FinalizeValidator
{
	protected const DOCUMENTS = [
		'sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'],
		'purchases' => ['quoterequest', 'purchaseorder'],
	];

	protected const DELIVERY_DATE_DOCUMENTS = ['quote', 'salesorder', 'deliveryorder', 'quoterequest', 'purchaseorder'];

	public function validate(array $document, string $module, string $controller): array
	{
		if (!in_array($controller, self::DOCUMENTS[$module] ?? [], true)) return $this->result([], []);

		$errors = [];
		$warnings = [];
		$contactKey = $module === 'purchases'
			? 'DOCUMENT_VALIDATION_SUPPLIER_REQUIRED'
			: 'DOCUMENT_VALIDATION_CUSTOMER_REQUIRED';

		if (empty($document['contactid'])) {
			$errors[] = $this->issue($contactKey, 'customer', 'contactid');
		} elseif (!$this->isValidContact((int)$document['contactid'])) {
			$invalidContactKey = $module === 'purchases'
				? 'DOCUMENT_VALIDATION_SUPPLIER_INVALID'
				: 'DOCUMENT_VALIDATION_CUSTOMER_INVALID';
			$errors[] = $this->issue($invalidContactKey, 'customer', 'contactid');
		}

		$documentFormClass = ucfirst($module) . '_Form_' . ucfirst($controller);
		$documentForm = class_exists($documentFormClass) ? new $documentFormClass() : null;
		$currencyInvalid = trim((string)($document['currency'] ?? '')) === '';
		if ($documentForm) {
			$documentForm->isValidPartial(['currency' => $document['currency'] ?? null]);
			$currencyInvalid = !empty($documentForm->getErrors()['currency']);
		}
		if ($currencyInvalid) {
			$errors[] = $this->issue('DOCUMENT_VALIDATION_CURRENCY_REQUIRED', 'overview', 'currency');
		}

		if (trim((string)($document['language'] ?? '')) === '') {
			$errors[] = $this->issue('DOCUMENT_VALIDATION_LANGUAGE_REQUIRED', 'finish', 'language');
		}

		$errors = array_merge($errors, $this->validatePositions($document, $module, $controller));

		if (in_array($controller, self::DELIVERY_DATE_DOCUMENTS, true) && empty($document['deliverydate'])) {
			$warnings[] = $this->issue('DOCUMENT_VALIDATION_DELIVERY_DATE_MISSING', 'overview', 'deliverydate');
		}

		if ($controller === 'reminder' && empty($document['invoiceid'])) {
			$warnings[] = $this->issue('DOCUMENT_VALIDATION_INVOICE_REFERENCE_MISSING', 'overview', 'invoiceid');
		}

		return $this->result($errors, $warnings);
	}

	protected function isValidContact(int $contactId): bool
	{
		if ($contactId <= 0 || !Zend_Registry::isRegistered('Client')) return false;
		$client = (array)Zend_Registry::get('Client');
		$clientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)($client['id'] ?? 0);
		if ($clientId <= 0) return false;
		$db = (new Contacts_Model_DbTable_Contact())->setClientId($clientId);
		return (bool)$db->getContactWithID($contactId);
	}

	public function assertValid(array $document, string $module, string $controller): array
	{
		$validation = $this->validate($document, $module, $controller);
		if (!$validation['canFinalize']) throw new DEEC_Document_ValidationException($validation);
		return $validation;
	}

	protected function validatePositions(array $document, string $module, string $controller): array
	{
		$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller) . 'pos';
		if (!class_exists($class)) return [$this->issue('DOCUMENT_VALIDATION_POSITIONS_UNAVAILABLE', 'positions')];

		$positions = (new $class())->getPositions((int)$document['id']);
		if (count($positions) === 0) return [$this->issue('DOCUMENT_VALIDATION_POSITION_REQUIRED', 'positions')];

		$errors = [];
		$number = 0;
		$formClass = ucfirst($module) . '_Form_' . ucfirst($controller) . 'pos';
		$form = class_exists($formClass) ? new $formClass() : null;
		foreach ($positions as $position) {
			$number++;
			$row = method_exists($position, 'toArray') ? $position->toArray() : (array)$position;
			$positionId = (int)($row['id'] ?? 0);
			$requiredErrors = [];
			if ($form) {
				$form->isValid($row);
				$requiredErrors = $form->getErrors();
			}

			if (!empty($requiredErrors['sku']) || trim((string)($row['sku'] ?? '')) === '') {
				$errors[] = $this->positionIssue('DOCUMENT_VALIDATION_POSITION_SKU_REQUIRED', $number, $positionId, 'sku');
			}
			if (!empty($requiredErrors['price']) || !isset($row['price']) || !is_numeric($row['price'])) {
				$errors[] = $this->positionIssue('DOCUMENT_VALIDATION_POSITION_PRICE_INVALID', $number, $positionId, 'price');
			}
			if (!empty($requiredErrors['taxrate']) || !isset($row['taxrate']) || !is_numeric($row['taxrate'])) {
				$errors[] = $this->positionIssue('DOCUMENT_VALIDATION_POSITION_TAXRATE_INVALID', $number, $positionId, 'taxrate');
			}
			if (!empty($requiredErrors['quantity']) || !isset($row['quantity']) || !is_numeric($row['quantity']) || (float)$row['quantity'] == 0.0) {
				$errors[] = $this->positionIssue('DOCUMENT_VALIDATION_POSITION_QUANTITY_INVALID', $number, $positionId, 'quantity');
			}
			if (!empty($requiredErrors['uom']) || !isset($row['uom']) || $row['uom'] === '') {
				$errors[] = $this->positionIssue('DOCUMENT_VALIDATION_POSITION_UOM_REQUIRED', $number, $positionId, 'uom');
			}
		}

		return $errors;
	}

	protected function issue(string $message, string $tab, ?string $field = null, array $args = []): array
	{
		return [
			'message' => $message,
			'args' => $args,
			'tab' => $tab,
			'field' => $field,
			'positionId' => null,
		];
	}

	protected function positionIssue(string $message, int $number, int $positionId, string $field): array
	{
		$issue = $this->issue($message, 'positions', $field, [$number]);
		$issue['positionId'] = $positionId;
		return $issue;
	}

	protected function result(array $errors, array $warnings): array
	{
		$status = $errors ? 'error' : ($warnings ? 'warning' : 'success');
		return [
			'status' => $status,
			'canFinalize' => !$errors,
			'errors' => $errors,
			'warnings' => $warnings,
		];
	}
}
