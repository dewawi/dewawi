<?php

class Dewawi_Shop {

	protected $basePath;

	protected $connection;

	public function __construct($basePath, $host, $username, $password, $dbname) {
		$this->basePath = $basePath;
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
	}

	public function allShops() {
		$shops = $this->getShops();
		foreach($shops as $shop) {
		}
	}

	public function listItems($shopid) {
		$shop = $this->getShop($shopid);
		$shoptitle = $shop['title'];
		$clientid = $shop['clientid'];
		$items = $this->getItems($shopid, $clientid);
		$deliveryTimes = $this->getDeliveryTimes($clientid);

		if($items && count($items)) {
			require_once($this->basePath.'/library/Dewawi/PriceRule.php');
			if($shop['type'] == 'opencart') {
				require_once($this->basePath.'/library/OpenCart/opencart.php');
				$OpenCart = new OpenCart($shop['host'], $shop['username'], $shop['password'], $shop['dbname']);
				$OpenCart->log('Opencart cronjob gestartet');
				$OpenCart->log('Prepare '.count($items).' items for upload to shop '.$shoptitle);

				$ordering = 0;
				$itemsUpdated = 0;
				$itemsCreated = 0;
				foreach($items as $item) {
					$sku = $item['sku'];
					$title = $item['shoptitle'] ? $item['shoptitle'] : $item['title'];
					$product = $OpenCart->getProduct($sku);
					if($sku && $title && $item['price'] && ($item['price'] > 0)) {
						$description = $item['shopdescription'] ? $item['shopdescription'] : $item['description'];
						$shortDescription = $item['shopdescriptionshort'];
						$miniDescription = $item['shopdescriptionmini'];

						//Create description html
						if(strpos($description, '<p>') === false) {
							$description = '<p>'.$description.'</p>';
							$description = str_replace("\n", '<br>', $description);
						}

						//Set options for price rules
						$priceRuleOptions = array();
						if($item['type']) $priceRuleOptions['itemtype'] = $item['type'];
						if($item['manufacturerid']) $priceRuleOptions['itemmanufacturer'] = $item['manufacturerid'];

						//Use price rules
						$PriceRule = new Dewawi_PriceRule();
						$price = $PriceRule->usePricerules($item, $this->connection, $priceRuleOptions);

						if(isset($item['video'])) {
							$videoHtml = '<iframe width="100%" height="500" src="https://www.youtube.com/embed/'.$item['video'].'" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
							$description = $videoHtml.$description;
						}

						if(isset($item['downloads'])) {
							$downloads = explode("\n", $item['downloads']);
							$downloadsHtml = '<h3>Downloads</h3>';
							$downloadsHtml .= '<ul>';
							foreach($downloads as $download) {
								if(strpos($download, '|')) {
									list($language, $name, $url) = explode('|', $download);
									$downloadsHtml .= '<li><a target="_blank" href="'.$url.'">'.$name.'</a></li>';
								}
							}
							$downloadsHtml .= '</ul>';
							$description = $description.$downloadsHtml;
						}

						//Get images
						$images = $this->getImages($item['id']);

						//Get options and rows
						$options = $this->getOptions($item, $priceRuleOptions);

						//Get attributes
						$attributes = $this->getAttributes($item['id']);
						if($shortDescription) $attributes[] = array('title' => 'Kurzbeschreibung', 'value' => $shortDescription);
						if($miniDescription) $attributes[] = array('title' => 'Minibeschreibung', 'value' => $miniDescription);

						$data = array();
						$data['product'] = array();
						$data['product']['sku'] = $sku;
						$data['product']['model'] = $sku;
						$data['product']['quantity'] = 0;
						if($item['inventory'] && $item['quantity']) {
							$data['product']['quantity'] = $item['quantity'];
						} else {
							$data['product']['quantity'] = 100;
						}
						//echo $sku.": ".$price."\n";
						$data['product']['price'] = $price;
						$data['product']['sort_order'] = $ordering;
						$data['product']['tax_class_id'] = 9;
						$data['product_description'] = array();
						$data['product_description']['name'] = htmlspecialchars($title);
						$data['product_description']['description'] = htmlspecialchars($description);
						$data['category_id'] = $OpenCart->getProductCategoryID($item['shopcategory'], $sku);
						if($deliveryTimes) {
							if($item['deliverytimeoos'] && isset($deliveryTimes[$item['deliverytimeoos']])) {
								$data['stock_status'] = $deliveryTimes[$item['deliverytimeoos']];
							}
						}

						if($product) {
							$OpenCart->updateProduct($data, $product['product_id']);
							$OpenCart->updateProductSeoUrl($title, $sku, $product['product_id']);
							$OpenCart->updateProductImages($images, $product['product_id'], $sku);
							$OpenCart->updateProductOptions($options, $product['product_id']);
							$OpenCart->updateProductAttributes($attributes, $product['product_id']);
							$this->updateItem($item['id'], 1);
							++$itemsUpdated;
						} else {
							$productID = $OpenCart->insertProduct($sku, $title, $data['category_id']);
							if($productID) {
								$OpenCart->updateProduct($data, $productID);
								$OpenCart->updateProductSeoUrl($title, $sku, $productID);
								$OpenCart->updateProductImages($images, $productID, $sku);
								$OpenCart->updateProductOptions($options, $productID);
								$OpenCart->updateProductAttributes($attributes, $productID);
								$this->updateItem($item['id'], 1);
								++$itemsCreated;
							}
						}
						++$ordering;
					} elseif($sku) {
						if($product) $OpenCart->removeProduct($product['product_id']);
						$OpenCart->log('Error: No title set for item: '.$sku);
						$this->updateItem($item['id'], 0);
					} else {
						if($product) $OpenCart->removeProduct($product['product_id']);
						$OpenCart->log('Error: No sku set for item: '.$item['id']);
						$this->updateItem($item['id'], 0);
					}
				}
				$OpenCart->log($itemsUpdated.' existing items are updated');
				$OpenCart->log($itemsCreated.' new items are created');
				$OpenCart->log('Opencart cronjob beendet');
			}
		}
	}

	public function getShop($id) {
		$query = 'SELECT * FROM shopaccount WHERE id = '.$id;
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_array($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getShops() {
		$query = 'SELECT * FROM shopaccount';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getItems($shopid, $clientid) {
		$query = '
				SELECT
					s.*, i.*
				FROM
					shopitem s
				LEFT JOIN
					item i
				ON
					i.id = s.itemid
				WHERE
					s.shopid = '.$shopid.'
					AND s.clientid = '.$clientid.';';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getDeliveryTimes($clientid) {
		$query = '
				SELECT
					* FROM deliverytime
				WHERE
					clientid = "'.$clientid.'"
				ORDER
					BY ordering;';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
			$deliveryTimes = array();
			foreach($data as $value) {
				$deliveryTimes[$value['id']] = $value['title'];
			}
		    return $deliveryTimes;
		} else {
		    return false;
		}
	}

	public function getImages($itemid) {
		$query = '
				SELECT
					* FROM itemimage
				WHERE
					itemid = "'.$itemid.'"
				ORDER
					BY ordering;';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getOptions($item, $priceRuleOptions) {
		$query = '
				SELECT
					* FROM itemoption
				WHERE
					itemid = "'.$item['id'].'"
				ORDER
					BY ordering;';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
			$options = array();
		    $optionsData = mysqli_fetch_all($result, MYSQLI_ASSOC);
			foreach($optionsData as $optionData) {
				$options[$optionData['id']]['title'] = $optionData['title'];
				$options[$optionData['id']]['type'] = $optionData['type'];
				$options[$optionData['id']]['required'] = $optionData['required'];
				$options[$optionData['id']]['ordering'] = $optionData['ordering'];
				$query = '
						SELECT
							* FROM itemoptionrow
						WHERE
							optionid = "'.$optionData['id'].'"
						ORDER
							BY ordering;';
	   			$result = mysqli_query($this->connection, $query);
				if($result && (mysqli_num_rows($result) > 0)) {
					$rowsData = mysqli_fetch_all($result, MYSQLI_ASSOC);
					foreach($rowsData as $rowData) {
						//Use the same price rules as item for the option
						$PriceRule = new Dewawi_PriceRule();
						$item['price'] = $rowData['price'];
						$rowData['price'] = $PriceRule->usePricerules($item, $this->connection, $priceRuleOptions);

						$options[$optionData['id']]['rows'][$rowData['id']]['title'] = $rowData['title'];
						$options[$optionData['id']]['rows'][$rowData['id']]['price'] = $rowData['price'];
						$options[$optionData['id']]['rows'][$rowData['id']]['ordering'] = $rowData['ordering'];
					}
				}
			}
		    return $options;
		} else {
		    return false;
		}
	}

	public function getOptionRows($optionid) {
		$query = '
				SELECT
					* FROM itemoptionrow
				WHERE
					optionid = "'.$optionid.'"
				ORDER
					BY ordering;';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getAttributes($itemid) {
		$query = '
				SELECT
					* FROM itemattribute
				WHERE
					itemid = "'.$itemid.'"
				ORDER
					BY ordering;';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function updateItem($itemid, $listedby) {
		$modified = date("Y-m-d H:i:s");
		$query = '
				UPDATE
					shopitem
				SET
					modified = "'.$modified.'",
					listedby = '.$listedby.'
				WHERE
					itemid = '.$itemid.';';
		$result = mysqli_query($this->connection, $query);
		if($result) {
			return true;
		} else {
			return false;
		}
	}
}
