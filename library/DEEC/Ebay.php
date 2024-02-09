<?php

class DEEC_eBay {

	protected $basePath;

	protected $connection;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
	}

	public function allShops() {
		$accounts = $this->getAccounts();
		foreach($accounts as $key => $account) {
		}
	}

	public function listItems($id) {
		$account = $this->getAccount($id);
		$userid = $account['userid'];
		require_once($this->basePath.'/library/eBay/ebay.php');
		$eBay = new eBay();
		$eBay->log('eBay cronjob gestartet');
		$productIndex = $eBay->getProductIndex();
		$productLines = $eBay->getProductLines($this->connection, $account);

		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($account['clientid']);
		$filePath = $this->basePath.'/files/ebay/'.$fileUrl;
		$productFileCsv = 'product-'.$userid.'.csv';
		$productFileZip = 'product-'.$userid.'.zip';

		//Get product data
		if(count($productLines)) {
			$eBay->log('Prepare '.count($productLines).' items for upload to account '.$userid);
			//Save data to product.csv
			array_unshift($productLines, $productIndex);
			$productFile = fopen($filePath.'/'.$productFileCsv, 'w');
			foreach($productLines as $fields) {
				fputcsv($productFile, $fields);
			}
			fclose($productFile);
			$eBay->log('CSV file created: '.$productFileCsv);

			//Create product zip archive
			$zip = new ZipArchive;
			$status = $zip->open($filePath.'/'.$productFileZip, ZipArchive::CREATE);
			if($status === TRUE) {
				$zip->addFile($filePath.'/'.$productFileCsv, $productFileCsv);
				$zip->close();
				$eBay->log('Zip file created: '.$productFileZip);
			} else {
				$eBay->log('Culdn\'t create zip file: '.$productFileZip);
			}
		}
	}

	public function uploadFile($id) {
		$account = $this->getAccount($id);
		$userid = $account['userid'];
		require_once($this->basePath.'/library/eBay/ebay.php');
		$eBay = new eBay();
		//Upload file to eBay FTP
		$productFileZip = 'product-'.$userid.'.zip';
		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($account['clientid']);
		$filePath = $this->basePath.'/files/ebay/'.$fileUrl;
		if(file_exists($filePath.'/'.$productFileZip)) {
			$eBay->uploadFTP($account, $filePath, $productFileZip, 'product');
		} else {
			$eBay->log('File not found for upload: '.$filePath.$productFileZip);
		}
	}

	public function getAccount($id) {
		$query = 'SELECT * FROM ebayaccount WHERE id = '.$id;
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_array($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getAccounts() {
		$query = 'SELECT * FROM ebayaccount';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}
}
