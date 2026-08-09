<?php

class DEEC_Export_Gobd
{
	protected $db;
	protected $clientId;
	protected $userId;

	public function __construct($db, int $clientId, ?int $userId = null)
	{
		$this->db = $db;
		$this->clientId = $clientId;
		$this->userId = $userId;
	}

	public function export(string $filePath, ?string $from, ?string $to): string
	{
		$profile = $this->getProfile();
		$resolvedProfile = [];
		$files = [];

		foreach($profile as $exportName => $config) {
			$columns = $this->filterExistingColumns($config['table'], $config['columns']);
			$config['columns'] = $columns;
			$resolvedProfile[$exportName] = $config;

			$data = $this->fetchData($config, $columns, $from, $to);

			$filename = $exportName . '.csv';
			$this->writeCsv($filePath . $filename, $data, $columns);
			$files[] = $filename;
		}

		$this->writeDataDictionary($filePath . 'data_dictionary.csv', $resolvedProfile);
		$files[] = 'data_dictionary.csv';

		$this->writeRelationships($filePath . 'relationships.csv', $resolvedProfile);
		$files[] = 'relationships.csv';

		$this->writeMetadata($filePath . 'metadata.json', $resolvedProfile, $from, $to);
		$files[] = 'metadata.json';

		$this->writeReadme($filePath . 'readme.txt', $from, $to);
		$files[] = 'readme.txt';

		$zipFileName = $this->buildZipFileName($from, $to);
		$this->createZip($filePath, $files, $zipFileName);

		return $zipFileName;
	}

	protected function fetchData(array $config, array $columns, ?string $from, ?string $to): array
	{
		if (empty($columns)) {
			return [];
		}

		if (!empty($config['parentTable']) && $from && $to) {
			return $this->fetchChildData($config, $columns, $from, $to);
		}

		return $this->fetchTableData($config, $columns, $from, $to);
	}

	protected function fetchTableData(array $config, array $columns, ?string $from, ?string $to): array
	{
		$table = $config['table'];
		$dateField = $config['dateField'] ?? null;

		$sql = 'SELECT ' . $this->buildColumnSql('t', $columns)
			. ' FROM ' . $this->quoteIdentifier($table) . ' t'
			. ' WHERE t.' . $this->quoteIdentifier('clientid') . ' = ?';

		$params = [$this->clientId];

		foreach($config['conditions'] ?? [] as $column => $value) {
			$sql .= ' AND t.' . $this->quoteIdentifier($column) . ' = ?';
			$params[] = $value;
		}

		if($dateField && $from && $to) {
			$sql .= ' AND t.' . $this->quoteIdentifier($dateField) . ' >= ?';
			$sql .= ' AND t.' . $this->quoteIdentifier($dateField) . ' <= ?';
			$params[] = $from;
			$params[] = $to;
		}

		if(in_array('id', $columns, true)) {
			$sql .= ' ORDER BY t.' . $this->quoteIdentifier('id') . ' ASC';
		}

		return $this->db->fetchAll($sql, $params);
	}

	protected function fetchChildData(array $config, array $columns, string $from, string $to): array
	{
		$table = $config['table'];
		$parentTable = $config['parentTable'];
		$parentKey = $config['parentKey'];
		$parentDateField = $config['parentDateField'];

		$sql = 'SELECT ' . $this->buildColumnSql('t', $columns)
			. ' FROM ' . $this->quoteIdentifier($table) . ' t'
			. ' INNER JOIN ' . $this->quoteIdentifier($parentTable) . ' p'
			. ' ON p.' . $this->quoteIdentifier('id') . ' = t.' . $this->quoteIdentifier($parentKey)
			. ' WHERE t.' . $this->quoteIdentifier('clientid') . ' = ?'
			. ' AND p.' . $this->quoteIdentifier('clientid') . ' = ?'
			. ' AND p.' . $this->quoteIdentifier($parentDateField) . ' >= ?'
			. ' AND p.' . $this->quoteIdentifier($parentDateField) . ' <= ?';

		$params = [
			$this->clientId,
			$this->clientId,
			$from,
			$to,
		];

		if (in_array('id', $columns, true)) {
			$sql .= ' ORDER BY t.' . $this->quoteIdentifier('id') . ' ASC';
		}

		return $this->db->fetchAll($sql, $params);
	}

	protected function buildColumnSql(string $alias, array $columns): string
	{
		$parts = [];

		foreach ($columns as $column) {
			$parts[] = $alias . '.' . $this->quoteIdentifier($column)
				. ' AS ' . $this->quoteIdentifier($column);
		}

		return implode(', ', $parts);
	}

	protected function getProfile(): array
	{
		$documentColumns = [
			'id' => 'Internal Dewawi database identifier.',
			'contactid' => 'Visible customer number stored on the document.',
			'clientid' => 'Internal Dewawi client identifier.',
			'header' => 'Document header text.',
			'footer' => 'Document footer text.',
			'vatin' => 'VAT identification number stored on the document.',
			'orderdate' => 'Date of the customer order.',
			'deliverydate' => 'Date of delivery or service.',
			'paymentmethod' => 'Payment method agreed for the document.',
			'shippingmethod' => 'Shipping or delivery method.',
			'billingname1' => 'Primary billing address name stored on the document.',
			'billingname2' => 'Secondary billing address name stored on the document.',
			'billingdepartment' => 'Billing department stored on the document.',
			'billingstreet' => 'Billing street stored on the document.',
			'billingpostcode' => 'Billing postal code stored on the document.',
			'billingcity' => 'Billing city stored on the document.',
			'billingcountry' => 'Billing country stored on the document.',
			'shippingname1' => 'Primary shipping address name stored on the document.',
			'shippingname2' => 'Secondary shipping address name stored on the document.',
			'shippingdepartment' => 'Shipping department stored on the document.',
			'shippingstreet' => 'Shipping street stored on the document.',
			'shippingpostcode' => 'Shipping postal code stored on the document.',
			'shippingcity' => 'Shipping city stored on the document.',
			'shippingcountry' => 'Shipping country stored on the document.',
			'shippingphone' => 'Shipping phone number stored on the document.',
			'subtotal' => 'Document subtotal before taxes.',
			'taxes' => 'Total tax amount of the document.',
			'total' => 'Total document amount.',
			'currency' => 'Currency used for monetary amounts.',
			'taxfree' => 'Indicates whether the document is treated as tax exempt.',
			'state' => 'Internal Dewawi document state.',
			'completed' => 'Indicates whether processing of the document has been completed.',
			'cancelled' => 'Indicates whether the document has been cancelled.',
			'filename' => 'Filename of the generated document file, if available.',
			'created' => 'Date and time the database record was created.',
			'deleted' => 'Logical deletion marker.',
		];

		$positionColumns = [
			'id' => 'Internal Dewawi database identifier.',
			'parentid' => 'Internal identifier of the parent document.',
			'itemid' => 'Reference to the item master record, if available.',
			'masterid' => 'Reference to the originating or master position, if available.',
			'possetid' => 'Internal identifier of the position set.',
			'clientid' => 'Internal Dewawi client identifier.',
			'sku' => 'Item number stored on the document position.',
			'title' => 'Position title stored on the document.',
			'description' => 'Position description stored on the document.',
			'price' => 'Unit price of the position.',
			'taxrate' => 'Tax rate applied to the position.',
			'quantity' => 'Quantity of the position.',
			'total' => 'Total amount of the position.',
			'currency' => 'Currency used for the position.',
			'uom' => 'Unit of measure.',
			'manufacturerid' => 'Reference to the manufacturer, if available.',
			'manufacturersku' => 'Manufacturer item number.',
			'ordering' => 'Position order within the document.',
			'created' => 'Date and time the position was created.',
			'deleted' => 'Logical deletion marker.',
		];

		return [
			'customers' => [
				'table' => 'contact',
				'title' => 'Customers',
				'description' => 'Customer master records used by sales and accounting documents.',
				'dateField' => null,
				'columns' => [
					'id', 'contactid', 'clientid', 'type',
					'name1', 'name2', 'department',
					'taxnumber', 'vatin', 'taxfree',
					'currency', 'debitornumber',
					'paymentmethod', 'paymentterm',
					'cashdiscountdays', 'cashdiscountpercent',
					'created', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal Dewawi customer identifier.',
					'contactid' => 'Visible customer number.',
					'clientid' => 'Internal Dewawi client identifier.',
					'type' => 'Customer or contact type.',
					'name1' => 'Primary customer name.',
					'name2' => 'Secondary customer name.',
					'department' => 'Customer department.',
					'taxnumber' => 'Customer tax number.',
					'vatin' => 'Customer VAT identification number.',
					'taxfree' => 'Indicates whether the customer is treated as tax exempt.',
					'currency' => 'Default customer currency.',
					'debitornumber' => 'Accounting debtor number.',
					'paymentmethod' => 'Default payment method.',
					'paymentterm' => 'Default payment term.',
					'cashdiscountdays' => 'Number of days allowed for cash discount.',
					'cashdiscountpercent' => 'Cash discount percentage.',
					'created' => 'Date and time the customer record was created.',
					'deleted' => 'Logical deletion marker.',
				],
			],

			'customer_addresses' => [
				'table' => 'address',
				'title' => 'Customer addresses',
				'description' => 'Addresses assigned to customer master records.',
				'dateField' => null,
				'conditions' => [
					'module' => 'contacts',
					'controller' => 'contact',
				],
				'columns' => [
					'id', 'parentid', 'type',
					'name1', 'name2', 'department',
					'street', 'postcode', 'city', 'country',
					'phone', 'ordering', 'clientid',
					'created', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal address identifier.',
					'parentid' => 'Internal identifier of the customer owning the address.',
					'type' => 'Address type, such as billing or shipping address.',
					'name1' => 'Primary address name.',
					'name2' => 'Secondary address name.',
					'department' => 'Department.',
					'street' => 'Street address.',
					'postcode' => 'Postal code.',
					'city' => 'City.',
					'country' => 'Country.',
					'phone' => 'Phone number stored with the address.',
					'ordering' => 'Display order of the address.',
					'clientid' => 'Internal Dewawi client identifier.',
					'created' => 'Date and time the address was created.',
					'deleted' => 'Logical deletion marker.',
				],
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'customers.csv',
						'targetColumn' => 'id',
						'description' => 'Customer owning this address.',
					],
				],
			],

			'quotes' => [
				'table' => 'quote',
				'title' => 'Quotes',
				'description' => 'Commercial quotations issued to customers.',
				'dateField' => 'quotedate',
				'columns' => [
					'id', 'quoteid', 'contactid', 'clientid',
					'vatin', 'header', 'footer',
					'quotedate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingdepartment',
					'billingstreet', 'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingdepartment',
					'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry',
					'shippingphone',
					'subtotal', 'taxes', 'total',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
				'columnDescriptions' => array_merge($documentColumns, [
					'quoteid' => 'Visible quote number.',
					'quotedate' => 'Quote date.',
				]),
				'relations' => [
					[
						'column' => 'contactid',
						'target' => 'customers.csv',
						'targetColumn' => 'contactid',
						'description' => 'Customer number referenced by the quote.',
					],
				],
			],

			'quote_positions' => [
				'table' => 'quotepos',
				'title' => 'Quote positions',
				'description' => 'Individual goods and service positions belonging to quotations.',
				'dateField' => null,
				'parentTable' => 'quote',
				'parentKey' => 'parentid',
				'parentDateField' => 'quotedate',
				'columns' => [
					'id', 'parentid', 'itemid', 'masterid', 'possetid',
					'clientid', 'sku', 'title', 'description',
					'price', 'taxrate', 'quantity', 'total',
					'currency', 'uom', 'manufacturerid',
					'manufacturersku', 'ordering',
					'created', 'deleted',
				],
				'columnDescriptions' => $positionColumns,
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'quotes.csv',
						'targetColumn' => 'id',
						'description' => 'Quote containing this position.',
					],
					[
						'column' => 'itemid',
						'target' => 'items.csv',
						'targetColumn' => 'id',
						'description' => 'Item master record referenced by the position, if available.',
					],
				],
			],

			'salesorders' => [
				'table' => 'salesorder',
				'title' => 'Sales orders',
				'description' => 'Customer orders confirmed by the company.',
				'dateField' => 'salesorderdate',
				'columns' => [
					'id', 'salesorderid', 'quoteid', 'contactid', 'clientid',
					'vatin', 'header', 'footer',
					'salesorderdate', 'quotedate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingdepartment',
					'billingstreet', 'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingdepartment',
					'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry',
					'shippingphone',
					'subtotal', 'taxes', 'total',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
				'columnDescriptions' => array_merge($documentColumns, [
					'salesorderid' => 'Visible sales order number.',
					'quoteid' => 'Visible number of the preceding quote, if available.',
					'salesorderdate' => 'Sales order date.',
					'quotedate' => 'Date of the preceding quote, if available.',
				]),
				'relations' => [
					[
						'column' => 'contactid',
						'target' => 'customers.csv',
						'targetColumn' => 'contactid',
						'description' => 'Customer number referenced by the sales order.',
					],
					[
						'column' => 'quoteid',
						'target' => 'quotes.csv',
						'targetColumn' => 'quoteid',
						'description' => 'Preceding quote, if available.',
					],
				],
			],

			'salesorder_positions' => [
				'table' => 'salesorderpos',
				'title' => 'Sales order positions',
				'description' => 'Individual goods and service positions belonging to sales orders.',
				'dateField' => null,
				'parentTable' => 'salesorder',
				'parentKey' => 'parentid',
				'parentDateField' => 'salesorderdate',
				'columns' => [
					'id', 'parentid', 'itemid', 'masterid', 'possetid',
					'clientid', 'sku', 'title', 'description',
					'price', 'taxrate', 'quantity', 'total',
					'currency', 'uom', 'manufacturerid',
					'manufacturersku', 'ordering',
					'created', 'deleted',
				],
				'columnDescriptions' => $positionColumns,
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'salesorders.csv',
						'targetColumn' => 'id',
						'description' => 'Sales order containing this position.',
					],
					[
						'column' => 'itemid',
						'target' => 'items.csv',
						'targetColumn' => 'id',
						'description' => 'Item master record referenced by the position, if available.',
					],
				],
			],

			'invoices' => [
				'table' => 'invoice',
				'title' => 'Invoices',
				'description' => 'Customer invoices issued by the company.',
				'dateField' => 'invoicedate',
				'columns' => [
					'id', 'invoiceid', 'quoteid', 'salesorderid',
					'deliveryorderid', 'contactid', 'clientid',
					'vatin', 'header', 'footer',
					'invoicedate', 'quotedate', 'salesorderdate',
					'deliveryorderdate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingdepartment',
					'billingstreet', 'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingdepartment',
					'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry',
					'shippingphone',
					'subtotal', 'taxes', 'total', 'prepayment',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
				'columnDescriptions' => array_merge($documentColumns, [
					'invoiceid' => 'Visible invoice number.',
					'quoteid' => 'Visible number of the preceding quote, if available.',
					'salesorderid' => 'Visible number of the preceding sales order, if available.',
					'deliveryorderid' => 'Visible number of the preceding delivery order, if available.',
					'invoicedate' => 'Invoice date.',
					'quotedate' => 'Date of the preceding quote, if available.',
					'salesorderdate' => 'Date of the preceding sales order, if available.',
					'deliveryorderdate' => 'Date of the preceding delivery order, if available.',
					'prepayment' => 'Prepayment amount credited against the invoice.',
				]),
				'relations' => [
					[
						'column' => 'contactid',
						'target' => 'customers.csv',
						'targetColumn' => 'contactid',
						'description' => 'Customer number referenced by the invoice.',
					],
					[
						'column' => 'quoteid',
						'target' => 'quotes.csv',
						'targetColumn' => 'quoteid',
						'description' => 'Preceding quote, if available.',
					],
					[
						'column' => 'salesorderid',
						'target' => 'salesorders.csv',
						'targetColumn' => 'salesorderid',
						'description' => 'Preceding sales order, if available.',
					],
				],
			],

			'invoice_positions' => [
				'table' => 'invoicepos',
				'title' => 'Invoice positions',
				'description' => 'Individual goods and service positions belonging to customer invoices.',
				'dateField' => null,
				'parentTable' => 'invoice',
				'parentKey' => 'parentid',
				'parentDateField' => 'invoicedate',
				'columns' => [
					'id', 'parentid', 'itemid', 'masterid', 'possetid',
					'clientid', 'sku', 'title', 'description',
					'price', 'taxrate', 'quantity', 'total',
					'currency', 'uom', 'manufacturerid',
					'manufacturersku', 'ordering',
					'created', 'deleted',
				],
				'columnDescriptions' => $positionColumns,
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'invoices.csv',
						'targetColumn' => 'id',
						'description' => 'Invoice containing this position.',
					],
					[
						'column' => 'itemid',
						'target' => 'items.csv',
						'targetColumn' => 'id',
						'description' => 'Item master record referenced by the position, if available.',
					],
				],
			],

			'creditnotes' => [
				'table' => 'creditnote',
				'title' => 'Credit notes',
				'description' => 'Customer credit notes issued in relation to previous sales transactions.',
				'dateField' => 'creditnotedate',
				'columns' => [
					'id', 'creditnoteid', 'quoteid', 'salesorderid',
					'invoiceid', 'contactid', 'clientid',
					'vatin', 'header', 'footer',
					'creditnotedate', 'quotedate', 'salesorderdate',
					'invoicedate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingdepartment',
					'billingstreet', 'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingdepartment',
					'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry',
					'shippingphone',
					'subtotal', 'taxes', 'total',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
				'columnDescriptions' => array_merge($documentColumns, [
					'creditnoteid' => 'Visible credit note number.',
					'quoteid' => 'Visible number of the preceding quote, if available.',
					'salesorderid' => 'Visible number of the preceding sales order, if available.',
					'invoiceid' => 'Visible number of the referenced invoice, if available.',
					'creditnotedate' => 'Credit note date.',
					'quotedate' => 'Date of the preceding quote, if available.',
					'salesorderdate' => 'Date of the preceding sales order, if available.',
					'invoicedate' => 'Date of the referenced invoice, if available.',
				]),
				'relations' => [
					[
						'column' => 'contactid',
						'target' => 'customers.csv',
						'targetColumn' => 'contactid',
						'description' => 'Customer number referenced by the credit note.',
					],
					[
						'column' => 'quoteid',
						'target' => 'quotes.csv',
						'targetColumn' => 'quoteid',
						'description' => 'Preceding quote, if available.',
					],
					[
						'column' => 'salesorderid',
						'target' => 'salesorders.csv',
						'targetColumn' => 'salesorderid',
						'description' => 'Preceding sales order, if available.',
					],
					[
						'column' => 'invoiceid',
						'target' => 'invoices.csv',
						'targetColumn' => 'invoiceid',
						'description' => 'Invoice referenced by the credit note, if available.',
					],
				],
			],

			'creditnote_positions' => [
				'table' => 'creditnotepos',
				'title' => 'Credit note positions',
				'description' => 'Individual positions belonging to customer credit notes.',
				'dateField' => null,
				'parentTable' => 'creditnote',
				'parentKey' => 'parentid',
				'parentDateField' => 'creditnotedate',
				'columns' => [
					'id', 'parentid', 'itemid', 'masterid', 'possetid',
					'clientid', 'sku', 'title', 'description',
					'price', 'taxrate', 'quantity', 'total',
					'currency', 'uom', 'manufacturerid',
					'manufacturersku', 'ordering',
					'created', 'deleted',
				],
				'columnDescriptions' => $positionColumns,
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'creditnotes.csv',
						'targetColumn' => 'id',
						'description' => 'Credit note containing this position.',
					],
					[
						'column' => 'itemid',
						'target' => 'items.csv',
						'targetColumn' => 'id',
						'description' => 'Item master record referenced by the position, if available.',
					],
				],
			],

			'items' => [
				'table' => 'item',
				'title' => 'Items',
				'description' => 'Item master records referenced by document positions.',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'catid', 'sku', 'gtin',
					'title', 'subtitle', 'type', 'description',
					'quantity', 'inventory', 'cost', 'price',
					'specialprice', 'margin', 'currency',
					'taxid', 'uomid', 'manufacturerid',
					'manufacturersku', 'manufacturergtin',
					'origincountry', 'created', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal item identifier.',
					'clientid' => 'Internal Dewawi client identifier.',
					'catid' => 'Internal item category identifier.',
					'sku' => 'Item number.',
					'gtin' => 'Global Trade Item Number, if available.',
					'title' => 'Item name.',
					'subtitle' => 'Additional item name.',
					'type' => 'Item type.',
					'description' => 'Item description.',
					'quantity' => 'Current item quantity value.',
					'inventory' => 'Inventory management flag or state.',
					'cost' => 'Stored item cost.',
					'price' => 'Stored item sales price.',
					'specialprice' => 'Stored special sales price.',
					'margin' => 'Stored item margin.',
					'currency' => 'Currency used for item prices.',
					'taxid' => 'Reference to the tax rate master record.',
					'uomid' => 'Reference to the unit of measure master record.',
					'manufacturerid' => 'Reference to the manufacturer master record.',
					'manufacturersku' => 'Manufacturer item number.',
					'manufacturergtin' => 'Manufacturer GTIN.',
					'origincountry' => 'Country of origin.',
					'created' => 'Date and time the item record was created.',
					'deleted' => 'Logical deletion marker.',
				],
				'relations' => [
					[
						'column' => 'taxid',
						'target' => 'taxrates.csv',
						'targetColumn' => 'id',
						'description' => 'Tax rate assigned to the item, if available.',
					],
				],
			],

			'taxrates' => [
				'table' => 'taxrate',
				'title' => 'Tax rates',
				'description' => 'Tax rates configured in Dewawi.',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'name', 'rate',
					'ordering', 'created', 'modified', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal tax rate identifier.',
					'clientid' => 'Internal Dewawi client identifier.',
					'name' => 'Tax rate name.',
					'rate' => 'Tax rate percentage.',
					'ordering' => 'Display order.',
					'created' => 'Date and time the tax rate record was created.',
					'modified' => 'Date and time the tax rate record was last modified.',
					'deleted' => 'Logical deletion marker.',
				],
			],

			'payment_methods' => [
				'table' => 'paymentmethod',
				'title' => 'Payment methods',
				'description' => 'Payment methods configured in Dewawi.',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'title',
					'ordering', 'created', 'modified', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal payment method identifier.',
					'clientid' => 'Internal Dewawi client identifier.',
					'title' => 'Payment method name.',
					'ordering' => 'Display order.',
					'created' => 'Date and time the payment method record was created.',
					'modified' => 'Date and time the payment method record was last modified.',
					'deleted' => 'Logical deletion marker.',
				],
			],

			'shipping_methods' => [
				'table' => 'shippingmethod',
				'title' => 'Shipping methods',
				'description' => 'Shipping and delivery methods configured in Dewawi.',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'title',
					'ordering', 'created', 'modified', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal shipping method identifier.',
					'clientid' => 'Internal Dewawi client identifier.',
					'title' => 'Shipping method name.',
					'ordering' => 'Display order.',
					'created' => 'Date and time the shipping method record was created.',
					'modified' => 'Date and time the shipping method record was last modified.',
					'deleted' => 'Logical deletion marker.',
				],
			],
		];
	}

	protected function filterExistingColumns(string $table, array $columns): array
	{
		$description = $this->db->describeTable($table);
		$existing = [];

		foreach ($columns as $column) {
			if (array_key_exists($column, $description)) {
				$existing[] = $column;
			}
		}

		return $existing;
	}

	protected function writeCsv(string $filename, array $rows, array $columns): void
	{
		$handle = fopen($filename, 'w');

		if (!$handle) {
			throw new RuntimeException('Could not create CSV file: ' . $filename);
		}

		fputcsv($handle, $columns, ';');

		foreach ($rows as $row) {
			$line = [];

			foreach ($columns as $column) {
				$line[] = $this->normalizeValue($row[$column] ?? null);
			}

			fputcsv($handle, $line, ';');
		}

		fclose($handle);
	}

	protected function normalizeValue($value)
	{
		if($value === null) {
			return '';
		}

		if($value instanceof DateTime) {
			return $value->format('Y-m-d H:i:s');
		}

		if(is_bool($value)) {
			return $value ? '1' : '0';
		}

		return (string)$value;
	}

	protected function writeDataDictionary(string $filename, array $profile): void
	{
		$handle = fopen($filename, 'w');

		if (!$handle) {
			throw new RuntimeException(
				'Could not create data dictionary: ' . $filename
			);
		}

		fputcsv($handle, [
			'file',
			'table',
			'title',
			'description',
			'column',
			'column_description',
		], ';');

		foreach ($profile as $exportName => $config) {
			$descriptions = $config['columnDescriptions'] ?? [];

			foreach ($config['columns'] as $column) {
				fputcsv($handle, [
					$exportName . '.csv',
					$config['table'],
					$config['title'] ?? $exportName,
					$config['description'] ?? '',
					$column,
					$descriptions[$column] ?? '',
				], ';');
			}
		}

		fclose($handle);
	}

	protected function writeRelationships(string $filename, array $profile): void
	{
		$handle = fopen($filename, 'w');

		if (!$handle) {
			throw new RuntimeException(
				'Could not create relationships file: ' . $filename
			);
		}

		fputcsv($handle, [
			'source_file',
			'source_column',
			'target_file',
			'target_column',
			'description',
		], ';');

		foreach ($profile as $exportName => $config) {
			foreach ($config['relations'] ?? [] as $relation) {
				fputcsv($handle, [
					$exportName . '.csv',
					$relation['column'],
					$relation['target'],
					$relation['targetColumn'],
					$relation['description'] ?? '',
				], ';');
			}
		}

		fclose($handle);
	}

	protected function writeMetadata(string $filename, array $profile, ?string $from, ?string $to): void
	{
		$metadata = [
			'system' => 'Dewawi',
			'exportType' => 'GoBD',
			'createdAt' => date('c'),
			'clientId' => $this->clientId,
			'userId' => $this->userId,
			'from' => $from,
			'to' => $to,
			'encoding' => 'UTF-8',
			'delimiter' => ';',
			'decimalFormat' => '1234.56',
			'dateFormat' => 'YYYY-MM-DD',
			'dateTimeFormat' => 'YYYY-MM-DD HH:MM:SS',
			'nullValue' => 'empty field',
			'tables' => [],
		];

		foreach($profile as $exportName => $config) {
			$metadata['tables'][$exportName] = [
				'file' => $exportName . '.csv',
				'table' => $config['table'],
				'title' => $config['title'] ?? $exportName,
				'description' => $config['description'] ?? '',
				'dateField' => $config['dateField'],
				'parentTable' => $config['parentTable'] ?? null,
				'parentKey' => $config['parentKey'] ?? null,
				'parentDateField' => $config['parentDateField'] ?? null,
				'conditions' => $config['conditions'] ?? [],
				'columns' => $config['columns'],
				'columnDescriptions' => $config['columnDescriptions'] ?? [],
				'relations' => $config['relations'] ?? [],
			];
		}

		file_put_contents(
			$filename,
			json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
		);
	}

	protected function writeReadme(string $filename, ?string $from, ?string $to): void
	{
		$content = [];
		$content[] = 'Dewawi GoBD Export';
		$content[] = 'Created: ' . date('Y-m-d H:i:s');
		$content[] = 'Client ID: ' . $this->clientId;
		$content[] = 'From: ' . ($from ?: '-');
		$content[] = 'To: ' . ($to ?: '-');
		$content[] = '';
		$content[] = 'Format: CSV';
		$content[] = 'Encoding: UTF-8';
		$content[] = 'Delimiter: semicolon';
		$content[] = 'Decimal format: 1234.56';
		$content[] = 'Date format: YYYY-MM-DD';
		$content[] = 'Date/time format: YYYY-MM-DD HH:MM:SS';
		$content[] = 'NULL values are represented as empty CSV fields.';
		$content[] = '';
		$content[] = 'The selected date range is applied to dated sales documents and their positions.';
		$content[] = 'Master and reference data are exported independently of the selected date range so referenced records remain available for interpretation.';
		$content[] = 'Rows marked with deleted = 1 are included when present in the source data.';
		$content[] = 'The export represents records stored in Dewawi at the time of export and does not reconstruct previous versions of modified records.';
		$content[] = 'Document title, subject, reference, info and notes fields are intentionally excluded from the export.';
		$content[] = 'Document header and footer fields remain included.';
		$content[] = 'Billing and shipping address fields represent the address values stored directly on each document.';
		$content[] = 'Customer addresses are exported separately and are restricted to addresses assigned to contact master records.';
		$content[] = 'Generic modified fields are excluded from fiscal document exports because they may include non-fiscal internal updates.';
		$content[] = 'data_dictionary.csv describes the meaning of exported files and columns.';
		$content[] = 'relationships.csv describes known relationships between exported datasets.';
		$content[] = 'metadata.json describes export settings, datasets, filters and exported columns.';

		file_put_contents($filename, implode("\n", $content));
	}

	protected function createZip(string $filePath, array $files, string $zipFileName): void
	{
		$zip = new ZipArchive();

		if ($zip->open($filePath . $zipFileName, ZipArchive::CREATE) !== true) {
			throw new RuntimeException('Could not create ZIP archive.');
		}

		foreach ($files as $file) {
			$fullPath = $filePath . $file;

			if (file_exists($fullPath)) {
				$zip->addFile($fullPath, $file);
			}
		}

		$zip->close();

		foreach ($files as $file) {
			$fullPath = $filePath . $file;

			if (file_exists($fullPath)) {
				unlink($fullPath);
			}
		}
	}

	protected function buildZipFileName(?string $from, ?string $to): string
	{
		return 'gobd-export-'
			. $this->formatDateForFilename($from)
			. '-'
			. $this->formatDateForFilename($to)
			. '-'
			. date('Ymd-His')
			. '.zip';
	}

	protected function formatDateForFilename(?string $date): string
	{
		if (!$date) {
			return 'unknown';
		}

		$timestamp = strtotime($date);

		if (!$timestamp) {
			return 'unknown';
		}

		return date('Ymd', $timestamp);
	}

	protected function quoteIdentifier(string $name): string
	{
		return '`' . str_replace('`', '``', $name) . '`';
	}
}
