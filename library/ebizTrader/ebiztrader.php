<?php

class ebizTrader {

	public function getFeedIndex() {
		//Set product feed index
		$productIndex = array();
		$productIndex[0] = 'IMPORT_IDENTIFIER';
		$productIndex[1] = 'IMPORT_TASK';
		$productIndex[2] = 'CONFIRMED';
		$productIndex[3] = 'MWST';
		$productIndex[4] = 'MENGE';
		$productIndex[5] = 'ZUSTAND';
		$productIndex[6] = 'VERSANDKOSTEN';
		$productIndex[7] = 'VERSANDOPTIONEN';
		$productIndex[8] = 'MOQ';
		$productIndex[9] = 'BF_CONSTRAINTS';
		$productIndex[10] = 'LU_LAUFZEIT';
		$productIndex[11] = 'LIEFERTERMIN';
		$productIndex[12] = 'FK_KAT';
		$productIndex[13] = 'FK_COUNTRY';
		$productIndex[14] = 'ZIP';
		$productIndex[15] = 'CITY';
		$productIndex[16] = 'STREET';
		$productIndex[17] = 'IMPORT_IMAGES';
		$productIndex[18] = 'FK_ARTICLE_EXT';
		$productIndex[19] = 'EAN';
		$productIndex[21] = 'PREIS';
		$productIndex[24] = 'PRODUKTNAME';
		$productIndex[25] = 'BESCHREIBUNG';
		$productIndex[26] = 'WIDTH';
		$productIndex[27] = 'DEPTH';
		$productIndex[28] = 'HEIGHT';
		$productIndex[29] = 'PACKAGING_WIDTH';
		$productIndex[30] = 'PACKAGING_DEPTH';
		$productIndex[31] = 'PACKAGING_HEIGHT';
		$productIndex[32] = 'NET_WEIGHT';
		$productIndex[33] = 'GROS_WEIGHT';
		$productIndex[34] = 'HSCODE';

		return $productIndex;
	}

	public function getProductLines($connection, $account) {
		$accountid = $account['id'];
		$userid = $account['userid'];
		$clientid = $account['clientid'];
		$listings = $this->getListings($connection, $accountid, $clientid);
		$productLines = array();
		if($listings) {
			require_once(BASE_PATH.'/library/Dewawi/PriceRule.php');
			$dir1 = substr($clientid, 0, 1);
			if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
			else $dir2 = '0';
			$url = $this->url();
			$url = substr($url, 0, strpos($url, 'ebiztrader/index'));
			$imageUrl = $url.'media/items/'.$dir1.'/'.$dir2.'/'.$clientid;
			$imagePath = BASE_PATH.'/media/items/'.$dir1.'/'.$dir2.'/'.$clientid;
			//$template = $this->getTemplate($connection, $accountid, $clientid);
			$taxRates = $this->getTaxRates($connection, $clientid);
			$manufacturers = $this->getManufacturers($connection, $clientid);
			$productCount = 0;
			foreach($listings as $listing) {
				$images = $this->getItemimages($connection, $listing['itemid']);
				$attributes = $this->getAttributes($connection, $listing['itemid']);
				if($images && count($images) && file_exists($imagePath.$images[0]['url'])) {
					list($width, $height, $type, $attr) = getimagesize($imagePath.$images[0]['url']);
					if(($width >= 500) || ($height >= 500)) {
						//Define fields
						//$locale = $ebayUser['locale'];
						/*if($listing['shippingpolicy']) {
							$shippingPolicy = $listing['shippingpolicy'];
						} else {
							$shippingPolicy = $ebayUser['shippingpolicy'];
						}
						$paymentPolicy = $ebayUser['paymentpolicy'];
						$returnPolicy = $ebayUser['returnpolicy'];
						$measurementSystem = $ebayUser['measurementsystem'];*/

						//Define fields
						$sku = $listing['sku'];
						$importIdentifier = md5($sku);
						$importTask = 'U';
						$confirmed = '1';
						$taxIncluded = '1';
						$condition = '1';
						$shippingCost = '0';
						$shippingOptions = '0';
						$moq = '0';
						$bfConstraints = '0'; // 1=B2B Produkt  0=für alle
						$lifetime = '105';
						$deliveryTime = '3-5 Werktage';
						$country = '1';
						$postcode = '47228';
						$city = 'Duisburg';
						$street = 'Dieselstraße 11';
						$ean = $listing['gtin'];
						$name = $listing['title'];
						$description = $listing['description'];
						$category1 = $listing['category1'];
						$length = $listing['length'];
						$width = $listing['width'];
						$height = $listing['height'];
						$packaginglength = $listing['packlength'];
						$packagingwidth = $listing['packwidth'];
						$packagingheight = $listing['packheight'];
						$weight = $listing['weight'];
						$packagingweight = $listing['packweight'];
						$hscode = $listing['ctn'];
						$price = $listing['price'];
						if($listing['taxid'] && isset($taxRates[$listing['taxid']])) {
							$taxRate = $taxRates[$listing['taxid']];
						}
						if($listing['manufacturerid'] && isset($manufacturers[$listing['manufacturerid']])) {
							$brand = $manufacturers[$listing['manufacturerid']];
						}
						if($listing['inventory']) {
							$quantity = $listing['quantity'];
						} else {
							$quantity = 100;
						}
						$quantity = 100;

						//Check if ean is 13 digits long
						if(preg_match("/^[0-9]{13}$/", $ean)) {
							if($name && $price) {
								//Set options for price rules
								$options = array();
								if($listing['type']) $options['itemtype'] = $listing['type'];
								if($listing['manufacturerid']) $options['itemmanufacturer'] = $listing['manufacturerid'];

								//Use price rules
								$PriceRule = new Dewawi_PriceRule();
								$price = $PriceRule->usePricerules($listing, $connection, $options, $clientid);

								$listingPrice = $price;
								if($taxRate) $listingPrice = $listingPrice + ($listingPrice * $taxRate / 100);
								$listingPrice = round($listingPrice, 2);

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
											$attributesHtml .= '<td>'.$attribute['value'].'</td>';
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

								//Define lines for product feed
								$productLines[$productCount][0] = $importIdentifier;
								$productLines[$productCount][1] = $importTask;
								$productLines[$productCount][2] = $confirmed;
								$productLines[$productCount][3] = $taxIncluded;
								$productLines[$productCount][4] = $quantity;
								$productLines[$productCount][5] = $condition;
								$productLines[$productCount][6] = $shippingCost;
								$productLines[$productCount][7] = $shippingOptions;
								$productLines[$productCount][8] = $moq;
								$productLines[$productCount][9] = $bfConstraints;
								$productLines[$productCount][10] = $lifetime;
								$productLines[$productCount][11] = $deliveryTime;
								$productLines[$productCount][12] = $category1;
								$productLines[$productCount][13] = $country;
								$productLines[$productCount][14] = $postcode;
								$productLines[$productCount][15] = $city;
								$productLines[$productCount][16] = $street;
								$productLines[$productCount][17] = '';
								foreach($images as $image) {
									if($image['url'] && file_exists($imagePath.$image['url'])) {
										list($width, $height, $type, $attr) = getimagesize($imagePath.$image['url']);
										if(($width >= 500) || ($height >= 500)) {
											if($productLines[$productCount][17]) $productLines[$productCount][17] .= ',';
											$productLines[$productCount][17] .= $imageUrl.$image['url'];
										}
									}
								}
								$productLines[$productCount][17] = $imageUrl.$images[0]['url'];
								$productLines[$productCount][18] = $sku;
								$productLines[$productCount][19] = $ean;
								$productLines[$productCount][21] = $listingPrice;
								$productLines[$productCount][24] = $name;
								$productLines[$productCount][25] = $description;
								$productLines[$productCount][26] = $length;
								$productLines[$productCount][27] = $width;
								$productLines[$productCount][28] = $height;
								$productLines[$productCount][29] = $packaginglength;
								$productLines[$productCount][30] = $packagingwidth;
								$productLines[$productCount][31] = $packagingheight;
								$productLines[$productCount][32] = $weight;
								$productLines[$productCount][33] = $packagingweight;
								$productLines[$productCount][34] = $hscode;

								$this->updateListing($connection, $listing['id'], 1);
								$this->log($listing['id'].': '.$listing['sku']);
								++$productCount;
							} else {
								$this->updateListing($connection, $listing['id'], 0);
								$this->log('Error: price or title not set for: '.$listing['sku']);
							}
						} else {
							$this->updateListing($connection, $listing['id'], 0);
							$this->log('Error: no valid gtin/ean found for: '.$listing['sku']);
						}
					} else {
						$this->updateListing($connection, $listing['id'], 0);
						$this->log('Error: longest side of your images must be a minimum of 500 pixels '.$listing['sku'].': '.$width.'x'.$height.' px');
					}
				} else {
					$this->updateListing($connection, $listing['id'], 0);
					$this->log('Error: there is no image for: '.$listing['sku']);
				}
			}
		} else {
			$this->log('There are no items to list for: '.$userid);
		}

		return $productLines;
	}

	public function getListings($connection, $accountid, $clientid) {
		$query = '
				SELECT
					e.*, i.*
				FROM
					ebiztraderlisting e
				LEFT JOIN
					item i
				ON
					i.id = e.itemid
				WHERE
					e.accountid = "'.$accountid.'"
					AND e.clientid = '.$clientid.';';
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
					* FROM itemattribute
				WHERE
					itemid = "'.$itemid.'"
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
				ORDER
					BY ordering;';
		$result = mysqli_query($connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
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

	public function updateListing($connection, $itemid, $listedby) {
		$modified = date("Y-m-d H:i:s");
		$query = '
				UPDATE
					ebiztraderlisting
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

	public function log($message) {
		$file = BASE_PATH.'/logs/ebiztrader.log';
	    error_log(date("Y-m-d H:i:s").' '.$message." \n", 3, $file);
	}
}
