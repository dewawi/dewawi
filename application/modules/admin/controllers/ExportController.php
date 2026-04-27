<?php

class Admin_ExportController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
		$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';

		// Fetch a list of exported files
		$exportedFiles = $this->getExportedFiles($fileUrl, $filePath);

		$this->view->exportedFiles = $exportedFiles;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
		$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';

		// Fetch a list of exported files
		$exportedFiles = $this->getExportedFiles($fileUrl, $filePath);

		$this->view->exportedFiles = $exportedFiles;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function exportAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$type = $this->_getParam('type', 'csv');
		$from = $this->_getParam('from', null);
		$to = $this->_getParam('to', null);

		require_once(BASE_PATH.'/library/DEEC/Directory.php');

		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
		$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';

		if (!is_dir($filePath)) {
			mkdir($filePath, 0755, true);
		}

		if ($type === 'gobd') {
			$this->exportGobd($filePath, $from, $to);
		} elseif ($type === 'csv' || $type === 'sql') {
			$this->exportDatabase($filePath, $type);
		} else {
			$this->_flashMessenger->addMessage('Invalid export type.');
			$this->_helper->redirector->gotoSimple('index');
			return;
		}

		$this->_flashMessenger->addMessage('Export created successfully.');
		$this->_helper->redirector->gotoSimple('index');
	}

	protected function exportDatabase(string $filePath, string $type): void
	{
		$tableNames = $this->getAllTableNames($type);
		$files = [];

		foreach ($tableNames as $tableName) {
			$data = $this->fetchDataFromTable($tableName);

			if ($type === 'csv') {
				$filename = 'export_' . $tableName . '.csv';
				$this->writeCsvFile($filePath . $filename, $data);
			} else {
				$filename = 'export_' . $tableName . '.sql';
				$this->writeSqlFile($filePath . $filename, $tableName, $data);
			}

			$files[] = $filename;
		}

		$this->createZipArchiveFromFiles($filePath, $files, 'export-' . date('Ymd-His') . '-' . $type . '.zip');
	}

	protected function exportGobd(string $filePath, ?string $from, ?string $to): void
	{
		$profile = $this->getGobdExportProfile();
		$files = [];

		foreach ($profile as $exportName => $config) {
			$data = $this->fetchGobdData($config, $from, $to);

			$filename = $exportName . '.csv';
			$columns = $this->filterExistingColumns($config['table'], $config['columns']);
			$this->writeCsvFile($filePath . $filename, $data, $columns);
			$files[] = $filename;
		}

		$metadataFile = 'metadata.json';
		$this->writeGobdMetadata($filePath . $metadataFile, $profile, $from, $to);
		$files[] = $metadataFile;

		$readmeFile = 'readme.txt';
		$this->writeGobdReadme($filePath . $readmeFile, $from, $to);
		$files[] = $readmeFile;

		$zipFileName = $this->buildGobdZipFileName($from, $to);
		$this->createZipArchiveFromFiles($filePath, $files, $zipFileName);
	}

	protected function getGobdExportProfile(): array
	{
		return [
			'customers' => [
				'table' => 'contact',
				'dateField' => null,
				'columns' => [
					'id',
					'contactid',
					'clientid',
					'type',
					'name1',
					'name2',
					'department',
					'taxnumber',
					'vatin',
					'taxfree',
					'currency',
					'debitornumber',
					'paymentmethod',
					'paymentterm',
					'cashdiscountdays',
					'cashdiscountpercent',
					'created',
					'modified',
					'deleted',
				],
			],

			'customer_addresses' => [
				'table' => 'address',
				'dateField' => null,
				'columns' => [
					'id',
					'module',
					'controller',
					'parentid',
					'type',
					'name1',
					'name2',
					'department',
					'street',
					'postcode',
					'city',
					'country',
					'phone',
					'ordering',
					'clientid',
					'created',
					'deleted',
				],
			],

			'invoices' => [
				'table' => 'invoice',
				'dateField' => 'invoicedate',
				'columns' => [
					'id',
					'invoiceid',
					'quoteid',
					'salesorderid',
					'deliveryorderid',
					'contactid',
					'clientid',
					'reference',
					'vatin',
					'invoicedate',
					'orderdate',
					'deliverydate',
					'paymentmethod',
					'shippingmethod',
					'billingname1',
					'billingname2',
					'billingstreet',
					'billingpostcode',
					'billingcity',
					'billingcountry',
					'shippingname1',
					'shippingname2',
					'shippingstreet',
					'shippingpostcode',
					'shippingcity',
					'shippingcountry',
					'subtotal',
					'taxes',
					'total',
					'prepayment',
					'currency',
					'taxfree',
					'state',
					'completed',
					'cancelled',
					'filename',
					'created',
					'deleted',
				],
			],

			'invoice_positions' => [
				'table' => 'invoicepos',
				'dateField' => null,
				'parentTable' => 'invoice',
				'parentKey' => 'parentid',
				'parentDateField' => 'invoicedate',
				'columns' => [
					'id',
					'parentid',
					'itemid',
					'masterid',
					'possetid',
					'clientid',
					'sku',
					'title',
					'description',
					'price',
					'taxrate',
					'quantity',
					'total',
					'currency',
					'uom',
					'manufacturerid',
					'manufacturersku',
					'ordering',
					'created',
					'deleted',
				],
			],

			'creditnotes' => [
				'table' => 'creditnote',
				'dateField' => 'creditnotedate',
				'columns' => [
					'id',
					'creditnoteid',
					'quoteid',
					'salesorderid',
					'invoiceid',
					'contactid',
					'clientid',
					'reference',
					'vatin',
					'creditnotedate',
					'invoicedate',
					'orderdate',
					'deliverydate',
					'paymentmethod',
					'shippingmethod',
					'billingname1',
					'billingname2',
					'billingstreet',
					'billingpostcode',
					'billingcity',
					'billingcountry',
					'shippingname1',
					'shippingname2',
					'shippingstreet',
					'shippingpostcode',
					'shippingcity',
					'shippingcountry',
					'subtotal',
					'taxes',
					'total',
					'currency',
					'taxfree',
					'state',
					'completed',
					'cancelled',
					'filename',
					'created',
					'deleted',
				],
			],

			'creditnote_positions' => [
				'table' => 'creditnotepos',
				'dateField' => null,
				'parentTable' => 'creditnote',
				'parentKey' => 'parentid',
				'parentDateField' => 'creditnotedate',
				'columns' => [
					'id',
					'parentid',
					'itemid',
					'masterid',
					'possetid',
					'clientid',
					'sku',
					'title',
					'description',
					'price',
					'taxrate',
					'quantity',
					'total',
					'currency',
					'uom',
					'manufacturerid',
					'manufacturersku',
					'ordering',
					'created',
					'deleted',
				],
			],

			'items' => [
				'table' => 'item',
				'dateField' => null,
				'columns' => [
					'id',
					'clientid',
					'catid',
					'sku',
					'gtin',
					'title',
					'subtitle',
					'type',
					'description',
					'quantity',
					'inventory',
					'cost',
					'price',
					'specialprice',
					'margin',
					'currency',
					'taxid',
					'uomid',
					'manufacturerid',
					'manufacturersku',
					'manufacturergtin',
					'origincountry',
					'created',
					'modified',
					'deleted',
				],
			],

			'taxrates' => [
				'table' => 'taxrate',
				'dateField' => null,
				'columns' => [
					'id',
					'clientid',
					'name',
					'rate',
					'ordering',
					'created',
					'modified',
					'deleted',
				],
			],

			'payment_methods' => [
				'table' => 'paymentmethod',
				'dateField' => null,
				'columns' => [
					'id',
					'clientid',
					'title',
					'ordering',
					'created',
					'modified',
					'deleted',
				],
			],

			'shipping_methods' => [
				'table' => 'shippingmethod',
				'dateField' => null,
				'columns' => [
					'id',
					'clientid',
					'title',
					'ordering',
					'created',
					'modified',
					'deleted',
				],
			],
		];
	}

	protected function buildGobdZipFileName(?string $from, ?string $to): string
	{
		$fromPart = $this->formatDateForFilename($from);
		$toPart = $this->formatDateForFilename($to);

		return 'gobd-export-' . $fromPart . '-' . $toPart . '-' . date('Ymd-His') . '.zip';
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

	protected function fetchGobdData(array $config, ?string $from, ?string $to): array
	{
		$db = Zend_Db_Table::getDefaultAdapter();

		$table = $config['table'];
		$dateField = $config['dateField'] ?? null;
		$columns = $this->filterExistingColumns($table, $config['columns']);

		if (empty($columns)) {
			return [];
		}

		$select = $db->select();

		if (!empty($config['parentTable']) && $from && $to) {
			$parentTable = $config['parentTable'];
			$parentKey = $config['parentKey'];
			$parentDateField = $config['parentDateField'];

			$columnMap = [];

			foreach ($columns as $column) {
				$columnMap[$column] = 't.' . $column;
			}

			$select
				->from(['t' => $table], $columnMap)
				->joinInner(
					['p' => $parentTable],
					'p.id = t.' . $parentKey,
					[]
				)
				->where('t.clientid = ?', $this->_user['clientid'])
				->where('p.clientid = ?', $this->_user['clientid'])
				->where('p.' . $parentDateField . ' >= ?', $from)
				->where('p.' . $parentDateField . ' <= ?', $to);

			if (in_array('id', $columns, true)) {
				$select->order('t.id ASC');
			}
		} else {
			$select
				->from($table, $columns)
				->where('clientid = ?', $this->_user['clientid']);

			if ($dateField && $from && $to) {
				$select->where($dateField . ' >= ?', $from);
				$select->where($dateField . ' <= ?', $to);
			}

			if (in_array('id', $columns, true)) {
				$select->order('id ASC');
			}
		}

		return $db->fetchAll($select);
	}

	protected function writeGobdMetadata(string $filename, array $profile, ?string $from, ?string $to): void
	{
		$metadata = [
			'system' => 'Dewawi',
			'exportType' => 'GoBD',
			'createdAt' => date('c'),
			'clientId' => (int)$this->_user['clientid'],
			'userId' => isset($this->_user['id']) ? (int)$this->_user['id'] : null,
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

	protected function writeGobdReadme(string $filename, ?string $from, ?string $to): void
	{
		$content = [];
		$content[] = 'Dewawi GoBD Export';
		$content[] = 'Created: ' . date('Y-m-d H:i:s');
		$content[] = 'Client ID: ' . $this->_user['clientid'];
		$content[] = 'From: ' . ($from ?: '-');
		$content[] = 'To: ' . ($to ?: '-');
		$content[] = '';
		$content[] = 'Format: CSV';
		$content[] = 'Encoding: UTF-8';
		$content[] = 'Delimiter: semicolon';
		$content[] = 'Decimal format: 1234.56';
		$content[] = '';
		$content[] = 'This export contains tax-relevant business data from Dewawi.';
		$content[] = 'Rows marked with deleted = 1 are included to preserve audit trail and document history.';
		$content[] = 'Billing address fields in invoices and creditnotes represent the document address at the time of document creation.';
		$content[] = 'Shipping address fields are included because they may be relevant for delivery and tax review.';
		$content[] = 'Customer addresses are exported separately from the address table because one contact can have multiple addresses.';

		file_put_contents($filename, implode("\n", $content));
	}

	protected function writeSqlFile(string $filename, string $tableName, array $data): void
	{
		$content = $this->generateSql($tableName, $data);
		file_put_contents($filename, $content);
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			require_once(BASE_PATH.'/library/DEEC/Directory.php');
			$Directory = new DEEC_Directory();
			$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
			$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';

			// Fetch a list of exported files
			$exportedFiles = $this->getExportedFiles($fileUrl, $filePath);

			// Check if the file exists
			if (file_exists($filePath.$exportedFiles[$id]['name'])) {
				// Attempt to delete the file
				if (unlink($filePath.$exportedFiles[$id]['name'])) {
					// If deletion is successful, add a success message
					$this->_flashMessenger->addMessage('File successfully deleted.');
				} else {
					// If deletion fails, add an error message
					$this->_flashMessenger->addMessage('Failed to delete the file.');
				}
			} else {
				// If the file does not exist, add an error message
				$this->_flashMessenger->addMessage('File not found.');
			}
		}
	}

	// Fetch all table names from the database
	protected function getAllTableNames($type)
	{
		$db = Zend_Db_Table::getDefaultAdapter();
		$tables = $db->listTables();
		$tablesToExport = ['address', 'bankaccount', 'campaign', 'category', 'comment', 'contact', 'contactperson', 'country', 'creditnote', 'creditnotepos', 'creditnoteposset', 'currency', 'deliveryorder', 'deliveryorderpos', 'deliveryorderposset', 'deliverytime', 'email', 'emailmessage', 'emailtemplate', 'footer', 'increment', 'internet', 'inventory', 'invoice', 'invoicepos', 'invoiceposset', 'item', 'itematr', 'itematrset', 'itemlist', 'itemopt', 'itemoptset', 'manufacturer', 'media', 'menu', 'menuitem', 'page', 'paymentmethod', 'permission', 'phone', 'pricerule', 'pricerulepos', 'process', 'processpos', 'processposset', 'purchaseorder', 'purchaseorderpos', 'purchaseorderposset', 'quote', 'quotepos', 'quoteposset', 'quoterequest', 'quoterequestpos', 'quoterequestposset', 'reminder', 'reminderpos', 'reminderposset', 'salesorder', 'salesorderpos', 'salesorderposset', 'shippingmethod', 'shop', 'shoporder', 'shoporderpos', 'slide', 'slug', 'state', 'tag', 'tagentity', 'task', 'taskpos', 'taskposset', 'taxrate', 'template', 'textblock', 'uom', 'usertracking', 'warehouse'];
		$clientidTables = [];

		// Check each table for the presence of the 'clientid' column
		foreach ($tables as $table) {
			$columns = $db->describeTable($table);
			// Check if the 'clientid' column exists in the table
			if (array_key_exists('clientid', $columns)) {
				if (in_array($table, $tablesToExport)) {
					$clientidTables[] = $table;
				}
			}
		}

		return $clientidTables;
	}

	protected function fetchDataFromTable($tableName)
	{
		$db = Zend_Db_Table::getDefaultAdapter();

		$select = $db->select()
			->from($tableName)
			->where('clientid = ?', $this->_user['clientid']);

		$columns = $db->describeTable($tableName);

		if (array_key_exists('id', $columns)) {
			$select->order('id ASC');
		}

		return $db->fetchAll($select);
	}

	protected function filterExistingColumns(string $table, array $columns): array
	{
		$db = Zend_Db_Table::getDefaultAdapter();
		$description = $db->describeTable($table);

		$existing = [];

		foreach ($columns as $column) {
			if (array_key_exists($column, $description)) {
				$existing[] = $column;
			}
		}

		return $existing;
	}

	protected function writeCsvFile(string $filename, array $data, array $columns = []): void
	{
		$handle = fopen($filename, 'w');

		if (!$handle) {
			throw new RuntimeException('Could not create CSV file: ' . $filename);
		}

		if (!empty($columns)) {
			fputcsv($handle, $columns, ';');
		} elseif (!empty($data)) {
			fputcsv($handle, array_keys($data[0]), ';');
		}

		foreach ($data as $row) {
			$cleanRow = [];

			if (!empty($columns)) {
				foreach ($columns as $column) {
					$cleanRow[] = $this->normalizeExportValue($row[$column] ?? null);
				}
			} else {
				foreach ($row as $value) {
					$cleanRow[] = $this->normalizeExportValue($value);
				}
			}

			fputcsv($handle, $cleanRow, ';');
		}

		fclose($handle);
	}

	protected function createZipArchiveFromFiles(string $filePath, array $files, string $zipFileName): void
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

	protected function normalizeExportValue($value)
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

	protected function generateCsv($data)
	{
		// Initialize CSV content with an empty string
		$csv = '';

		// Check if data is not empty
		if (!empty($data)) {
			// Get column names from the first row of data
			$columnNames = array_keys($data[0]);

			// Create header row by imploding column names with comma
			$headerRow = implode(',', $columnNames) . "\n";

			// Add header row to CSV content
			$csv .= $headerRow;

			// Iterate over data rows and generate CSV content
			foreach ($data as $row) {
				// Process each row of data
				$processedRow = [];
				foreach ($row as $value) {
					// Handle TEXT data type (if needed)
					if (is_string($value)) {
						// Escape double quotes and enclose in quotes
						$value = '"' . str_replace('"', '""', $value) . '"';
					}
					$processedRow[] = $value;
				}
				// Add processed row to CSV content
				$csv .= implode(',', $processedRow) . "\n";
			}
		}

		return $csv;
	}

	// Generate SQL content
	protected function generateSql($tableName, $data)
	{
		if (empty($data)) {
			return '';
		}

		$db = Zend_Db_Table::getDefaultAdapter();
		$sql = '';

		foreach ($data as $row) {
			$columns = [];
			$values = [];

			foreach ($row as $column => $value) {
				$columns[] = $db->quoteIdentifier($column);

				if ($value === null) {
					$values[] = 'NULL';
				} else {
					$values[] = $db->quote((string)$value);
				}
			}

			$sql .= 'INSERT INTO ' . $db->quoteIdentifier($tableName)
				. ' (' . implode(', ', $columns) . ') VALUES ('
				. implode(', ', $values) . ");\n";
		}

		return $sql;
	}

	protected function createZipArchive($filePath, $tableNames, $type)
	{
		$zip = new ZipArchive();
		$zipFileName = 'export-'.time().'-'.$type.'.zip';

		if ($zip->open($filePath.$zipFileName, ZipArchive::CREATE) === TRUE) {
			foreach ($tableNames as $tableName) {
				$fileName = "export_$tableName.$type";
				$zip->addFile($filePath.$fileName, $fileName);
			}
			$zip->close();
			// Clean up: Delete individual files
			foreach ($tableNames as $tableName) {
				$fileName = "export_$tableName.$type";
				unlink($filePath.$fileName);
			}
		}
	}

	protected function getExportedFiles($fileUrl, $exportDir)
	{
		// Initialize an empty array to store file information
		$filesInfo = [];

		// Check if the export directory exists
		if (file_exists($exportDir) && is_dir($exportDir)) {
			// Get a list of all files in the export directory
			$files = scandir($exportDir);
			// Remove '.' and '..' entries
			$files = array_diff($files, ['.', '..']);
			
			// Iterate through each file to get its size
			foreach ($files as $file) {
				// Get the full path of the file
				$filePath = $exportDir . $file;

				// Get the file size in bytes
				$fileSize = filesize($filePath);

				// Format the file size
				if ($fileSize >= 1024 * 1024) {
					// If the file size is greater than or equal to 1 MB, display it in MB
					$formattedSize = round($fileSize / (1024 * 1024), 2) . ' MB';
				} else {
					// Otherwise, display it in KB
					$formattedSize = round($fileSize / 1024, 2) . ' KB';
				}

				// Get the creation datetime
				$createdDatetime = date('Y-m-d H:i:s', filectime($filePath));

				// Add file name, path, and size to the filesInfo array
				$filesInfo[] = [
					'name' => $file,
					'url' => $fileUrl,
					'path' => $filePath,
					'size' => $formattedSize,
					'created_datetime' => $createdDatetime
				];
			}
		}

		// Sort filesInfo array by creation datetime (descending)
		usort($filesInfo, function($a, $b) {
			return strtotime($b['created_datetime']) - strtotime($a['created_datetime']);
		});

		// Return the array containing file names, paths, and sizes
		return $filesInfo;
	}
}
