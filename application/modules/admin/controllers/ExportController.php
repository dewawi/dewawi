<?php

class Admin_ExportController extends DEEC_Controller_Action
{
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

		$type = (string)$this->_getParam('type', 'csv');
		$target = (string)$this->_getParam('target', '');
		$from = $this->_getParam('from', null);
		$to = $this->_getParam('to', null);

		require_once(BASE_PATH.'/library/DEEC/Directory.php');

		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
		$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';

		if (!is_dir($filePath)) {
			mkdir($filePath, 0755, true);
		}

		if ($type === 'list') {
			$this->exportList($filePath, $target);
		} elseif ($type === 'gobd') {
			require_once(BASE_PATH . '/library/DEEC/Export/Gobd.php');

			$db = Zend_Db_Table::getDefaultAdapter();
			$userId = isset($this->_user['id']) ? (int)$this->_user['id'] : null;

			$export = new DEEC_Export_Gobd(
				$db,
				(int)$this->_user['clientid'],
				$userId
			);

			$export->export($filePath, $from, $to);
		} elseif ($type === 'csv' || $type === 'sql') {
			$this->exportDatabase($filePath, $type);
		} else {
			$this->_flashMessenger->addMessage('Invalid export type.');
			return $this->_helper->redirector->gotoSimple('index');
		}

		$this->_flashMessenger->addMessage('Export created successfully.');
		return $this->_helper->redirector->gotoSimple('index');
	}

	protected function exportList(string $filePath, string $target): void
	{
		$config = $this->getListExportConfig($target);

		if (!$config) {
			throw new RuntimeException('Invalid list export target.');
		}

		$toolbar = new $config['toolbar']();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$params['_export'] = true;

		$query = new DEEC_List_Query();

		list($items, $records) = $query->fetch(
			$params,
			$options,
			call_user_func([$config['entity'], 'listConfig'])
		);

		$service = new $config['service']();

		$service->export($filePath, $items, [
			'options' => $options,
			'user' => $this->_user,
		]);
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

	protected function writeSqlFile(string $filename, string $tableName, array $data): void
	{
		$content = $this->generateSql($tableName, $data);
		file_put_contents($filename, $content);
	}

	protected function getListExportConfig(string $target): ?array
	{
		$map = [
			'contacts' => [
				'label' => 'CONTACTS',
				'toolbar' => 'Contacts_Form_Toolbar',
				'entity' => 'Contacts_Model_Entity_Contact',
				'service' => 'Contacts_Service_ContactExportService',
			],
			'items' => [
				'label' => 'ITEMS',
				'toolbar' => 'Items_Form_Toolbar',
				'entity' => 'Items_Model_Entity_Item',
				'service' => 'Items_Service_ItemExportService',
			],
		];

		return $map[$target] ?? null;
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
