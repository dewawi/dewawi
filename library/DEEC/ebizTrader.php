<?php

class DEEC_ebizTrader {

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
		require_once($this->basePath.'/library/ebizTrader/ebiztrader.php');
		$ebizTrader = new ebizTrader();
		$ebizTrader->log('ebizTrader cronjob gestartet');
		$productIndex = $ebizTrader->getFeedIndex();
		$productLines = $ebizTrader->getProductLines($this->connection, $account);

		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($account['clientid']);
		$filePath = $this->basePath.'/files/ebiztrader/'.$fileUrl;
		$productFileCsv = 'product-'.$userid.'.csv';

		//Get product data
		if(count($productLines)) {
			$ebizTrader->log('Prepare '.count($productLines).' items for upload to account '.$userid);
			//Save data to product.csv
			array_unshift($productLines, $productIndex);
			$productFile = fopen($filePath.$productFileCsv, 'w');
			foreach($productLines as $fields) {
				fputcsv($productFile, $fields);
			}
			fclose($productFile);
			$ebizTrader->log('CSV file created: '.$productFileCsv);
		}
	}

	public function getAccount($id) {
		$query = 'SELECT * FROM ebiztraderaccount WHERE id = '.$id;
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_array($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getAccounts($connection) {
		$query = 'SELECT * FROM ebiztraderaccount';
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}
}
