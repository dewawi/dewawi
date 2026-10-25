<?php

class Sales_Service_DocumentPayloadService
{
	public function build(array $document, string $controller, array $options = []): array
	{
		$locale = Zend_Registry::get('Zend_Locale');

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($document['contactid']);

		$templateDb = new Application_Model_DbTable_Template();
		$templateId = !empty($options['templateid'])
			? (int)$options['templateid']
			: (int)($document['templateid'] ?? 0);

		try {
			if ($templateId > 0) {
				$template = $templateDb->getTemplate($templateId);
			} else {
				$template = $templateDb->getPrimaryTemplate();
			}
		} catch (Exception $e) {
			$template = $templateDb->getPrimaryTemplate();
		}

		$document['templateid'] = (int)$template['id'];

		list($positions, $document, $optionsList, $optionSets) = $this->getPositions($document, $locale, $controller);

		$payment = $this->buildPaymentData($document);

		$document['prepayment'] = $payment['prepayment_formatted'];
		$document['prepayment_raw'] = $payment['prepayment_raw'];
		$document['balance'] = $payment['balance_formatted'];
		$document['balance_raw'] = $payment['balance_raw'];

		$itemData = $this->getItemData($positions);

		$footerDb = new Application_Model_DbTable_Footer();
		$footers = $footerDb->getFooters((int)$document['templateid']);

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
			'settings' => $this->buildPdfSettings($document, $controller),
		];
	}

	// Read-only export preflight; normal PDF generation never invokes these checks.
	public function prepareEInvoice(int $id, array $export = [], string $profile = 'en16931'): array
	{
		if (!in_array($profile, ['en16931', 'xrechnung'], true)) throw new InvalidArgumentException('Unsupported invoice profile');
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		if (!$permission->hasPermission('sales', 'invoice', 'view')) throw new RuntimeException('Invoice access denied');

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getById($id);
		if (!$invoice) throw new RuntimeException('Invoice not found');
		$clientId = (int)$invoice['clientid'];
		$clientDb = new Application_Model_DbTable_Client();
		$client = $clientDb->fetchRow($clientDb->select()->where('id = ?', $clientId)->where('deleted = ?', 0));
		$client = $client ? $client->toArray() : [];
		$positions = (new Sales_Model_DbTable_Invoicepos())->getPositions($id);
		$errors = [];
		$warnings = [];
		$error = static function (string $rule, string $field, string $message) use (&$errors): void {
			$errors[] = ['rule' => $rule, 'field' => $field, 'message' => $message];
		};

		// Export-only metadata fills gaps in the schema; it never replaces invoice snapshots.
		$seller = [
			'name' => trim((string)($client['company'] ?? '')),
			'street' => trim((string)($client['address'] ?? '')),
			'postcode' => trim((string)($client['postcode'] ?? '')),
			'city' => trim((string)($client['city'] ?? '')),
			'country' => strtoupper(trim((string)($client['country'] ?? ''))),
			'vat_id' => trim((string)($export['seller']['vat_id'] ?? '')),
			'tax_id' => trim((string)($export['seller']['tax_id'] ?? '')),
			'identifier' => trim((string)($export['seller']['identifier'] ?? '')),
			'contact_name' => trim((string)($export['seller']['contact_name'] ?? '')),
			'contact_phone' => trim((string)($export['seller']['contact_phone'] ?? '')),
			'contact_email' => trim((string)($export['seller']['contact_email'] ?? $client['email'] ?? '')),
			'endpoint' => trim((string)($export['seller']['endpoint'] ?? '')),
			'endpoint_scheme' => trim((string)($export['seller']['endpoint_scheme'] ?? '')),
		];
		$buyer = [
			'number' => $invoice['contactid'] ?? null,
			'name' => trim((string)($invoice['billingname1'] ?? '')),
			'name2' => trim((string)($invoice['billingname2'] ?? '')),
			'department' => trim((string)($invoice['billingdepartment'] ?? '')),
			'street' => trim((string)($invoice['billingstreet'] ?? '')),
			'postcode' => trim((string)($invoice['billingpostcode'] ?? '')),
			'city' => trim((string)($invoice['billingcity'] ?? '')),
			'country' => strtoupper(trim((string)($invoice['billingcountry'] ?? ''))),
			'vat_id' => trim((string)($invoice['vatin'] ?? '')),
			'endpoint' => trim((string)($export['buyer']['endpoint'] ?? '')),
			'endpoint_scheme' => trim((string)($export['buyer']['endpoint_scheme'] ?? '')),
		];
		$payment = [
			'description' => $invoice['paymentmethod'] ?? null,
			'means_code' => trim((string)($export['payment']['means_code'] ?? '')),
			'due_date' => $export['payment']['due_date'] ?? null,
			'terms' => trim((string)($export['payment']['terms'] ?? '')),
			'account' => null,
		];
		if (!empty($export['payment']['bank_account_id']) && $payment['means_code'] === '') $error('BR-49', 'payment.means_code', 'Zum ausgewählten Zahlungskonto fehlt der standardisierte Zahlungsart-Code.');
		if (!empty($export['payment']['bank_account_id']) && in_array($payment['means_code'], ['30', '58'], true)) {
			if (!$permission->hasPermission('contacts', 'contact', 'view')) throw new RuntimeException('Bank account access denied');
			$account = (new Contacts_Model_DbTable_Bankaccount())->getById((int)$export['payment']['bank_account_id']);
			if (!$account || (int)$account['clientid'] !== $clientId) throw new RuntimeException('Bank account not found');
			$payment['account'] = array_intersect_key($account, array_flip(['iban', 'bic', 'accountnumber', 'currency', 'title']));
		}
		$data = [
			'id' => $id, 'client_id' => $clientId, 'number' => $invoice['invoiceid'] ?? null,
			'issue_date' => $invoice['invoicedate'] ?? null, 'type_code' => '380',
			'currency' => trim((string)($invoice['currency'] ?? '')),
			'delivery_date' => $invoice['deliverydate'] ?? null,
			'period_start' => $export['period_start'] ?? null, 'period_end' => $export['period_end'] ?? null,
			'buyer_reference' => trim((string)($export['buyer_reference'] ?? '')),
			'business_process' => trim((string)($export['business_process'] ?? '')),
			'specification' => $profile === 'xrechnung' ? 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0' : 'urn:cen.eu:en16931:2017',
			'seller' => $seller, 'buyer' => $buyer, 'payment' => $payment,
			'totals' => array_intersect_key($invoice, array_flip(['subtotal', 'taxes', 'total', 'prepayment'])),
			'lines' => [], 'tax_breakdown' => [],
		];
		if (empty($data['number'])) $error('BR-02', 'number', 'Die Rechnungsnummer fehlt.');
		if (!$this->isEInvoiceDate($data['issue_date'])) $error('BR-03', 'issue_date', 'Das Rechnungsdatum fehlt oder ist ungültig.');
		foreach (['delivery_date', 'period_start', 'period_end'] as $field) {
			if (!empty($data[$field]) && !$this->isEInvoiceDate($data[$field])) $error('DATE', $field, 'Das Leistungsdatum oder der Leistungszeitraum ist ungültig.');
		}
		if ($data['period_start'] && $data['period_end'] && $data['period_start'] > $data['period_end']) {
			$error('BR-29', 'period_end', 'Das Ende des Leistungszeitraums liegt vor dem Beginn.');
		}
		$countries = Zend_Locale::getTranslationList('territory', 'en');
		foreach (['seller' => $seller, 'buyer' => $buyer] as $party => $values) {
			if ($values['name'] === '') $error($party === 'seller' ? 'BR-06' : 'BR-07', $party . '.name', $party === 'seller' ? 'Der Name des Rechnungsausstellers fehlt.' : 'Der Rechnungsempfänger fehlt.');
			if (!preg_match('/^[A-Z]{2}$/', $values['country']) || !isset($countries[$values['country']]) || in_array($values['country'], ['EU', 'EZ', 'UN', 'ZZ', 'XK', 'AC', 'CP', 'DG', 'EA', 'IC', 'TA'], true)) {
				$error($party === 'seller' ? 'BR-09' : 'BR-11', $party . '.country', 'Das Land von ' . ($party === 'seller' ? 'Aussteller' : 'Empfänger') . ' muss als gültiger ISO-Ländercode angegeben sein.');
			}
			if ($values['vat_id'] !== '' && !preg_match('/^(?:[A-Z]{2})[A-Z0-9]+$/', $values['vat_id'])) $error('BR-CO-09', $party . '.vat_id', 'Die Umsatzsteuer-ID muss mit einem Länderpräfix beginnen und darf keine Leerzeichen enthalten.');
		}
		$currencyValid = preg_match('/^[A-Z]{3}$/', $data['currency']) && isset(Zend_Locale::getTranslationList('nametocurrency', 'en')[$data['currency']]);
		if (!$currencyValid) $error('BR-CL-03', 'currency', 'Die Rechnungswährung fehlt oder ist kein bekannter ISO-Währungscode.');
		if ($seller['identifier'] === '' && $seller['vat_id'] === '') $error('BR-CO-26', 'seller.identifier', 'Eine Ausstellerkennung oder Umsatzsteuer-ID fehlt; eine Steuernummer allein ersetzt diese Kennung nicht.');
		if (!count($positions)) $error('BR-16', 'lines', 'Die Rechnung enthält keine Rechnungsposition.');

		$numeric = true;
		$rulesByLine = [];
		$linesById = [];
		$priceRuleHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');
		foreach ($positions as $position) {
			$lineId = (int)$position->id;
			$linesById[$lineId] = $position;
			foreach (['price', 'quantity', 'taxrate', 'total'] as $field) {
				if (!is_numeric($position->$field) || !is_finite((float)$position->$field)) {
					$numeric = false;
					$error('LINE-NUMBER', 'lines.' . $lineId . '.' . $field, 'Position ' . $lineId . ': ' . $field . ' fehlt oder ist keine gültige Zahl.');
				}
			}
			$rulesByLine[$lineId] = $priceRuleHelper->getPriceRulePositions('sales', 'invoicepos', $lineId);
			foreach ($rulesByLine[$lineId] as $rule) {
				if (!is_numeric($rule['amount']) || !is_finite((float)$rule['amount']) || !in_array($rule['action'], ['bypercent', 'byfixed', 'topercent', 'tofixed'], true)) {
					$numeric = false;
					$error('PRICE-RULE', 'lines.' . $lineId . '.discounts', 'Position ' . $lineId . ': Eine Preisregel ist ungültig.');
				}
			}
		}
		foreach (['subtotal', 'taxes', 'total'] as $field) {
			if (!isset($invoice[$field]) || !is_numeric($invoice[$field]) || !is_finite((float)$invoice[$field])) $error('TOTAL', 'totals.' . $field, 'Der gespeicherte Rechnungsbetrag ' . $field . ' fehlt oder ist ungültig.');
			elseif (abs((float)$invoice[$field] - round((float)$invoice[$field], 2)) > 0.000001) $error('BR-DEC', 'totals.' . $field, 'Der gespeicherte Rechnungsbetrag ' . $field . ' hat mehr als zwei wirksame Nachkommastellen.');
		}
		if ($numeric && $currencyValid) {
			// calculate() is the existing read-only calculation path; direct() would persist changes.
			$currency = Zend_Registry::get('Zend_Currency');
			Zend_Registry::set('Zend_Currency', clone $currency);
			try {
				$calculation = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->calculate($invoice, $positions, 'sales', 'invoice');
			} finally {
				Zend_Registry::set('Zend_Currency', $currency);
			}
			$groups = [];
			foreach ($positions as $position) {
				$lineId = (int)$position->id;
				$amounts = $calculation['row'][$lineId];
				$rate = empty($invoice['taxfree']) ? (float)$position->taxrate : 0.0;
				$tax = $export['line_tax'][$lineId] ?? $export['tax'] ?? [];
				$category = $tax['category'] ?? ($rate > 0 ? 'S' : null);
				$reason = trim((string)($tax['exemption_reason'] ?? ''));
				$unit = $export['unit_codes'][$position->uom] ?? null;
				if (!in_array($unit, ['C62', 'H87', 'HUR', 'DAY', 'MON', 'ANN', 'KGM', 'GRM', 'MTR', 'MTK', 'MTQ', 'LTR', 'KWH', 'TNE', 'MIN', 'SEC', 'SET', 'XPK'], true)) {
					$error('BR-23', 'lines.' . $lineId . '.unit_code', 'Position ' . $lineId . ': Die Mengeneinheit benötigt eine unterstützte UN/ECE-Code-Zuordnung.');
				}
				if (!in_array($category, ['S', 'Z', 'E'], true)) $error('TAX-CATEGORY', 'lines.' . $lineId . '.tax_category', 'Position ' . $lineId . ': Die Steuerkategorie fehlt oder dieser Steuerfall wird noch nicht unterstützt. Steuerfreiheit allein legt den Steuerfall nicht fest.');
				if (($category === 'S' && $rate <= 0) || (in_array($category, ['Z', 'E'], true) && $rate != 0) || (float)$position->taxrate < 0) {
					$error('BR-' . ($category ?? 'S') . '-05', 'lines.' . $lineId . '.tax_rate', 'Position ' . $lineId . ': Steuerkategorie und Steuersatz passen nicht zusammen.');
				}
				if ($category === 'E' && $reason === '') $error('BR-E-10', 'lines.' . $lineId . '.exemption_reason', 'Position ' . $lineId . ': Der Grund der Steuerbefreiung fehlt.');
				if (in_array($category, ['S', 'Z'], true) && $reason !== '') $error('BR-' . $category . '-10', 'lines.' . $lineId . '.exemption_reason', 'Position ' . $lineId . ': Für diesen Steuerfall darf kein Befreiungsgrund angegeben werden.');
				if (trim((string)$position->title) === '') $error('BR-25', 'lines.' . $lineId . '.name', 'Position ' . $lineId . ': Die Bezeichnung fehlt.');
				if ($amounts['price'] < 0 || (float)$position->price < 0) $error('BR-27', 'lines.' . $lineId . '.unit_net_price', 'Position ' . $lineId . ': Der Einzelpreis darf nicht negativ sein.');
				if ($position->currency !== $data['currency']) $error('LINE-CURRENCY', 'lines.' . $lineId . '.currency', 'Position ' . $lineId . ': Die Währung weicht von der Rechnungswährung ab.');
				if (abs((float)$position->total - $amounts['gross']) >= 0.005) $error('STORED-LINE-TOTAL', 'lines.' . $lineId . '.gross', 'Position ' . $lineId . ': Der gespeicherte Betrag passt nicht zur bestehenden Berechnung.');
				$masterId = (int)$position->masterid;
				$inherited = $masterId && isset($linesById[$masterId]) && !empty($linesById[$masterId]->pricerulemaster);
				if ($masterId && (!isset($linesById[$masterId]) || !empty($linesById[$masterId]->masterid) || $masterId === $lineId)) $error('LINE-MASTER', 'lines.' . $lineId . '.masterid', 'Position ' . $lineId . ': Die Hauptposition fehlt oder ist ungültig.');
				$discounts = $inherited ? $rulesByLine[$masterId] : ($masterId ? [] : $rulesByLine[$lineId]);
				$data['lines'][] = [
					'id' => $lineId, 'name' => $position->title, 'description' => $position->description,
					'sku' => $position->sku, 'quantity' => $position->quantity, 'unit' => $position->uom, 'unit_code' => $unit,
					'unit_list_price' => $position->price, 'unit_net_price' => $amounts['price'],
					'price_rules' => $discounts, 'rules_inherited' => $inherited,
					'tax_category' => $category, 'tax_rate' => $rate, 'exemption_reason' => $reason,
					'net' => $amounts['total'], 'gross' => $position->total,
				];
				$key = ($category ?? '') . ':' . $rate;
				if (!isset($groups[$key])) $groups[$key] = ['category' => $category, 'rate' => $rate, 'net' => 0, 'tax' => 0, 'exemption_reason' => $reason];
				if ($groups[$key]['exemption_reason'] !== $reason) $error('TAX-REASON-MAPPING', 'lines.' . $lineId . '.exemption_reason', 'Unterschiedliche Befreiungsgründe derselben Steuergruppe müssen vor dem Export fachlich zusammengeführt werden.');
				$groups[$key]['net'] += $amounts['total'];
				$groups[$key]['tax'] = empty($invoice['taxfree']) ? $calculation['row']['taxes'][(string)(float)$position->taxrate] : 0;
			}
			$data['tax_breakdown'] = array_values($groups);
			foreach ($data['tax_breakdown'] as &$group) $group['net'] = round($group['net'], 2);
			unset($group);
			foreach (['subtotal', 'taxes', 'total'] as $field) {
				$calculated = $field === 'taxes' ? $calculation['row']['taxes']['total'] : $calculation['row'][$field];
				if (isset($invoice[$field]) && is_numeric($invoice[$field]) && abs((float)$invoice[$field] - $calculated) >= 0.005) {
					$error('STORED-TOTAL', 'totals.' . $field, 'Die gespeicherten Rechnungsbeträge passen nicht zur bestehenden Berechnung; der Beleg wird nicht korrigiert.');
				}
			}
			if ($seller['vat_id'] === '' && $seller['tax_id'] === '') $error('BR-S/Z/E-02', 'seller.tax_id', 'Die Umsatzsteuer-ID oder Steuernummer des Ausstellers fehlt.');
		}
		$paid = $invoice['prepayment'] ?? 0;
		if (!is_numeric($paid) || !is_finite((float)$paid)) {
			$error('BR-CO-16', 'totals.prepayment', 'Der bereits gezahlte Betrag ist ungültig.');
		} elseif (isset($invoice['total']) && is_numeric($invoice['total'])) {
			if (abs((float)$paid - round((float)$paid, 2)) > 0.000001) $error('BR-DEC-18', 'totals.prepayment', 'Der bereits gezahlte Betrag hat mehr als zwei wirksame Nachkommastellen.');
			if ($currencyValid) {
				$currency = Zend_Registry::get('Zend_Currency');
				Zend_Registry::set('Zend_Currency', clone $currency);
				try {
					$data['totals']['payable'] = $this->buildPaymentData($invoice, true)['balance_raw'];
				} finally {
					Zend_Registry::set('Zend_Currency', $currency);
				}
			}
			if ((float)$invoice['total'] > (float)$paid && !$payment['due_date'] && $payment['terms'] === '') $error('BR-CO-25', 'payment.terms', 'Bei offenem Zahlbetrag fehlen Zahlungsziel oder Zahlungsbedingungen.');
		}
		if ($payment['due_date'] && !$this->isEInvoiceDate($payment['due_date'])) $error('DATE', 'payment.due_date', 'Das Zahlungsziel ist kein gültiges Datum.');
		$code = $payment['means_code'];
		if ($profile === 'xrechnung' && $code === '') $error('BR-DE-1', 'payment.means_code', 'Für XRechnung fehlt der standardisierte Zahlungsart-Code.');
		if ($code !== '' && !in_array($code, ['10', '30', '58'], true)) $error('PAYMENT-MAPPING', 'payment.means_code', 'Diese Zahlungsart benötigt weitere Angaben und wird von dieser Vorprüfung noch nicht unterstützt.');
		if (in_array($code, ['30', '58'], true)) {
			$accountId = preg_replace('/\s+/', '', (string)($payment['account']['iban'] ?? ''));
			if ($accountId === '') $accountId = trim((string)($payment['account']['accountnumber'] ?? ''));
			if ($accountId === '') $error('BR-61', 'payment.account', 'Für die Überweisung fehlt das ausdrücklich ausgewählte Zahlungskonto.');
			if ($code === '58' && !(new Zend_Validate_Iban())->isValid($accountId)) $error('BR-DE-19', 'payment.account.iban', 'Für die SEPA-Überweisung fehlt eine gültige IBAN.');
		}
		if ($profile === 'xrechnung') {
			foreach (['seller' => ['city' => 'BR-DE-3', 'postcode' => 'BR-DE-4', 'contact_name' => 'BR-DE-5', 'contact_phone' => 'BR-DE-6', 'contact_email' => 'BR-DE-7', 'endpoint' => 'PEPPOL-EN16931-R020'], 'buyer' => ['city' => 'BR-DE-8', 'postcode' => 'BR-DE-9', 'endpoint' => 'PEPPOL-EN16931-R010']] as $party => $fields) {
				foreach ($fields as $field => $rule) if ($data[$party][$field] === '') $error($rule, $party . '.' . $field, 'Für XRechnung fehlt ' . $party . '.' . $field . '.');
			}
			if ($data['buyer_reference'] === '') $error('BR-DE-15', 'buyer_reference', 'Für XRechnung fehlt die vom Käufer vorgegebene Referenz, gegebenenfalls die Leitweg-ID.');
			if ($data['business_process'] === '') $error('PEPPOL-EN16931-R001', 'business_process', 'Für XRechnung fehlt die Geschäftsprozesskennung.');
			if ($seller['contact_email'] !== '' && !filter_var($seller['contact_email'], FILTER_VALIDATE_EMAIL)) $error('BR-DE-28', 'seller.contact_email', 'Die E-Mail-Adresse des Ausstellerkontakts ist ungültig.');
		}
		foreach (['seller' => $seller, 'buyer' => $buyer] as $party => $values) {
			if ($values['endpoint'] !== '' && $values['endpoint_scheme'] === '') $error($party === 'seller' ? 'BR-62' : 'BR-63', $party . '.endpoint_scheme', 'Das Kennungsschema der elektronischen Adresse fehlt.');
			if ($values['endpoint'] !== '' && $values['endpoint_scheme'] !== '' && !in_array($values['endpoint_scheme'], ['EM', '0204', '0088', '9930'], true)) $error('ENDPOINT-SCHEME', $party . '.endpoint_scheme', 'Dieses elektronische Adressschema wird von der Vorprüfung noch nicht unterstützt.');
			if ($values['endpoint'] !== '' && $values['endpoint_scheme'] === 'EM' && !filter_var($values['endpoint'], FILTER_VALIDATE_EMAIL)) $error('ENDPOINT-EMAIL', $party . '.endpoint', 'Die elektronische Adresse ist keine gültige E-Mail-Adresse.');
		}
		$warnings[] = ['rule' => 'SCOPE', 'message' => 'Datenvorprüfung für die unterstützten Steuer- und Zahlungsfälle; keine XML-/Schematron-Konformitätsprüfung.'];
		$warnings[] = ['rule' => 'MASTER-DATA', 'message' => 'Aussteller- und gewählte Bankdaten sind aktuelle Stammdaten. Ihre Übereinstimmung mit dem ausgestellten Original muss vor dem Export bestätigt werden.'];
		return ['valid' => !$errors, 'profile' => $profile, 'data' => $data, 'errors' => $errors, 'warnings' => $warnings, 'xml_validation_required' => true];
	}

	private function isEInvoiceDate($value): bool
	{
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		return $date && $date->format('Y-m-d') === $value && $date->format('Y') !== '0000';
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
			$itemId = (int)$position->itemid;
			if ($itemId <= 0) {
				continue;
			}

			$item = $itemDb->getItem($itemId);
			if (!is_array($item) || empty($item['id'])) {
				continue;
			}

			$items[$itemId] = $item;

			if (!empty($item['catid'])) {
				$categories[$itemId] = $categoryDb->getCategory((int)$item['catid']);
			}

			$media[$itemId] = $mediaDb->getMediaByParentID((int)$item['id'], 'items', 'item');

			$attributeSets = $attributeSetsDb->getPositionSets($itemId);

			foreach ($attributeSets as $attributeSetId => $attributeSet) {
				$attributesByGroup[$position->id][$attributeSetId] = [
					'title' => $attributeSet['title'],
					'description' => $attributeSet['description'],
					'attributes' => $attributesDb->getPositions($itemId, $attributeSet['id']),
				];
			}

			$otherAttributes = $attributesDb->getPositions($itemId, 0);
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

	protected function buildPaymentData(array $document, bool $signed = false): array
	{
		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency($document['currency'], 'USE_SYMBOL');

		$prepayment = (float)($document['prepayment'] ?? 0);
		$total = (float)($document['total_raw'] ?? $document['total'] ?? 0);
		$balance = $signed ? round($total - $prepayment, 2) : max(0, $total - $prepayment);

		return [
			'prepayment_raw' => $prepayment,
			'balance_raw' => $balance,
			'prepayment_formatted' => $currency->toCurrency($prepayment),
			'balance_formatted' => $currency->toCurrency($balance),
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
			'showCover' => 0,
			'showImages' => 0,
			'showPositionImages' => 0,
		];

		if ($controller === 'quote') {
			$defaults['showOptions'] = 1;
			$defaults['showIncludedOptions'] = 1;
			$defaults['showAttributes'] = 1;
			$defaults['showCover'] = 1;
			$defaults['showImages'] = 1;
			$defaults['showPositionImages'] = 1;
		}

		if ($controller === 'invoice') {
			$defaults['showOptions'] = 0;
			$defaults['showIncludedOptions'] = 0;
			$defaults['showAttributes'] = 0;
			$defaults['showCover'] = 0;
			$defaults['showImages'] = 1;
			$defaults['showPositionImages'] = 1;
		}

		if ($controller === 'deliveryorder') {
			$defaults['showPrices'] = 0;
			$defaults['showDiscounts'] = 0;
			$defaults['showOptions'] = 0;
			$defaults['showIncludedOptions'] = 0;
			$defaults['showAttributes'] = 0;
			$defaults['showCover'] = 0;
			$defaults['showImages'] = 1;
			$defaults['showPositionImages'] = 1;
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
			'showImages' => isset($document['pdfshowimages']) ? (int)$document['pdfshowimages'] : $defaults['showImages'],
			'showPositionImages' => isset($document['pdfshowpositionimages']) ? (int)$document['pdfshowpositionimages'] : $defaults['showPositionImages'],
			];
	}
}
