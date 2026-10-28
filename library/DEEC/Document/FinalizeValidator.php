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

		$positionValidation = $this->validatePositions($document, $module, $controller);
		$errors = array_merge($errors, $positionValidation['errors']);
		$warnings = array_merge($warnings, $positionValidation['warnings']);

		if ($controller === 'invoice' && empty($document['deliverydate'])) {
			$warnings[] = $this->issue('DOCUMENT_VALIDATION_INVOICE_SERVICE_DATE_MISSING', 'overview', 'deliverydate');
		} elseif (in_array($controller, self::DELIVERY_DATE_DOCUMENTS, true) && empty($document['deliverydate'])) {
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
		if (!class_exists($class)) return $this->positionResult([$this->issue('DOCUMENT_VALIDATION_POSITIONS_UNAVAILABLE', 'positions')], []);

		$positions = (new $class())->getPositions((int)$document['id']);
		if (count($positions) === 0) return $this->positionResult([$this->issue('DOCUMENT_VALIDATION_POSITION_REQUIRED', 'positions')], []);

		$errors = [];
		$warnings = [];
		$number = 0;
		$relevant = 0;
		$hasZeroPrice = false;
		$hasZeroQuantity = false;
		$formClass = ucfirst($module) . '_Form_' . ucfirst($controller) . 'pos';
		$form = class_exists($formClass) ? new $formClass() : null;
		foreach ($positions as $position) {
			$number++;
			$row = method_exists($position, 'toArray') ? $position->toArray() : (array)$position;
			if (!$this->isRelevantPosition($row)) continue;
			$relevant++;
			if ($this->isTextOnlyPosition($row)) continue;
			$positionId = (int)($row['id'] ?? 0);
			$requiredErrors = [];
			if ($form) {
				$form->isValid($row);
				$requiredErrors = $form->getErrors();
			}

			if (!empty($requiredErrors['price']) || !isset($row['price']) || !is_numeric($row['price'])) {
				$errors[] = $this->positionIssue('DOCUMENT_VALIDATION_POSITION_PRICE_INVALID', $number, $positionId, 'price');
			} elseif ((float)$row['price'] == 0.0) {
				$hasZeroPrice = true;
			}
			if (!empty($requiredErrors['taxrate']) || !isset($row['taxrate']) || !is_numeric($row['taxrate'])) {
				$errors[] = $this->positionIssue('DOCUMENT_VALIDATION_POSITION_TAXRATE_INVALID', $number, $positionId, 'taxrate');
			}
			if (!empty($requiredErrors['quantity']) || !isset($row['quantity']) || !is_numeric($row['quantity'])) {
				$errors[] = $this->positionIssue('DOCUMENT_VALIDATION_POSITION_QUANTITY_INVALID', $number, $positionId, 'quantity');
			} elseif ((float)$row['quantity'] == 0.0) {
				$hasZeroQuantity = true;
			}
		}

		if ($relevant === 0) $errors[] = $this->issue('DOCUMENT_VALIDATION_POSITION_CONTENT_REQUIRED', 'positions');
		if ($hasZeroQuantity) $warnings[] = $this->issue('DOCUMENT_VALIDATION_ZERO_QUANTITY', 'positions');
		if ($hasZeroPrice) $warnings[] = $this->issue('DOCUMENT_VALIDATION_ZERO_PRICE', 'positions');
		return $this->positionResult($errors, $warnings);
	}

	protected function isRelevantPosition(array $position): bool
	{
		if (!empty($position['itemid'])) return true;
		foreach (['sku', 'title', 'description'] as $field) {
			$value = html_entity_decode(strip_tags((string)($position[$field] ?? '')), ENT_QUOTES, 'UTF-8');
			if (trim(str_replace("\xc2\xa0", ' ', $value)) !== '') return true;
		}
		return false;
	}

	protected function isTextOnlyPosition(array $position): bool
	{
		if ((int)($position['itemid'] ?? 0) > 0 || trim((string)($position['sku'] ?? '')) !== '') return false;
		foreach (['price', 'taxrate', 'quantity'] as $field) {
			if (isset($position[$field]) && $position[$field] !== '') return false;
		}
		return true;
	}

	protected function positionResult(array $errors, array $warnings): array
	{
		return ['errors' => $errors, 'warnings' => $warnings];
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
