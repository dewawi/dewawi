<?php

require_once '/usr/share/php/phpseclib/autoload.php';
require_once '/usr/share/php/phpseclib/Net/SFTP.php';

use phpseclib\Net\SFTP;

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

		//Set images index
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
		$productIndex[25] = 'Picture URL 13';
		$productIndex[26] = 'Picture URL 14';
		$productIndex[27] = 'Picture URL 15';
		$productIndex[28] = 'Picture URL 16';
		$productIndex[29] = 'Picture URL 17';
		$productIndex[30] = 'Picture URL 18';
		$productIndex[31] = 'Picture URL 19';
		$productIndex[32] = 'Picture URL 20';
		$productIndex[33] = 'Picture URL 21';
		$productIndex[34] = 'Picture URL 22';
		$productIndex[35] = 'Picture URL 23';
		$productIndex[36] = 'Picture URL 24';

		//$productIndex[25] = 'C:Herstellernummer';
		//$productIndex[26] = 'C:Marke';

		//Set distribution feed index
		$productIndex[40] = 'Category';
		$productIndex[41] = 'Store Category Name 1';
		$productIndex[42] = 'Store Category Name 2';
		$productIndex[43] = 'Shipping Policy';
		$productIndex[44] = 'Payment Policy';
		$productIndex[45] = 'Return Policy';
		$productIndex[46] = 'List Price';
		$productIndex[47] = 'Apply Tax';
		$productIndex[48] = 'VATPercent';

		//Set inventory feed index
		$productIndex[49] = 'Total Ship To Home Quantity';

		//Set attributes
		$maxAttributes = 100;
		$attributeIndex = 1;
		while($attributeIndex < $maxAttributes) {
			$productIndex[($attributeIndex*2)+100] = 'Attribute Name '.$attributeIndex;
			$productIndex[($attributeIndex*2)+101] = 'Attribute Value '.$attributeIndex;
			++$attributeIndex;
		}

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
			$imageUrl = $url.'media/'.$dir1.'/'.$dir2.'/'.$clientid.'/images/';
			$imagePath = BASE_PATH.'/media/'.$dir1.'/'.$dir2.'/'.$clientid.'/images/';
			$template = $this->getTemplate($connection, $accountid, $clientid);
			$taxRates = $this->getTaxRates($connection, $clientid);
			$manufacturers = $this->getManufacturers($connection, $clientid);
			$productCount = 0;
			foreach($items as $item) {
				$images = $this->getImages($connection, $item['itemid'], 'items', 'item');
				$attributes = $this->getAttributes($connection, $item['itemid']);
				if($images && count($images) && file_exists($imagePath.'/'.$images[0]['url'])) {
					list($width, $height, $type, $attr) = getimagesize($imagePath.'/'.$images[0]['url']);
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
						$manufacturerDescription = $manufacturers[$item['manufacturerid']]['description'];
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
						if($item['manufacturerid'] && isset($manufacturers[$item['manufacturerid']]['name'])) {
							$brand = $manufacturers[$item['manufacturerid']]['name'];
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



								$listingPrice = $price;
								if($taxRate) $listingPrice = $listingPrice + ($listingPrice * $taxRate / 100);
								$listingPrice = round($listingPrice, 2);

								//Max title length 80 characters
								if(strlen($eBayTitle) > 82) $eBayTitle = substr($eBayTitle, 0, 82);

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
								$templateItem = str_replace('%MANUFACTURERDESCRIPTION%', $manufacturerDescription, $templateItem);
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

								//Set Picture URLs 1 to 24
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
								$productLines[$productCount][25] = ''; //Picture URL 13
								$productLines[$productCount][26] = ''; //Picture URL 14
								$productLines[$productCount][27] = ''; //Picture URL 15
								$productLines[$productCount][28] = ''; //Picture URL 16
								$productLines[$productCount][29] = ''; //Picture URL 17
								$productLines[$productCount][30] = ''; //Picture URL 18
								$productLines[$productCount][31] = ''; //Picture URL 19
								$productLines[$productCount][32] = ''; //Picture URL 20
								$productLines[$productCount][33] = ''; //Picture URL 21
								$productLines[$productCount][34] = ''; //Picture URL 22
								$productLines[$productCount][35] = ''; //Picture URL 23
								$productLines[$productCount][36] = ''; //Picture URL 24
								$i = 13;
								foreach($images as $image) {
									if($i < 37) {
										if($image['url'] && file_exists($imagePath.'/'.$image['url'])) {
											list($width, $height, $type, $attr) = getimagesize($imagePath.'/'.$image['url']);
											if(($width >= 500) || ($height >= 500)) {
												$productLines[$productCount][$i] = $imageUrl.$image['url'];
												++$i;
											}
										}
									}
								}

								//Define lines for distribution feed
								$productLines[$productCount][40] = $eBayCategory1;
								$productLines[$productCount][41] = $eBayStoreCategory1;
								$productLines[$productCount][42] = $eBayStoreCategory2;
								$productLines[$productCount][43] = $shippingPolicy;
								$productLines[$productCount][44] = $paymentPolicy;
								$productLines[$productCount][45] = $returnPolicy;
								$productLines[$productCount][46] = $listingPrice;
								$productLines[$productCount][47] = $applyTax;
								$productLines[$productCount][48] = $taxRate;

								//Define lines for inventory feed
								$productLines[$productCount][49] = $quantity;

								//Set attributes
								if(isset($brand)) {
									$productLines[$productCount][50] = 'Marke';
									$productLines[$productCount][51] = $brand;
									$productLines[$productCount][52] = 'Herstellernummer';
									$productLines[$productCount][53] = $sku;
								}

								$i = 1;
								//Get attributes
								if($attributes && count($attributes)) {
									foreach($attributes as $attribute) {
										if($attribute['title'] != 'Zeichnung' && strlen($attribute['description']) <= 65) {
											$productLines[$productCount][$i+100] = $attribute['title'];
											$productLines[$productCount][$i+101] = $attribute['description'];
											$i = $i + 2;
										}
									}
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
					$this->log('Error: there is no image for: '.$item['sku'].' ('.$imagePath.'/'.$images[0]['url'].')');
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

		// Create an SFTP object
		$sftp = new SFTP($host, $port);

		// Connect and authenticate
		if (!$sftp->login($user, $pass)) {
			$this->log("SSH login failed");
			return false; // Return false to indicate failure
		} else {
			$this->log("SSH login successful");
		}

		// Upload the file
		$remoteFilePath = $remoteDir.$filename;
		$localFilePath = $filePath.'/'.$filename;
		if ($sftp->put($remoteFilePath, $localFilePath, SFTP::SOURCE_LOCAL_FILE)) {
			$this->log("Upload successful");
		} else {
			$this->log("Upload failed");
			return false; // Return false to indicate failure
		}

		$this->log('Write the contents to the server successful');
		return true;

		// Attempt to connect
		/*$connection = ssh2_connect($host, $port);
		if (!$connection) {
			$error = error_get_last();
			$this->log("SSH connection failed: " . $error['message']);
			return false; // Return false to indicate failure
		}
		$this->log("SSH connection established.");

		// Proceed with authentication
		/*if (!ssh2_auth_password($connection, $user, $pass)) {
			$this->log("Authentication failed.");
			ssh2_disconnect($connection);
			return false;
		}
		$this->log("Authentication successful.");

		$sftp = ssh2_sftp($connection);
		if (!$sftp) {
			$this->log('Failed to create a SFTP connection');
			ssh2_disconnect($connection);
			return false;
		}

		$stream = fopen("ssh2.sftp://$sftp$remoteDir$filename", 'w');
		if (!$stream) {
			$this->log('Open SFTP file connection failed');
			ssh2_disconnect($connection);
			return false;
		}

		$file = file_get_contents($filePath.'/'.$filename);
		if ($file === false) {
			$this->log("Couldn't get the file contents");
			fclose($stream);
			ssh2_disconnect($connection);
			return false;
		}

		if(fwrite($stream, $file) === false) {
			$this->log("Couldn't write the contents to the server");
			fclose($stream);
			ssh2_disconnect($connection);
			return false;
		}

		fclose($stream);
		ssh2_disconnect($connection);*/
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
					AND i.deleted = 0
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

	public function getImages($connection, $parentid, $module, $controller) {
		$query = '
				SELECT
					* FROM media
				WHERE
					parentid = "'.$parentid.'"
					AND module = "'.$module.'"
					AND controller = "'.$controller.'"
					AND type = "image"
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
				$manufacturers[$manufacturer['id']] = array();
				$manufacturers[$manufacturer['id']]['name'] = $manufacturer['name'];
				$manufacturers[$manufacturer['id']]['description'] = $manufacturer['description'];
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
