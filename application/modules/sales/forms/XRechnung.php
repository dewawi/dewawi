<?php

class Sales_Form_XRechnung extends DEEC_Form
{
	private const BUSINESS_PROCESS = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';
	private const UNIT_CODES = [
		'C62' => 'C62 – Unit',
		'H87' => 'H87 – Piece',
		'HUR' => 'HUR – Hour',
		'DAY' => 'DAY – Day',
		'MON' => 'MON – Month',
		'ANN' => 'ANN – Year',
		'KGM' => 'KGM – Kilogram',
		'GRM' => 'GRM – Gram',
		'MTR' => 'MTR – Metre',
		'MTK' => 'MTK – Square metre',
		'MTQ' => 'MTQ – Cubic metre',
		'LTR' => 'LTR – Litre',
		'KWH' => 'KWH – Kilowatt hour',
		'TNE' => 'TNE – Tonne',
		'MIN' => 'MIN – Minute',
		'SEC' => 'SEC – Second',
		'SET' => 'SET – Set',
		'XPK' => 'XPK – Package',
	];

	public function __construct(array $units = [], array $lines = [], array $bankAccounts = [])
	{
		$this->addSellerElements();
		$this->addBuyerElements();
		$this->addPaymentElements($bankAccounts);
		$this->addInvoiceElements();
		$this->addUnitElements($units);
		$this->addTaxElements($lines);
	}

	public static function getBusinessProcess(): string
	{
		return self::BUSINESS_PROCESS;
	}

	public static function getUnitCodes(): array
	{
		return self::UNIT_CODES;
	}

	private function addSellerElements(): void
	{
		$this->addElement([
			'name' => 'seller_vat_id',
			'type' => 'text',
			'label' => 'XRECHNUNG_SELLER_VAT_ID',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 32, 'pattern' => 'DE[0-9]{9}'],
			'section' => 'XRECHNUNG_SELLER',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'seller_tax_id',
			'type' => 'text',
			'label' => 'XRECHNUNG_SELLER_TAX_ID',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 64],
			'section' => 'XRECHNUNG_SELLER',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'seller_identifier',
			'type' => 'text',
			'label' => 'XRECHNUNG_SELLER_IDENTIFIER',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'section' => 'XRECHNUNG_SELLER',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'seller_contact_name',
			'type' => 'text',
			'label' => 'XRECHNUNG_CONTACT_NAME',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'section' => 'XRECHNUNG_SELLER',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'seller_contact_phone',
			'type' => 'text',
			'label' => 'XRECHNUNG_CONTACT_PHONE',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 64],
			'section' => 'XRECHNUNG_SELLER',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'seller_contact_email',
			'type' => 'email',
			'label' => 'XRECHNUNG_CONTACT_EMAIL',
			'required' => true,
			'format' => ['type' => 'email'],
			'attribs' => ['maxlength' => 255],
			'section' => 'XRECHNUNG_SELLER',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'seller_endpoint',
			'type' => 'email',
			'label' => 'XRECHNUNG_SELLER_ENDPOINT',
			'required' => true,
			'format' => ['type' => 'email'],
			'attribs' => ['maxlength' => 255],
			'section' => 'XRECHNUNG_SELLER',
			'col' => 6,
		]);
		$this->addElement([
			'name' => 'seller_endpoint_scheme',
			'type' => 'hidden',
			'default' => 'EM',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['pattern' => 'EM'],
			'wrap' => false,
		]);
	}

	private function addBuyerElements(): void
	{
		$this->addElement([
			'name' => 'buyer_reference',
			'type' => 'text',
			'label' => 'XRECHNUNG_BUYER_REFERENCE',
			'description' => 'XRECHNUNG_BUYER_REFERENCE_HELP',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'section' => 'XRECHNUNG_BUYER',
			'col' => 6,
		]);
		$this->addElement([
			'name' => 'buyer_endpoint',
			'type' => 'email',
			'label' => 'XRECHNUNG_BUYER_ENDPOINT',
			'required' => true,
			'format' => ['type' => 'email'],
			'attribs' => ['maxlength' => 255],
			'section' => 'XRECHNUNG_BUYER',
			'col' => 6,
		]);
		$this->addElement([
			'name' => 'buyer_endpoint_scheme',
			'type' => 'hidden',
			'default' => 'EM',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['pattern' => 'EM'],
			'wrap' => false,
		]);
	}

	private function addPaymentElements(array $bankAccounts): void
	{
		$accountPattern = $bankAccounts ? '(?:' . implode('|', array_map('intval', array_keys($bankAccounts))) . ')' : '(?!)';
		$this->addElement([
			'name' => 'payment_means_code',
			'type' => 'select',
			'label' => 'XRECHNUNG_PAYMENT_MEANS',
			'options' => [
				'' => 'XRECHNUNG_SELECT',
				'10' => 'XRECHNUNG_PAYMENT_10',
				'30' => 'XRECHNUNG_PAYMENT_30',
				'58' => 'XRECHNUNG_PAYMENT_58',
			],
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['pattern' => '(?:10|30|58)'],
			'section' => 'XRECHNUNG_PAYMENT',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'bank_account_id',
			'type' => 'select',
			'label' => 'XRECHNUNG_BANK_ACCOUNT',
			'options' => ['' => 'XRECHNUNG_SELECT'] + $bankAccounts,
			'format' => ['type' => 'int'],
			'attribs' => ['pattern' => $accountPattern],
			'section' => 'XRECHNUNG_PAYMENT',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'payment_due_date',
			'type' => 'text',
			'label' => 'XRECHNUNG_DUE_DATE',
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'datePicker', 'pattern' => '(?:[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}\.[0-9]{2}\.[0-9]{4})'],
			'section' => 'XRECHNUNG_PAYMENT',
			'col' => 4,
		]);
		$this->addElement([
			'name' => 'payment_terms',
			'type' => 'textarea',
			'label' => 'XRECHNUNG_PAYMENT_TERMS',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 1000, 'rows' => 3],
			'section' => 'XRECHNUNG_PAYMENT',
			'col' => 12,
		]);
	}

	private function addInvoiceElements(): void
	{
		$this->addElement([
			'name' => 'business_process',
			'type' => 'text',
			'label' => 'XRECHNUNG_BUSINESS_PROCESS',
			'default' => self::BUSINESS_PROCESS,
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['readonly' => 'readonly', 'pattern' => preg_quote(self::BUSINESS_PROCESS, '~')],
			'section' => 'XRECHNUNG_INVOICE_DATA',
			'col' => 12,
		]);
		foreach (['period_start' => 'XRECHNUNG_PERIOD_START', 'period_end' => 'XRECHNUNG_PERIOD_END'] as $name => $label) {
			$this->addElement([
				'name' => $name,
				'type' => 'text',
				'label' => $label,
				'format' => ['type' => 'string'],
				'attribs' => ['class' => 'datePicker', 'pattern' => '(?:[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}\.[0-9]{2}\.[0-9]{4})'],
				'section' => 'XRECHNUNG_INVOICE_DATA',
				'col' => 6,
			]);
		}
	}

	private function addUnitElements(array $units): void
	{
		foreach ($units as $unit) {
			$this->addElement([
				'name' => $unit['field'],
				'type' => 'select',
				'label' => 'XRECHNUNG_UNIT_CODE',
				'info' => (string)$unit['uom'],
				'options' => ['' => 'XRECHNUNG_SELECT'] + self::UNIT_CODES,
				'required' => true,
				'format' => ['type' => 'string'],
				'attribs' => ['pattern' => '(?:' . implode('|', array_keys(self::UNIT_CODES)) . ')'],
				'section' => 'XRECHNUNG_UNITS',
				'col' => 6,
			]);
		}
	}

	private function addTaxElements(array $lines): void
	{
		foreach ($lines as $line) {
			$info = '#' . (int)$line['id'] . ' – ' . (string)$line['title'] . ' (' . (string)$line['tax_rate'] . '%)';
			$this->addElement([
				'name' => $line['category_field'],
				'type' => 'select',
				'label' => 'XRECHNUNG_TAX_CATEGORY',
				'info' => $info,
				'options' => [
					'' => 'XRECHNUNG_SELECT',
					'S' => 'XRECHNUNG_TAX_S',
					'Z' => 'XRECHNUNG_TAX_Z',
					'E' => 'XRECHNUNG_TAX_E',
				],
				'required' => true,
				'format' => ['type' => 'string'],
				'attribs' => ['pattern' => '(?:S|Z|E)'],
				'section' => 'XRECHNUNG_TAXES',
				'col' => 4,
			]);
			$this->addElement([
				'name' => $line['reason_field'],
				'type' => 'text',
				'label' => 'XRECHNUNG_EXEMPTION_REASON',
				'info' => $info,
				'format' => ['type' => 'string'],
				'attribs' => ['maxlength' => 255],
				'section' => 'XRECHNUNG_TAXES',
				'col' => 8,
			]);
		}
	}
}
