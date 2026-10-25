<?php

class Sales_Service_XRechnungService
{
	private const NS = [
		'Invoice' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
		'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
		'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
	];

	public function generate(int $id, array $export = []): array
	{
		// Check lifecycle before preparation; a draft finalized during preparation must not be exported.
		$invoice = (new Sales_Model_DbTable_Invoice())->getById($id);
		$result = (new Sales_Service_DocumentPayloadService())->prepareEInvoice($id, $export, 'xrechnung');
		$result['xml'] = null;
		if (!$result['valid']) return $result;

		if (!$invoice || (int)$invoice['state'] !== 105 || empty($invoice['completed']) || !empty($invoice['cancelled'])
			|| empty($invoice['invoiceid']) || (string)$invoice['invoiceid'] !== (string)$result['data']['number']) {
			$result['errors'][] = ['rule' => 'DOCUMENT-NOT-FINAL', 'field' => 'state', 'message' => 'Nur endgültig abgeschlossene, nicht stornierte Rechnungen können exportiert werden.'];
		}
		$result['errors'] = array_merge($result['errors'], $this->checkAmountsAndScope($result['data']));
		if ($result['errors']) {
			$result['valid'] = false;
			return $result;
		}

		try {
			$result['xml'] = $this->build($result['data']);
		} catch (InvalidArgumentException $exception) {
			$result['valid'] = false;
			$result['errors'][] = ['rule' => 'XML-TEXT', 'field' => 'xml', 'message' => $exception->getMessage()];
		}
		return $result;
	}

	private function checkAmountsAndScope(array $data): array
	{
		$errors = [];
		$error = static function (string $rule, string $field, string $message) use (&$errors): void {
			$errors[] = ['rule' => $rule, 'field' => $field, 'message' => $message];
		};
		if ($data['seller']['country'] !== 'DE' || $data['buyer']['country'] !== 'DE' || $data['currency'] !== 'EUR') {
			$error('XML-SCOPE', 'currency', 'Diese Exportversion unterstützt ausschließlich deutsche Rechnungen in EUR.');
		}
		if ($data['business_process'] !== 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0') $error('XML-SCOPE', 'business_process', 'Diese Exportversion unterstützt ausschließlich den Geschäftsprozess billing:01:1.0.');
		foreach (['seller', 'buyer'] as $party) {
			if ($data[$party]['endpoint_scheme'] !== 'EM') $error('XML-SCOPE', $party . '.endpoint_scheme', 'Dieses elektronische Adressschema ist für den XML-Export noch nicht freigegeben; unterstützt wird EM.');
			if ($data[$party]['vat_id'] !== '' && !preg_match('/^DE\d{9}$/', $data[$party]['vat_id'])) $error('XML-SCOPE', $party . '.vat_id', 'Diese Exportversion unterstützt ausschließlich deutsche Umsatzsteuer-IDs im Format DE und neun Ziffern.');
		}
		if (strlen(preg_replace('/\D/', '', $data['seller']['contact_phone'])) < 3) $error('XML-CONTACT', 'seller.contact_phone', 'Die Telefonnummer des Ausstellerkontakts muss mindestens drei Ziffern enthalten.');
		if (!in_array($data['payment']['means_code'], ['10', '30', '58'], true)) $error('XML-SCOPE', 'payment.means_code', 'Diese Zahlungsart ist für den XML-Export nicht freigegeben.');
		if (preg_match('/^\s*#/m', $data['payment']['terms'])) $error('XML-SCOPE', 'payment.terms', 'Strukturierte Skonto-Zahlungsbedingungen sind in dieser Exportversion noch nicht freigegeben.');
		if (!empty($data['payment']['account']['bic']) && !preg_match('/^[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$/', strtoupper(trim($data['payment']['account']['bic'])))) $error('XML-PAYMENT', 'payment.account.bic', 'Die angegebene BIC hat kein gültiges Format.');
		$totals = [];
		foreach (['subtotal', 'taxes', 'total', 'prepayment', 'payable'] as $field) {
			$totals[$field] = $this->cents($data['totals'][$field] ?? ($field === 'prepayment' ? 0 : null));
			if ($totals[$field] === null) $error('XML-DECIMAL', 'totals.' . $field, 'Ein Rechnungsbetrag lässt sich nicht ohne Rundungsänderung als Geldbetrag ausgeben.');
		}
		$lineTotal = 0;
		$lineGroups = [];
		foreach ($data['lines'] as $line) {
			$net = $this->cents($line['net']);
			if ($net === null) {
				$error('XML-DECIMAL', 'lines.' . $line['id'] . '.net', 'Der Positionsbetrag enthält nicht unterstützte Nachkommastellen.');
				continue;
			}
			if (!in_array($line['tax_category'], ['S', 'Z', 'E'], true)
				|| ($line['tax_category'] === 'S' && !in_array((float)$line['tax_rate'], [7.0, 19.0], true))) {
				$error('XML-SCOPE', 'lines.' . $line['id'] . '.tax_category', 'Dieser Steuerfall oder Steuersatz ist für den XML-Export nicht freigegeben.');
			}
			// Check the serialized values against the payload; never replace prices or amounts.
			$quantity = (float)$this->decimal($line['quantity']);
			$price = (float)$this->decimal($line['unit_net_price']);
			if ($this->cents(round($quantity * $price, 2)) !== $net) $error('XML-LINE-AMOUNT', 'lines.' . $line['id'] . '.net', 'Menge, effektiver Einzelpreis und Positionsnetto widersprechen sich.');
			$lineTotal += $net;
			$key = $line['tax_category'] . ':' . $this->decimal($line['tax_rate']);
			$lineGroups[$key] = ($lineGroups[$key] ?? 0) + $net;
		}
		$taxTotal = 0;
		$seen = [];
		foreach ($data['tax_breakdown'] as $group) {
			$key = $group['category'] . ':' . $this->decimal($group['rate']);
			$net = $this->cents($group['net']);
			$tax = $this->cents($group['tax']);
			if (isset($seen[$key]) || $net === null || $tax === null || ($lineGroups[$key] ?? null) !== $net
				|| $this->cents(round((float)$group['net'] * (float)$group['rate'] / 100, 2)) !== $tax) {
				$error('XML-TAX-AMOUNT', 'tax_breakdown', 'Steuergruppe, Bemessungsgrundlage und Steuerbetrag widersprechen sich oder enthalten Rundungsdifferenzen.');
			}
			$seen[$key] = true;
			$taxTotal += $tax ?? 0;
		}
		if (count($seen) !== count($lineGroups) || $lineTotal !== $totals['subtotal'] || $taxTotal !== $totals['taxes']
			|| $totals['subtotal'] + $totals['taxes'] !== $totals['total']
			|| $totals['total'] - $totals['prepayment'] !== $totals['payable']) {
			$error('XML-TOTALS', 'totals', 'Positionssummen, Steuergruppen, Gesamtbeträge und Zahlbetrag stimmen nicht centgenau überein.');
		}
		return $errors;
	}

	private function build(array $data): string
	{
		$document = new DOMDocument('1.0', 'UTF-8');
		$document->formatOutput = true;
		$root = $document->createElementNS(self::NS['Invoice'], 'Invoice');
		$document->appendChild($root);
		foreach (['cac', 'cbc'] as $prefix) $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, self::NS[$prefix]);
		$this->element($root, 'cbc:CustomizationID', $data['specification']);
		$this->element($root, 'cbc:ProfileID', $data['business_process']);
		$this->element($root, 'cbc:ID', $data['number']);
		$this->element($root, 'cbc:IssueDate', $data['issue_date']);
		if ($data['payment']['due_date']) $this->element($root, 'cbc:DueDate', $data['payment']['due_date']);
		$this->element($root, 'cbc:InvoiceTypeCode', '380');
		$this->element($root, 'cbc:DocumentCurrencyCode', $data['currency']);
		$this->element($root, 'cbc:BuyerReference', $data['buyer_reference']);
		if ($data['period_start'] || $data['period_end']) {
			$period = $this->element($root, 'cac:InvoicePeriod');
			if ($data['period_start']) $this->element($period, 'cbc:StartDate', $data['period_start']);
			if ($data['period_end']) $this->element($period, 'cbc:EndDate', $data['period_end']);
		}
		$this->party($root, $data['seller'], true);
		$this->party($root, $data['buyer'], false);
		if ($data['delivery_date']) $this->element($this->element($root, 'cac:Delivery'), 'cbc:ActualDeliveryDate', $data['delivery_date']);
		$means = $this->element($root, 'cac:PaymentMeans');
		$this->element($means, 'cbc:PaymentMeansCode', $data['payment']['means_code']);
		if (in_array($data['payment']['means_code'], ['30', '58'], true)) {
			$account = $data['payment']['account'];
			$accountId = strtoupper(preg_replace('/\s+/', '', (string)($account['iban'] ?? '')));
			if ($accountId === '') $accountId = $account['accountnumber'];
			$financial = $this->element($means, 'cac:PayeeFinancialAccount');
			$this->element($financial, 'cbc:ID', $accountId);
			if (!empty($account['bic'])) $this->element($this->element($financial, 'cac:FinancialInstitutionBranch'), 'cbc:ID', strtoupper(trim($account['bic'])));
		}
		if ($data['payment']['terms'] !== '') $this->element($this->element($root, 'cac:PaymentTerms'), 'cbc:Note', $data['payment']['terms']);
		$currency = ['currencyID' => $data['currency']];
		$taxTotal = $this->element($root, 'cac:TaxTotal');
		$this->element($taxTotal, 'cbc:TaxAmount', $this->money($data['totals']['taxes']), $currency);
		foreach ($data['tax_breakdown'] as $group) {
			$subtotal = $this->element($taxTotal, 'cac:TaxSubtotal');
			$this->element($subtotal, 'cbc:TaxableAmount', $this->money($group['net']), $currency);
			$this->element($subtotal, 'cbc:TaxAmount', $this->money($group['tax']), $currency);
			$this->taxCategory($subtotal, 'cac:TaxCategory', $group['category'], $group['rate'], $group['exemption_reason']);
		}
		$totals = $this->element($root, 'cac:LegalMonetaryTotal');
		foreach (['LineExtensionAmount' => 'subtotal', 'TaxExclusiveAmount' => 'subtotal', 'TaxInclusiveAmount' => 'total', 'PrepaidAmount' => 'prepayment', 'PayableAmount' => 'payable'] as $element => $field) {
			$this->element($totals, 'cbc:' . $element, $this->money($data['totals'][$field] ?? 0), $currency);
		}
		foreach ($data['lines'] as $line) {
			$node = $this->element($root, 'cac:InvoiceLine');
			$this->element($node, 'cbc:ID', $line['id']);
			$this->element($node, 'cbc:InvoicedQuantity', $this->decimal($line['quantity']), ['unitCode' => $line['unit_code']]);
			$this->element($node, 'cbc:LineExtensionAmount', $this->money($line['net']), $currency);
			$item = $this->element($node, 'cac:Item');
			if ((string)$line['description'] !== '') $this->element($item, 'cbc:Description', $line['description']);
			$this->element($item, 'cbc:Name', $line['name']);
			if ((string)$line['sku'] !== '') $this->element($this->element($item, 'cac:SellersItemIdentification'), 'cbc:ID', $line['sku']);
			$this->taxCategory($item, 'cac:ClassifiedTaxCategory', $line['tax_category'], $line['tax_rate']);
			// Price rules are already reflected in unit_net_price; do not deduct them again.
			$this->element($this->element($node, 'cac:Price'), 'cbc:PriceAmount', $this->decimal($line['unit_net_price']), $currency);
		}
		return $document->saveXML();
	}

	private function party(DOMElement $root, array $data, bool $seller): void
	{
		$party = $this->element($this->element($root, $seller ? 'cac:AccountingSupplierParty' : 'cac:AccountingCustomerParty'), 'cac:Party');
		$this->element($party, 'cbc:EndpointID', $data['endpoint'], ['schemeID' => $data['endpoint_scheme']]);
		if ($seller && $data['identifier'] !== '') $this->element($this->element($party, 'cac:PartyIdentification'), 'cbc:ID', $data['identifier']);
		$address = $this->element($party, 'cac:PostalAddress');
		if ($data['street'] !== '') $this->element($address, 'cbc:StreetName', $data['street']);
		$this->element($address, 'cbc:CityName', $data['city']);
		$this->element($address, 'cbc:PostalZone', $data['postcode']);
		$additional = trim(($data['name2'] ?? '') . "\n" . ($data['department'] ?? ''));
		if ($additional !== '') $this->element($this->element($address, 'cac:AddressLine'), 'cbc:Line', $additional);
		$this->element($this->element($address, 'cac:Country'), 'cbc:IdentificationCode', $data['country']);
		foreach (['vat_id' => 'VAT', 'tax_id' => 'FC'] as $field => $scheme) {
			if (empty($data[$field])) continue;
			$tax = $this->element($party, 'cac:PartyTaxScheme');
			$this->element($tax, 'cbc:CompanyID', $data[$field]);
			$this->element($this->element($tax, 'cac:TaxScheme'), 'cbc:ID', $scheme);
		}
		$this->element($this->element($party, 'cac:PartyLegalEntity'), 'cbc:RegistrationName', $data['name']);
		if ($seller) {
			$contact = $this->element($party, 'cac:Contact');
			$this->element($contact, 'cbc:Name', $data['contact_name']);
			$this->element($contact, 'cbc:Telephone', $data['contact_phone']);
			$this->element($contact, 'cbc:ElectronicMail', $data['contact_email']);
		}
	}

	private function taxCategory(DOMElement $parent, string $name, string $category, $rate, string $reason = ''): void
	{
		$tax = $this->element($parent, $name);
		$this->element($tax, 'cbc:ID', $category);
		$this->element($tax, 'cbc:Percent', $this->decimal($rate));
		if ($reason !== '') $this->element($tax, 'cbc:TaxExemptionReason', $reason);
		$this->element($this->element($tax, 'cac:TaxScheme'), 'cbc:ID', 'VAT');
	}

	private function element(DOMElement $parent, string $name, $value = null, array $attributes = []): DOMElement
	{
		$document = $parent->ownerDocument;
		$element = $document->createElementNS(self::NS[explode(':', $name)[0]], $name);
		$parent->appendChild($element);
		foreach ($attributes as $key => $attribute) $element->setAttribute($key, (string)$attribute);
		if ($value !== null) {
			$text = (string)$value;
			if (!preg_match('//u', $text) || preg_match('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $text)) throw new InvalidArgumentException('Ein Rechnungsfeld enthält Zeichen, die in XML 1.0 nicht zulässig sind.');
			$element->appendChild($document->createTextNode($text));
		}
		return $element;
	}

	private function cents($value): ?int
	{
		if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > PHP_INT_MAX / 200
			|| abs((float)$value - round((float)$value, 2)) > 0.000000001) return null;
		return (int)round((float)$value * 100);
	}

	private function money($value): string
	{
		return number_format((float)$value, 2, '.', '');
	}

	private function decimal($value): string
	{
		$text = json_encode((float)$value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
		if (preg_match('/^(-?)(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $text, $match)) {
			$digits = $match[2] . ($match[3] ?? '');
			$point = strlen($match[2]) + (int)$match[4];
			if ($point <= 0) $text = '0.' . str_repeat('0', -$point) . $digits;
			elseif ($point >= strlen($digits)) $text = $digits . str_repeat('0', $point - strlen($digits));
			else $text = substr($digits, 0, $point) . '.' . substr($digits, $point);
			$text = $match[1] . $text;
		}
		if (str_contains($text, '.')) $text = rtrim(rtrim($text, '0'), '.');
		return $text === '-0' ? '0' : $text;
	}
}
