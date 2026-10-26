<?php

class Sales_InvoiceController extends DEEC_Controller_DocumentAction
{
	private const XRECHNUNG_ENDPOINT_SCHEME = 'EM';

	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'invoices',
			'list' => 'Sales_Model_List_Invoices',
			'entity' => Sales_Model_Entity_Invoice::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Sales_Service_CreateDataFactory();

		return $factory->build($controller, $contactId);
	}

	protected function beforeEdit(array $row)
	{
		if ($this->isReadonlyState($row)) {
			return $this->_helper->redirector->gotoSimple(
				'view',
				'invoice',
				null,
				['id' => (int)$row['id']]
			);
		}

		return null;
	}

	protected function getViewAssigns(array $row, $form): array
	{
		$assign = parent::getViewAssigns($row, $form);
		$assign['xRechnungAvailable'] = $this->isXRechnungAvailable($row);

		if (!$assign['xRechnungAvailable']) return $assign;

		$context = $this->buildXRechnungContext($row);
		$xRechnungForm = $this->createXRechnungForm($context);
		$xRechnungForm->setValues($this->buildXRechnungDefaults($row, $context));
		$assign['xRechnungForm'] = $xRechnungForm;

		return $assign;
	}

	public function xrechnungAction()
	{
		$this->disableView();
		$request = $this->getRequest();

		if (!$request->isPost()) {
			return $this->sendXRechnungJson([
				'ok' => false,
				'valid' => false,
				'errors' => [$this->xRechnungError('request', 'METHOD', 'XRECHNUNG_POST_REQUIRED')],
				'warnings' => [],
			], 405);
		}

		$permission = new DEEC_Permission((array)$this->_user);
		if (!$permission->hasPermission('sales', 'invoice', 'view')) {
			return $this->sendXRechnungJson([
				'ok' => false,
				'valid' => false,
				'errors' => [$this->xRechnungError('permission', 'ACCESS', 'XRECHNUNG_ACCESS_DENIED')],
				'warnings' => [],
			], 403);
		}

		$id = (int)$this->_getParam('id', 0);
		$row = $id > 0 ? $this->loadRow($id) : null;
		if (!$row) {
			return $this->sendXRechnungJson([
				'ok' => false,
				'valid' => false,
				'errors' => [$this->xRechnungError('id', 'NOT-FOUND', 'XRECHNUNG_NOT_FOUND')],
				'warnings' => [],
			], 404);
		}

		$context = $this->buildXRechnungContext($row);
		$form = $this->createXRechnungForm($context);
		$post = (array)$request->getPost();
		$errors = $this->validateXRechnungScalars($form, $post);

		if (!$errors && !$form->isValid($post)) {
			$errors = $this->buildXRechnungFormErrors($form);
		}
		if ($errors) {
			return $this->sendXRechnungJson([
				'ok' => true,
				'valid' => false,
				'errors' => $errors,
				'warnings' => [],
			]);
		}

		$export = $this->buildXRechnungExport($form->getFilteredValues(), $context);
		try {
			$result = (new Sales_Service_XRechnungService())->generate($id, $export);
		} catch (RuntimeException $exception) {
			return $this->sendXRechnungJson([
				'ok' => false,
				'valid' => false,
				'errors' => [$this->xRechnungError('request', 'EXPORT', 'XRECHNUNG_REQUEST_FAILED')],
				'warnings' => [],
			], 400);
		}

		$result['errors'] = $this->mapXRechnungErrors((array)$result['errors'], $context);
		$result['warnings'] = $this->translateXRechnungWarnings((array)$result['warnings']);
		if (empty($result['valid']) || !is_string($result['xml']) || $result['xml'] === '') {
			return $this->sendXRechnungJson([
				'ok' => true,
				'valid' => false,
				'errors' => $result['errors'],
				'warnings' => (array)$result['warnings'],
			]);
		}

		if ((string)$request->getPost('download', '') !== '1') {
			return $this->sendXRechnungJson([
				'ok' => true,
				'valid' => true,
				'errors' => [],
				'warnings' => (array)$result['warnings'],
			]);
		}

		$filename = $this->buildXRechnungFilename((string)$row['invoiceid']);
		$response = $this->getResponse();
		$response->clearHeaders();
		$response->setHeader('Content-Type', 'application/xml; charset=UTF-8', true);
		$response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
		$response->setHeader('Content-Length', (string)strlen($result['xml']), true);
		$response->setHeader('Cache-Control', 'private, no-store', true);
		$response->setHeader('X-Content-Type-Options', 'nosniff', true);
		$response->setBody($result['xml']);

		return null;
	}

	private function isXRechnungAvailable(array $row): bool
	{
		return (int)($row['state'] ?? 0) === 105
			&& !empty($row['completed'])
			&& empty($row['cancelled']);
	}

	private function buildXRechnungContext(array $row): array
	{
		$positions = (new Sales_Model_DbTable_Invoicepos())->getPositions((int)$row['id']);
		$units = [];
		$unitFields = [];
		$lines = [];

		foreach ($positions as $position) {
			$uom = trim((string)$position->uom);
			if (!array_key_exists($uom, $unitFields)) {
				$field = 'unit_code_' . count($units);
				$unitFields[$uom] = $field;
				$units[] = ['field' => $field, 'uom' => $uom];
			}
			$id = (int)$position->id;
			$lines[] = [
				'id' => $id,
				'title' => (string)$position->title,
				'tax_rate' => empty($row['taxfree']) ? (float)$position->taxrate : 0.0,
				'uom' => $uom,
				'category_field' => 'line_tax_category_' . $id,
				'reason_field' => 'line_exemption_reason_' . $id,
			];
		}

		return [
			'units' => $units,
			'unit_fields' => $unitFields,
			'lines' => $lines,
			'bank_accounts' => $this->getXRechnungBankAccounts((int)$row['clientid']),
			'client' => $this->getXRechnungClient((int)$row['clientid']),
		];
	}

	private function createXRechnungForm(array $context): Sales_Form_XRechnung
	{
		$form = new Sales_Form_XRechnung($context['units'], $context['lines'], $context['bank_accounts']);
		$form->setLocale(Zend_Registry::get('Zend_Locale'));
		return $form;
	}

	private function getXRechnungBankAccounts(int $clientId): array
	{
		$permission = new DEEC_Permission((array)$this->_user);
		if (!$permission->hasPermission('contacts', 'contact', 'view')) return [];

		$db = new Contacts_Model_DbTable_Bankaccount();
		$rows = $db->fetchAll(
			$db->select()
				->where('clientid = ?', $clientId)
				->where('deleted = ?', 0)
				->order('ordering ASC')
				->order('id ASC')
		);
		$options = [];
		foreach ($rows as $row) {
			$data = $row->toArray();
			$account = preg_replace('/\s+/', '', (string)($data['iban'] ?? ''));
			if ($account === '') $account = trim((string)($data['accountnumber'] ?? ''));
			$masked = $account === '' ? '' : ' •••• ' . substr($account, -4);
			$label = trim((string)($data['title'] ?? ''));
			$options[(string)$data['id']] = ($label !== '' ? $label : 'ID ' . (int)$data['id']) . $masked;
		}
		return $options;
	}

	private function getXRechnungClient(int $clientId): array
	{
		$db = new Application_Model_DbTable_Client();
		$row = $db->fetchRow($db->select()->where('id = ?', $clientId)->where('deleted = ?', 0));
		return $row ? $row->toArray() : [];
	}

	private function buildXRechnungDefaults(array $row, array $context): array
	{
		$email = trim((string)($context['client']['email'] ?? ''));
		if (!DEEC_Filter::isValidEmail($email)) $email = '';

		$defaults = [
			'seller_contact_email' => $email,
			'seller_endpoint' => $email,
			'seller_endpoint_scheme' => self::XRECHNUNG_ENDPOINT_SCHEME,
			'buyer_endpoint_scheme' => self::XRECHNUNG_ENDPOINT_SCHEME,
			'business_process' => Sales_Form_XRechnung::getBusinessProcess(),
			'payment_means_code' => $this->defaultPaymentMeans((string)($row['paymentmethod'] ?? '')),
		];
		foreach ($context['units'] as $unit) {
			$defaults[$unit['field']] = $this->defaultUnitCode($unit['uom']);
		}
		foreach ($context['lines'] as $line) {
			$defaults[$line['category_field']] = in_array((float)$line['tax_rate'], [7.0, 19.0], true) ? 'S' : '';
		}
		return $defaults;
	}

	private function defaultPaymentMeans(string $paymentMethod): string
	{
		$value = strtolower(trim($paymentMethod));
		if (in_array($value, ['10', 'bar', 'barzahlung', 'cash'], true)) return '10';
		if (in_array($value, ['30'], true)) return '30';
		if (in_array($value, ['58', 'sepa', 'sepa-überweisung', 'sepa überweisung', 'sepa transfer'], true)) return '58';
		return '';
	}

	private function defaultUnitCode(string $uom): string
	{
		$code = strtoupper(trim($uom));
		if (array_key_exists($code, Sales_Form_XRechnung::getUnitCodes())) return $code;

		$map = [
			'unit' => 'C62', 'einheit' => 'C62',
			'piece' => 'H87', 'pieces' => 'H87', 'pcs' => 'H87', 'stück' => 'H87', 'stk' => 'H87',
			'hour' => 'HUR', 'hours' => 'HUR', 'stunde' => 'HUR', 'stunden' => 'HUR',
			'day' => 'DAY', 'days' => 'DAY', 'tag' => 'DAY', 'tage' => 'DAY',
			'month' => 'MON', 'months' => 'MON', 'monat' => 'MON', 'monate' => 'MON',
			'year' => 'ANN', 'years' => 'ANN', 'jahr' => 'ANN', 'jahre' => 'ANN',
			'kg' => 'KGM', 'kilogram' => 'KGM', 'kilogramm' => 'KGM',
			'g' => 'GRM', 'gram' => 'GRM', 'gramm' => 'GRM',
			'm' => 'MTR', 'meter' => 'MTR', 'metre' => 'MTR',
			'm²' => 'MTK', 'm2' => 'MTK', 'square metre' => 'MTK', 'quadratmeter' => 'MTK',
			'm³' => 'MTQ', 'm3' => 'MTQ', 'cubic metre' => 'MTQ', 'kubikmeter' => 'MTQ',
			'l' => 'LTR', 'liter' => 'LTR', 'litre' => 'LTR',
			'kwh' => 'KWH', 'kilowatt hour' => 'KWH', 'kilowattstunde' => 'KWH',
			't' => 'TNE', 'tonne' => 'TNE',
			'minute' => 'MIN', 'minutes' => 'MIN', 'minuten' => 'MIN',
			'second' => 'SEC', 'seconds' => 'SEC', 'sekunde' => 'SEC', 'sekunden' => 'SEC',
			'set' => 'SET', 'satz' => 'SET',
			'package' => 'XPK', 'packages' => 'XPK', 'paket' => 'XPK', 'pakete' => 'XPK',
		];
		return $map[strtolower(trim($uom))] ?? '';
	}

	private function validateXRechnungScalars(DEEC_Form $form, array $post): array
	{
		$errors = [];
		foreach ($form->getElements() as $name => $element) {
			if (array_key_exists($name, $post) && !is_scalar($post[$name]) && $post[$name] !== null) {
				$errors[] = $this->xRechnungError($name, 'FORM-INVALID', 'XRECHNUNG_INVALID_VALUE');
			}
		}
		return $errors;
	}

	private function buildXRechnungFormErrors(DEEC_Form $form): array
	{
		$errors = [];
		foreach ($form->getErrors() as $field => $codes) {
			foreach ((array)$codes as $code) {
				$key = 'XRECHNUNG_VALIDATION_' . strtoupper((string)$code);
				$errors[] = $this->xRechnungError((string)$field, 'FORM-' . strtoupper((string)$code), $key);
			}
		}
		return $errors;
	}

	private function buildXRechnungExport(array $values, array $context): array
	{
		$export = [
			'seller' => [
				'vat_id' => (string)($values['seller_vat_id'] ?? ''),
				'tax_id' => (string)($values['seller_tax_id'] ?? ''),
				'identifier' => (string)($values['seller_identifier'] ?? ''),
				'contact_name' => (string)($values['seller_contact_name'] ?? ''),
				'contact_phone' => (string)($values['seller_contact_phone'] ?? ''),
				'contact_email' => (string)($values['seller_contact_email'] ?? ''),
				'endpoint' => (string)($values['seller_endpoint'] ?? ''),
				'endpoint_scheme' => (string)($values['seller_endpoint_scheme'] ?? ''),
			],
			'buyer' => [
				'endpoint' => (string)($values['buyer_endpoint'] ?? ''),
				'endpoint_scheme' => (string)($values['buyer_endpoint_scheme'] ?? ''),
			],
			'buyer_reference' => (string)($values['buyer_reference'] ?? ''),
			'business_process' => (string)($values['business_process'] ?? ''),
			'period_start' => $this->normalizeXRechnungDate($values['period_start'] ?? null),
			'period_end' => $this->normalizeXRechnungDate($values['period_end'] ?? null),
			'payment' => [
				'means_code' => (string)($values['payment_means_code'] ?? ''),
				'bank_account_id' => !empty($values['bank_account_id']) ? (int)$values['bank_account_id'] : null,
				'due_date' => $this->normalizeXRechnungDate($values['payment_due_date'] ?? null),
				'terms' => (string)($values['payment_terms'] ?? ''),
			],
			'unit_codes' => [],
			'line_tax' => [],
		];
		foreach ($context['units'] as $unit) {
			$export['unit_codes'][$unit['uom']] = (string)($values[$unit['field']] ?? '');
		}
		foreach ($context['lines'] as $line) {
			$export['line_tax'][$line['id']] = [
				'category' => (string)($values[$line['category_field']] ?? ''),
				'exemption_reason' => (string)($values[$line['reason_field']] ?? ''),
			];
		}
		return $export;
	}

	private function mapXRechnungErrors(array $errors, array $context): array
	{
		$fields = [
			'seller.vat_id' => 'seller_vat_id', 'seller.tax_id' => 'seller_tax_id',
			'seller.identifier' => 'seller_identifier', 'seller.contact_name' => 'seller_contact_name',
			'seller.contact_phone' => 'seller_contact_phone', 'seller.contact_email' => 'seller_contact_email',
			'seller.endpoint' => 'seller_endpoint', 'seller.endpoint_scheme' => 'seller_endpoint',
			'buyer.endpoint' => 'buyer_endpoint', 'buyer.endpoint_scheme' => 'buyer_endpoint',
			'buyer_reference' => 'buyer_reference', 'business_process' => 'business_process',
			'period_start' => 'period_start', 'period_end' => 'period_end',
			'payment.means_code' => 'payment_means_code', 'payment.account' => 'bank_account_id',
			'payment.account.iban' => 'bank_account_id', 'payment.account.bic' => 'bank_account_id',
			'payment.due_date' => 'payment_due_date', 'payment.terms' => 'payment_terms',
		];
		$lines = array_column($context['lines'], null, 'id');
		foreach ($errors as &$error) {
			$field = (string)($error['field'] ?? 'request');
			if (isset($fields[$field])) {
				$error['field'] = $fields[$field];
				continue;
			}
			if (!preg_match('/^lines\.(\d+)\.(unit_code|tax_category|tax_rate|exemption_reason)$/', $field, $match)) continue;
			$line = $lines[(int)$match[1]] ?? null;
			if (!$line) continue;
			$error['field'] = $match[2] === 'unit_code'
				? ($context['unit_fields'][$line['uom']] ?? $field)
				: ($match[2] === 'exemption_reason' ? $line['reason_field'] : $line['category_field']);
		}
		unset($error);
		return $errors;
	}

	private function translateXRechnungWarnings(array $warnings): array
	{
		$keys = ['SCOPE' => 'XRECHNUNG_WARNING_SCOPE', 'MASTER-DATA' => 'XRECHNUNG_WARNING_MASTER_DATA'];
		foreach ($warnings as &$warning) {
			$rule = (string)($warning['rule'] ?? '');
			if (isset($keys[$rule])) $warning['message'] = $this->translateXRechnungMessage($keys[$rule]);
		}
		unset($warning);
		return $warnings;
	}

	private function normalizeXRechnungDate($value): ?string
	{
		$value = trim((string)$value);
		if ($value === '') return null;
		foreach (['Y-m-d', 'd.m.Y'] as $format) {
			$date = DateTimeImmutable::createFromFormat('!' . $format, $value);
			if ($date && $date->format($format) === $value) return $date->format('Y-m-d');
		}
		return $value;
	}

	private function xRechnungError(string $field, string $rule, string $messageKey): array
	{
		return ['field' => $field, 'rule' => $rule, 'message' => $this->translateXRechnungMessage($messageKey)];
	}

	private function translateXRechnungMessage(string $key): string
	{
		if (Zend_Registry::isRegistered('DEEC_Translate')) {
			$translator = Zend_Registry::get('DEEC_Translate');
			if ($translator instanceof DEEC_Translate) return $translator->t($key);
		}
		return $this->view->translate($key);
	}

	private function sendXRechnungJson(array $data, int $status = 200)
	{
		$this->getResponse()->setHttpResponseCode($status);
		return $this->_helper->json($data);
	}

	private function buildXRechnungFilename(string $number): string
	{
		$number = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($number));
		$number = trim((string)$number, '.-_');
		if ($number === '') $number = 'invoice';
		return 'XRechnung-' . substr($number, 0, 120) . '.xml';
	}
}
