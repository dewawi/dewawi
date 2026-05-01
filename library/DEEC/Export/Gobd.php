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
		$files = [];

		foreach ($profile as $exportName => $config) {
			$columns = $this->filterExistingColumns($config['table'], $config['columns']);
			$data = $this->fetchData($config, $columns, $from, $to);

			$filename = $exportName . '.csv';
			$this->writeCsv($filePath . $filename, $data, $columns);
			$files[] = $filename;
		}

		$this->writeMetadata($filePath . 'metadata.json', $profile, $from, $to);
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

		if ($dateField && $from && $to) {
			$sql .= ' AND t.' . $this->quoteIdentifier($dateField) . ' >= ?';
			$sql .= ' AND t.' . $this->quoteIdentifier($dateField) . ' <= ?';
			$params[] = $from;
			$params[] = $to;
		}

		if (in_array('id', $columns, true)) {
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
		return [
			'customers' => [
				'table' => 'contact',
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
			],

			'customer_addresses' => [
				'table' => 'address',
				'dateField' => null,
				'columns' => [
					'id', 'module', 'controller', 'parentid', 'type',
					'name1', 'name2', 'department',
					'street', 'postcode', 'city', 'country',
					'phone', 'ordering', 'clientid',
					'created', 'deleted',
				],
			],

			'invoices' => [
				'table' => 'invoice',
				'dateField' => 'invoicedate',
				'columns' => [
					'id', 'invoiceid', 'quoteid', 'salesorderid',
					'deliveryorderid', 'contactid', 'clientid',
					'reference', 'vatin',
					'invoicedate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingstreet',
					'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingstreet',
					'shippingpostcode', 'shippingcity', 'shippingcountry',
					'subtotal', 'taxes', 'total', 'prepayment',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
			],

			'invoice_positions' => [
				'table' => 'invoicepos',
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
			],

			'creditnotes' => [
				'table' => 'creditnote',
				'dateField' => 'creditnotedate',
				'columns' => [
					'id', 'creditnoteid', 'quoteid', 'salesorderid',
					'invoiceid', 'contactid', 'clientid',
					'reference', 'vatin',
					'creditnotedate', 'invoicedate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingstreet',
					'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingstreet',
					'shippingpostcode', 'shippingcity', 'shippingcountry',
					'subtotal', 'taxes', 'total',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
			],

			'creditnote_positions' => [
				'table' => 'creditnotepos',
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
			],

			'items' => [
				'table' => 'item',
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
			],

			'taxrates' => [
				'table' => 'taxrate',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'name', 'rate',
					'ordering', 'created', 'modified', 'deleted',
				],
			],

			'payment_methods' => [
				'table' => 'paymentmethod',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'title',
					'ordering', 'created', 'modified', 'deleted',
				],
			],

			'shipping_methods' => [
				'table' => 'shippingmethod',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'title',
					'ordering', 'created', 'modified', 'deleted',
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
		if ($value === null) {
			return '';
		}

		if ($value instanceof DateTime) {
			return $value->format('Y-m-d H:i:s');
		}

		if (is_bool($value)) {
			return $value ? '1' : '0';
		}

		return trim((string)$value);
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
			'tables' => [],
		];

		foreach ($profile as $exportName => $config) {
			$metadata['tables'][$exportName] = [
				'table' => $config['table'],
				'dateField' => $config['dateField'],
				'parentTable' => $config['parentTable'] ?? null,
				'parentKey' => $config['parentKey'] ?? null,
				'parentDateField' => $config['parentDateField'] ?? null,
				'columns' => $config['columns'],
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
		$content[] = '';
		$content[] = 'Rows marked with deleted = 1 are included to preserve audit trail and document history.';
		$content[] = 'Invoice header fields title and subject are excluded because they may contain internal workflow notes.';
		$content[] = 'Billing address fields in invoices and creditnotes represent the document address at the time of document creation.';
		$content[] = 'Shipping address fields are included because they may be relevant for delivery and tax review.';
		$content[] = 'Customer addresses are exported separately from the address table because one contact can have multiple addresses.';
		$content[] = 'Generic modified fields are excluded from fiscal document exports because they may include non-fiscal internal updates.';

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
