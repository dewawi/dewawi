<?php

class eBay {

	public function getProductIndex() {
		//Set product feed index
		$productIndex = array();
		$productIndex[0] = 'SKU';
		$productIndex[1] = 'Localized For';
		$productIndex[2] = 'Title';
		$productIndex[3] = 'Product Description';
		$productIndex[4] = 'EAN';
		$productIndex[5] = 'Condition';
		$productIndex[6] = 'Measurement System';
		$productIndex[7] = 'Length';
		$productIndex[8] = 'Width';
		$productIndex[9] = 'Height';
		$productIndex[10] = 'Weight Major';
		$productIndex[11] = 'Weight Minor';
		$productIndex[12] = 'Package Type';
		$productIndex[13] = 'Picture URL 1';
		$productIndex[14] = 'Picture URL 2';
		$productIndex[15] = 'Picture URL 3';
		$productIndex[16] = 'Picture URL 4';
		$productIndex[17] = 'Picture URL 5';
		$productIndex[18] = 'Picture URL 6';
		$productIndex[19] = 'Picture URL 7';
		$productIndex[20] = 'Picture URL 8';
		$productIndex[21] = 'Picture URL 9';
		$productIndex[22] = 'Picture URL 10';
		$productIndex[23] = 'Picture URL 11';
		$productIndex[24] = 'Picture URL 12';
		//$productIndex[25] = 'C:Herstellernummer';
		//$productIndex[26] = 'C:Marke';

		//Set distribution feed index
		$productIndex[30] = 'Category';
		$productIndex[31] = 'Store Category Name 1';
		$productIndex[32] = 'Store Category Name 2';
		$productIndex[33] = 'Shipping Policy';
		$productIndex[34] = 'Payment Policy';
		$productIndex[35] = 'Return Policy';
		$productIndex[36] = 'List Price';
		$productIndex[37] = 'Apply Tax';
		$productIndex[38] = 'VATPercent';

		//Set inventory feed index
		$productIndex[40] = 'Total Ship To Home Quantity';

		//Set attributes
		$productIndex[50] = 'Attribute Name 1';
		$productIndex[51] = 'Attribute Value 1';
		$productIndex[52] = 'Attribute Name 2';
		$productIndex[53] = 'Attribute Value 2';

		return $productIndex;
	}

	public function getProductLines($connection, $account) {
		$accountid = $account['id'];
		$clientid = $account['clientid'];
		$items = $this->getItems($connection, $accountid, $clientid);
		$productLines = array();
		if($items) {
			require_once(BASE_PATH.'/library/DEEC/PriceRule.php');
			$dir1 = substr($clientid, 0, 1);
			if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
			else $dir2 = '0';
			$url = $this->url();
			$url = substr($url, 0, strpos($url, 'ebay/index'));
			$imageUrl = $url.'media/items/'.$dir1.'/'.$dir2.'/'.$clientid;
			$imagePath = BASE_PATH.'/media/items/'.$dir1.'/'.$dir2.'/'.$clientid;
			$template = $this->getTemplate($connection, $accountid, $clientid);
			$taxRates = $this->getTaxRates($connection, $clientid);
			$manufacturers = $this->getManufacturers($connection, $clientid);
			$productCount = 0;
			foreach($items as $item) {
				$images = $this->getItemimages($connection, $item['itemid']);
				$attributes = $this->getAttributes($connection, $item['itemid']);
				if($images && count($images) && file_exists($imagePath.$images[0]['url'])) {
					list($width, $height, $type, $attr) = getimagesize($imagePath.$images[0]['url']);
					if(($width >= 500) || ($height >= 500)) {
						//Define fields
						$locale = $account['locale'];
						if($item['shippingpolicy']) {
							$shippingPolicy = $item['shippingpolicy'];
						} else {
							$shippingPolicy = $account['shippingpolicy'];
						}
						$paymentPolicy = $account['paymentpolicy'];
						$returnPolicy = $account['returnpolicy'];
						$measurementSystem = $account['measurementsystem'];

						//Define fields
						$sku = $item['sku'];
						if($item['gtin']) {
							$ean = $item['gtin'];
						} else {
							$ean = 'nichtzutreffend';
						}
						$title = $item['title'];
						$eBayTitle = $item['ebaytitle'] ? $item['ebaytitle'] : $item['title'];
						$eBayCategory1 = $item['category1'];
						$eBayCategory2 = $item['category2'];
						$eBayStoreCategory1 = $item['ebaystorecategory1'];
						$eBayStoreCategory2 = $item['ebaystorecategory2'];
						$description = $item['description'];
						$ebayDescription = $item['ebaydescription'];
						$shopDescription = $item['shopdescription'];
						$shopDescriptionShort = $item['shopdescriptionshort'];
						$shopDescriptionMini = $item['shopdescriptionmini'];
						$length = $item['length'];
						$width = $item['width'];
						$height = $item['height'];
						$packaginglength = $item['packlength'];
						$packagingwidth = $item['packwidth'];
						$packagingheight = $item['packheight'];
						$weight = $item['weight'];
						$packagingweight = $item['packweight'];
						$price = $item['price'];
						$condition = 'NEW';
						$applyTax = 1;
						if($item['taxid'] && isset($taxRates[$item['taxid']])) {
							$taxRate = $taxRates[$item['taxid']];
						}
						if($item['manufacturerid'] && isset($manufacturers[$item['manufacturerid']])) {
							$brand = $manufacturers[$item['manufacturerid']];
						}
						//if($item['inventory']) {
						//	$quantity = $item['quantity'];
						//} else {
							$quantity = 100;
						//}

						//Check if ean is 13 digits long
						//if(preg_match("/^[0-9]{13}$/", $ean)) {
							if($title && $price && ($price > 0)) {
								//Set options for price rules
								$options = array();
								if($item['type']) $options['itemtype'] = $item['type'];
								if($item['manufacturerid']) $options['itemmanufacturer'] = $item['manufacturerid'];

								//Use price rules
								$PriceRule = new DEEC_PriceRule();
								$price = $PriceRule->usePricerules($item, $connection, $options, $clientid);



								$listingPrice = $price * 1.05;
								if($taxRate) $listingPrice = $listingPrice + ($listingPrice * $taxRate / 100);
								$listingPrice = round($listingPrice, 2);

								//Max title length 80 characters
								if(strlen($eBayTitle) > 80) $eBayTitle = substr($eBayTitle, 0, 80);

								//Create description html
								if(strpos($description, '<p>') === false) {
									$description = '<p>'.$description.'</p>';
									$description = str_replace("\n", '<br>', $description);
								}

								//Create attributes table
								$attributesHtml = '';
								if($attributes && count($attributes)) {
									$attributesHtml = '<h2>Produkteigenschaften</h2>';
									$attributesHtml .= '<table id="attributes">';
									foreach($attributes as $attribute) {
										if($attribute['title'] != 'Zeichnung') {
											$attributesHtml .= '<tr>';
											$attributesHtml .= '<td>'.$attribute['title'].'</td>';
											$attributesHtml .= '<td>'.$attribute['description'].'</td>';
											$attributesHtml .= '</tr>';
										}
									}
									$attributesHtml .= '</table>';
								}

								//Create dimensions table
								$dimensionsHtml = '';
								if($length || $weight) {
									if($width && ($weight || $packagingweight)) $dimensionsHtml = '<h2>Abmessungen & Gewicht</h2>';
									else $dimensionsHtml = '<h2>Abmessungen</h2>';
									$dimensionsHtml .= '<table id="dimensions">';
									if($length) {
										$dimensionsHtml .= '<tr>';
										$dimensionsHtml .= '<td>Tiefe</td>';
										$dimensionsHtml .= '<td>'.$length.'</td>';
										$dimensionsHtml .= '</tr>';
									}
									if($width) {
										$dimensionsHtml .= '<tr>';
										$dimensionsHtml .= '<td>Breite</td>';
										$dimensionsHtml .= '<td>'.$width.'</td>';
										$dimensionsHtml .= '</tr>';
									}
									if($height) {
										$dimensionsHtml .= '<tr>';
										$dimensionsHtml .= '<td>Höhe</td>';
										$dimensionsHtml .= '<td>'.$height.'</td>';
										$dimensionsHtml .= '</tr>';
									}
									if($packaginglength) {
										$dimensionsHtml .= '<tr>';
										$dimensionsHtml .= '<td>Tiefe inkl. Verpackung</td>';
										$dimensionsHtml .= '<td>'.$packaginglength.'</td>';
										$dimensionsHtml .= '</tr>';
									}
									if($packagingwidth) {
										$dimensionsHtml .= '<tr>';
										$dimensionsHtml .= '<td>Breite inkl. Verpackung</td>';
										$dimensionsHtml .= '<td>'.$packagingwidth.'</td>';
										$dimensionsHtml .= '</tr>';
									}
									if($packagingheight) {
										$dimensionsHtml .= '<tr>';
										$dimensionsHtml .= '<td>Höhe inkl. Verpackung</td>';
										$dimensionsHtml .= '<td>'.$packagingheight.'</td>';
										$dimensionsHtml .= '</tr>';
									}
									if($weight) {
										$dimensionsHtml .= '<tr>';
										$dimensionsHtml .= '<td>Gewicht</td>';
										$dimensionsHtml .= '<td>'.$weight.'</td>';
										$dimensionsHtml .= '</tr>';
									}
									if($packagingweight) {
										$dimensionsHtml .= '<tr>';
										$dimensionsHtml .= '<td>Gewicht inkl. Verpackung</td>';
										$dimensionsHtml .= '<td>'.$packagingweight.'</td>';
										$dimensionsHtml .= '</tr>';
									}
									$dimensionsHtml .= '</table>';
								}
								$additionalData = $attributesHtml.$dimensionsHtml;

								//Replace data in template
								$templateItem = str_replace('%LOGO%', $template['logo'], $template['html']);
								$templateItem = str_replace('%TITLE%', $title, $templateItem);
								$templateItem = str_replace('%SKU%', $sku, $templateItem);
								if(isset($images[0]['url'])) {
									$templateItem = str_replace('%IMAGE1%', '<img src="'.$imageUrl.$images[0]['url'].'"/>', $templateItem);
								} else {
									$templateItem = str_replace('%IMAGE1%', '', $templateItem);
								}
								if(isset($images[1]['url'])) {
									$templateItem = str_replace('%IMAGE2%', '<img src="'.$imageUrl.$images[1]['url'].'"/>', $templateItem);
								} else {
									$templateItem = str_replace('%IMAGE2%', '', $templateItem);
								}
								$templateItem = str_replace('%DESCRIPTION%', $description, $templateItem);
								$templateItem = str_replace('%EBAYDESCRIPTION%', $ebayDescription, $templateItem);
								$templateItem = str_replace('%SHOPDESCRIPTION%', $shopDescription, $templateItem);
								$templateItem = str_replace('%SHOPDESCRIPTIONSHORT%', $shopDescriptionShort, $templateItem);
								$templateItem = str_replace('%SHOPDESCRIPTIONMINI%', $shopDescriptionMini, $templateItem);
								$templateItem = str_replace('%ADDITIONALDATA%', $additionalData, $templateItem);
								$templateItem = str_replace('-tt-', '', $templateItem);
								$templateItem = str_replace("\n", '', $templateItem);
								//$description = trim(preg_replace('/>\s+</', '><', $templateItem));
								$description = trim(preg_replace('/\s+/', ' ', $templateItem));

								//Define lines for product feed
								$productLines[$productCount][0] = $sku;
								$productLines[$productCount][1] = $locale;
								$productLines[$productCount][2] = $eBayTitle;
								$productLines[$productCount][3] = $description;
								$productLines[$productCount][4] = $ean;
								$productLines[$productCount][5] = $condition;
								$productLines[$productCount][6] = $measurementSystem;
								$productLines[$productCount][7] = ''; //Length
								$productLines[$productCount][8] = ''; //Width
								$productLines[$productCount][9] = ''; //Height
								$productLines[$productCount][10] = ''; //Weight Major
								$productLines[$productCount][11] = ''; //Weight Minor
								$productLines[$productCount][12] = ''; //Package Type

								//Set Picture URLs 1 to 12
								$productLines[$productCount][13] = $imageUrl.$images[0]['url']; //Picture URL 1
								$productLines[$productCount][14] = ''; //Picture URL 2
								$productLines[$productCount][15] = ''; //Picture URL 3
								$productLines[$productCount][16] = ''; //Picture URL 4
								$productLines[$productCount][17] = ''; //Picture URL 5
								$productLines[$productCount][18] = ''; //Picture URL 6
								$productLines[$productCount][19] = ''; //Picture URL 7
								$productLines[$productCount][20] = ''; //Picture URL 8
								$productLines[$productCount][21] = ''; //Picture URL 9
								$productLines[$productCount][22] = ''; //Picture URL 10
								$productLines[$productCount][23] = ''; //Picture URL 11
								$productLines[$productCount][24] = ''; //Picture URL 12
								$i = 13;
								foreach($images as $image) {
									if($i < 25) {
										if($image['url'] && file_exists($imagePath.$image['url'])) {
											list($width, $height, $type, $attr) = getimagesize($imagePath.$image['url']);
											if(($width >= 500) || ($height >= 500)) {
												$productLines[$productCount][$i] = $imageUrl.$image['url'];
												++$i;
											}
										}
									}
								}

								//Define lines for distribution feed
								$productLines[$productCount][30] = $eBayCategory1;
								$productLines[$productCount][31] = $eBayStoreCategory1;
								$productLines[$productCount][32] = $eBayStoreCategory2;
								$productLines[$productCount][33] = $shippingPolicy;
								$productLines[$productCount][34] = $paymentPolicy;
								$productLines[$productCount][35] = $returnPolicy;
								$productLines[$productCount][36] = $listingPrice;
								$productLines[$productCount][37] = $applyTax;
								$productLines[$productCount][38] = $taxRate;

								//Define lines for inventory feed
								$productLines[$productCount][40] = $quantity;

								//Set attributes
								if(isset($brand)) {
									$productLines[$productCount][50] = 'Marke';
									$productLines[$productCount][51] = $brand;
									$productLines[$productCount][52] = 'Herstellernummer';
									$productLines[$productCount][53] = $sku;
								}

								$this->updateItem($connection, $item['id'], 1);
								++$productCount;
							} else {
								$this->updateItem($connection, $item['id'], 0);
								$this->log('Error: price or title not set for: '.$item['sku']);
							}
						//} else {
						//	$this->updateItem($connection, $item['id'], 0);
						//	$this->log('Error: no valid gtin/ean found for: '.$item['sku']);
						//}
					} else {
						$this->updateItem($connection, $item['id'], 0);
						$this->log('Error: longest side of your eBay images must be a minimum of 500 pixels '.$item['sku'].': '.$width.'x'.$height.' px');
					}
				} else {
					$this->updateItem($connection, $item['id'], 0);
					$this->log('Error: there is no image for: '.$item['sku']);
				}
			}
		} else {
			$this->log('There are no items to list for: '.$account['id']);
		}

		return $productLines;
	}

	public function uploadFTP($account, $filePath, $filename, $type)
	{
		$host = $account['ftphost'];
		$user = $account['ftpusername'];
		$pass = $account['ftptoken'];
		$port = $account['ftpport'];
		$remoteDir = '/store/'.$type.'/';

		// open connection
		if($connection = ssh2_connect($host, $port)) {
			$this->log('SSH connection to host initiated');
		} else {
			$this->log('Cannot initiate connection to host');
		    die('Cannot initiate connection to host');
		}

		// send access parameters
		if(ssh2_auth_password($connection, $user, $pass)) {
			$this->log('SSH login successful');
		} else {
			$this->log('Cannot login to SSH');
		    die('Cannot login to SSH');
		}

		if($sftp = ssh2_sftp($connection)) {
			$this->log('Sftp connection successful');
		} else {
			$this->log('Failed to create a sftp connection');
		    die('Failed to create a sftp connection');
		}

		if($stream = fopen("ssh2.sftp://$sftp$remoteDir$filename", 'w')) {
			$this->log('Open sftp file connection: ssh2.sftp://'.$sftp.$remoteDir.$filename);
		} else {
			$this->log('Open sftp file connection failed');
		    die('Open sftp file connection failed');
		}

		if($file = file_get_contents($filePath.'/'.$filename)) {
			$this->log('Get the file contents '.$filename);
		} else {
			$this->log('Couldn\'t get the file contents');
		    die('Couldn\'t get the file contents');
		}

		if(fwrite($stream, $file)) {
			$this->log('Write the contents to the server successful');
		} else {
			$this->log('Couldn\'t write the contents to the server');
		    die('Couldn\'t write the contents to the server');
		}

		fclose($stream);

		return true;
	}

	public function getItems($connection, $accountid, $clientid) {
		$query = '
				SELECT
					e.*, i.*
				FROM
					ebaylisting e
				LEFT JOIN
					item i
				ON
					i.id = e.itemid
				WHERE
					e.accountid = '.$accountid.'
					AND e.clientid = '.$clientid.'
					AND e.deleted = 0;';
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getItem($connection, $itemid, $clientid) {
		$query = '
				SELECT
					* FROM item
				WHERE
					id = '.$itemid.'
					AND clientid = '.$clientid.'
					AND deleted = 0;';
		//var_dump($query);
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_array($result);
		} else {
		    return false;
		}
	}

	function getAttributes($connection, $itemid) {
		$query = '
				SELECT
					* FROM itematr
				WHERE
					itemid = "'.$itemid.'"
					AND deleted = 0
				ORDER
					BY ordering;';
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getItemimages($connection, $itemid) {
		$query = '
				SELECT
					* FROM itemimage
				WHERE
					itemid = "'.$itemid.'"
					AND deleted = 0
				ORDER
					BY ordering;';
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getTemplate($connection, $accountid, $clientid) {
		$query = '
				SELECT
					* FROM ebaytemplate
				WHERE
					accountid = '.$accountid.'
					AND clientid = '.$clientid.'
					AND deleted = 0;';
		//var_dump($query);
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_array($result);
		} else {
		    return false;
		}
	}

	public function getTaxRates($connection, $clientid) {
		$where = 'clientid = '.$clientid;
		$where .= ' AND deleted = 0';
		$query = '
				SELECT
					* FROM taxrate
				WHERE
					'.$where.'
				ORDER
					BY ordering;';
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
			$taxrates = array();
		    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
			foreach($data as $taxrate) {
				$taxrates[$taxrate['id']] = $taxrate['rate'];
			}
		    return $taxrates;
		} else {
		    return false;
		}
	}

	public function getManufacturers($connection, $clientid) {
		$where = 'clientid = '.$clientid;
		$where .= ' AND deleted = 0';
		$query = '
				SELECT
					* FROM manufacturer
				WHERE
					'.$where.'
				ORDER
					BY ordering;';
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
			$manufacturers = array();
		    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
			foreach($data as $manufacturer) {
				$manufacturers[$manufacturer['id']] = $manufacturer['name'];
			}
		    return $manufacturers;
		} else {
		    return false;
		}
	}

	public function updateItem($connection, $itemid, $listedby) {
		$modified = date("Y-m-d H:i:s");
		$query = '
				UPDATE
					ebaylisting
				SET
					modified = "'.$modified.'",
					listedby = '.$listedby.'
				WHERE
					itemid = '.$itemid.';';
		$result = mysqli_query($connection, $query);
		if($result) {
			return true;
		} else {
			return false;
		}
	}

	public function url(){
		return sprintf(
			"%s://%s%s",
			isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
			$_SERVER['SERVER_NAME'],
			$_SERVER['REQUEST_URI']
		);
	}
	public function log($message) {
		$file = BASE_PATH.'/logs/ebay.log';
	    error_log(date("Y-m-d H:i:s").' '.$message." \n", 3, $file);
	}
}
