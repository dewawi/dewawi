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

		// Fetch all table names from the database
		$tableNames = $this->getAllTableNames($type);

		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
		$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';

		// Export each table into separate CSV files
		foreach ($tableNames as $tableName) {
			$data = $this->fetchDataFromTable($tableName);
			if($type == 'csv') {
				$csvContent = $this->generateCsv($data);

				// Write CSV content to a file
				$filename = "export_$tableName.csv";
				file_put_contents($filePath.$filename, $csvContent);
			} elseif($type == 'sql') {
				$sqlContent = $this->generateSql($tableName, $data);

				// Write SQL content to a file
				$sqlFilename = "export_$tableName.sql";
				file_put_contents($filePath.$sqlFilename, $sqlContent);
			}
		}

		// Create a zip archive containing all files
		$this->createZipArchive($filePath, $tableNames, $type);

		// Respond with a success message or perform any further actions
		$this->view->message = 'All tables exported successfully.';

		$this->_helper->redirector->gotoSimple('index');
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

	// Fetch data from a specific table
	protected function fetchDataFromTable($tableName)
	{
		$db = Zend_Db_Table::getDefaultAdapter();
		$select = $db->select()->from($tableName)->where('clientid = ?', $this->_user['clientid']);
		return $db->fetchAll($select);
	}

	// Generate CSV content
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
		// Initialize SQL content with an empty string
		$sql = '';

		// Check if data is not empty
		if (!empty($data)) {
			// Generate INSERT INTO statements for each row
			foreach ($data as $row) {
				$columns = array_keys($row);
				$values = array_map(function($value) {
					// Escape single quotes in values
					return "'" . str_replace("'", "''", $value) . "'";
				}, $row);
				$sql .= "INSERT INTO $tableName (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
			}
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
