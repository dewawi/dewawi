<?php

class OpenCart {

	protected $connection;

	protected $options;

	protected $optionValues;

	protected $attributes;

	protected $attributeGroupID;

	protected $stockStatus;

	public function __construct($host, $username, $password, $dbname) {
		$this->connection = mysqli_connect($host, $username, $password, $dbname);
		$this->options = $this->getOptions();
		$this->optionValues = $this->getOptionValues();
		$this->attributeGroupID = $this->getAttributeGroupID();
		$this->attributes = $this->getAttributes();
		$this->stockStatus = $this->getStockStatus();
	}

	public function getProduct($sku) {
		$query = '
				SELECT
					* FROM oc_product
				WHERE
					sku = "'.$sku.'";';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_array($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getProducts() {
		$query = 'SELECT * FROM oc_product';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getStockStatus() {
		$query = 'SELECT * FROM oc_stock_status';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
			$stockStatus = array();
			foreach($data as $value) {
				$stockStatus[$value['stock_status_id']] = $value['name'];
			}
		    return $stockStatus;
		} else {
		    return false;
		}
	}

	public function getAttributeGroupID() {
		$query = 'SELECT * FROM oc_attribute_group_description WHERE name = "DEWAWI"';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    $data = mysqli_fetch_array($result, MYSQLI_ASSOC);
		    return $data['attribute_group_id'];
		} else {
		    return false;
		}
	}

	public function getAttributes() {
		$query = '
				SELECT
					a.*, d.*
				FROM
					oc_attribute a
				LEFT JOIN
					oc_attribute_description d
				ON
					a.attribute_id = d.attribute_id
				WHERE
					a.attribute_group_id = "'.$this->attributeGroupID.'";';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getOptions() {
		$query = '
				SELECT
					o.*, d.*
				FROM
					oc_option o
				LEFT JOIN
					oc_option_description d
				ON
					o.option_id = d.option_id;';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getOptionValues() {
		$query = '
				SELECT
					o.*, d.*
				FROM
					oc_option_value o
				LEFT JOIN
					oc_option_value_description d
				ON
					o.option_id = d.option_id;';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function updateProduct($data, $id) {
		// Update product data
		$data['product']['date_modified'] = date('Y-m-d H:i:s');
		$query = 'UPDATE oc_product SET ';
		$i = 1;
		$count = count($data['product']);
		foreach($data['product'] as $attribute => $value) {
			$value = mysqli_real_escape_string($this->connection, $value);
			$query .= $attribute.' = "'.$value.'"';
			if($i < $count) $query .= ', ';
			++$i;
		}
		$query .= " WHERE product_id = ".$id.";";
		$result = mysqli_query($this->connection, $query);
		//var_dump($query);

		// Update product description
		$query = 'UPDATE oc_product_description SET ';
		$i = 1;
		$count = count($data['product_description']);
		foreach($data['product_description'] as $attribute => $value) {
			$value = mysqli_real_escape_string($this->connection, $value);
			$query .= $attribute.' = "'.$value.'"';
			if($i < $count) $query .= ', ';
			++$i;
		}
		$query .= " WHERE product_id = ".$id.";";
		$result = mysqli_query($this->connection, $query);
		//var_dump($query);

		// Update product category
		$query = 'UPDATE oc_product_to_category SET ';
		$query .= 'category_id = "'.$data['category_id'].'"';
		$query .= " WHERE product_id = ".$id.";";
		$result = mysqli_query($this->connection, $query);
		//var_dump($query);

		// Update product stock status
		if(isset($data['stock_status']) && $data['stock_status']) {
			$stock_status_id = array_search($data['stock_status'], $this->stockStatus);
			if($stock_status_id) {
				$query = 'UPDATE oc_product SET ';
				$query .= 'stock_status_id = "'.$stock_status_id.'"';
				$query .= " WHERE product_id = ".$id.";";
				$result = mysqli_query($this->connection, $query);
			}
		}

		if($result) {
		    return true;
		} else {
		    return false;
		}
	}

	public function updateProductImages($images, $id, $sku) {
		// Delete all product images
		$query = 'DELETE FROM `oc_product_image` ';
		$query .= " WHERE product_id = ".$id.";";
		$result = mysqli_query($this->connection, $query);

		if($images) {
			foreach($images as $key => $image) {
				if($key == 0) {
					$data['product']['date_modified'] = date('Y-m-d H:i:s');
					$query = 'UPDATE oc_product SET ';
					$query .= " image = 'catalog".$image['url']."'";
					$query .= " WHERE product_id = ".$id.";";
					$result = mysqli_query($this->connection, $query);
					//var_dump($query);
				} else {
					$query = 'INSERT INTO oc_product_image (`product_id`, `image`, `sort_order`) VALUES ';
					$query .= "(".$id.", 'catalog".$image['url']."', '".$key."');";
					$result = mysqli_query($this->connection, $query);
					//var_dump($query);
				}
			}
		} else {
			$this->log('Error: No image found for '.$sku);
		}

		if($result) {
		    return true;
		} else {
		    return false;
		}
	}

	public function addAttribute($title) {
		$query = 'INSERT INTO oc_attribute (`attribute_group_id`, `sort_order`) VALUES ';
		$query .= '('.$this->attributeGroupID.', 0);';
		$result = mysqli_query($this->connection, $query);
		$id = mysqli_insert_id($this->connection);

		$query = 'INSERT INTO oc_attribute_description (`attribute_id`, `language_id`, `name`) VALUES ';
		$query .= '('.$id.', 1, "'.$title.'");';
		$result = mysqli_query($this->connection, $query);
		//var_dump($query);

		if($result) {
			$this->attributes = $this->getAttributes();
		    return $id;
		} else {
		    return false;
		}
	}

	public function addOption($title, $type) {
		$query = 'INSERT INTO oc_option (`type`, `sort_order`) VALUES ';
		$query .= '("'.$type.'", 0);';
		$result = mysqli_query($this->connection, $query);
		$id = mysqli_insert_id($this->connection);

		$query = 'INSERT INTO oc_option_description (`option_id`, `language_id`, `name`) VALUES ';
		$query .= '('.$id.', 1, "'.$title.'");';
		$result = mysqli_query($this->connection, $query);
		//var_dump($query);

		if($result) {
			$this->options = $this->getOptions();
		    return $id;
		} else {
		    return false;
		}
	}

	public function addOptionValue($title, $option_id) {
		$query = 'INSERT INTO oc_option_value (`option_id`, `image`, `sort_order`) VALUES ';
		$query .= '('.$option_id.', "", 0);';
		$result = mysqli_query($this->connection, $query);
		$id = mysqli_insert_id($this->connection);

		$query = 'INSERT INTO oc_option_value_description (`option_value_id`, `language_id`, `option_id`, `name`) VALUES ';
		$query .= '('.$id.', 1, '.$option_id.', "'.$title.'");';
		$result = mysqli_query($this->connection, $query);
		//var_dump($query);

		if($result) {
			$this->optionValues = $this->getOptionValues();
		    return $id;
		} else {
		    return false;
		}
	}

	public function updateProductAttributes($attributes, $id) {
		// Delete all product attributes
		$query = 'DELETE FROM `oc_product_attribute` ';
		$query .= " WHERE product_id = ".$id.";";
		$result = mysqli_query($this->connection, $query);

		if($attributes) {
			foreach($attributes as $key => $attribute) {
				if($attribute_id = $this->attributeExists($attribute['title'])) {
					$query = 'INSERT INTO oc_product_attribute (`product_id`, `attribute_id`, `language_id`, `text`) VALUES ';
					$query .= '('.$id.', '.$attribute_id.', 1, "'.htmlspecialchars($attribute['value']).'");';
					$result = mysqli_query($this->connection, $query);
					//var_dump($query);
				} elseif($attribute_id = $this->addAttribute($attribute['title'])) {
					$query = 'INSERT INTO oc_product_attribute (`product_id`, `attribute_id`, `language_id`, `text`) VALUES ';
					$query .= '('.$id.', '.$attribute_id.', 1, "'.htmlspecialchars($attribute['value']).'");';
					$result = mysqli_query($this->connection, $query);
					//var_dump($query);
				}
			}
		}

		if($result) {
		    return true;
		} else {
		    return false;
		}
	}

	public function updateProductOptions($options, $id) {
		// Delete all product options
		$query = 'DELETE FROM `oc_product_option` ';
		$query .= " WHERE product_id = ".$id.";";
		$result = mysqli_query($this->connection, $query);

		if($options) {
			foreach($options as $key => $option) {
				if($option_id = $this->optionExists($option['title'], $option['type'])) {
					$query = 'INSERT INTO oc_product_option (`product_id`, `option_id`, `value`, `required`) VALUES ';
					$query .= '('.$id.', '.$option_id.', "", "'.$option['required'].'");';
					$result = mysqli_query($this->connection, $query);
					$product_option_id = mysqli_insert_id($this->connection);
					if(isset($option['rows']) && count($option['rows'])) {
						foreach($option['rows'] as $row) {
							//Max title length 128 characters
							if(strlen($row['title']) > 128) $row['title'] = substr($row['title'], 0, 128);
							if($option_value_id = $this->optionValueExists($row['title'], $option_id)) {
								$query = 'INSERT INTO oc_product_option_value (`product_option_id`, `product_id`, `option_id`, `option_value_id`, `quantity`, `subtract`, `price`, `price_prefix`, `points`, `points_prefix`, `weight`, `weight_prefix`) VALUES ';
								$query .= '('.$product_option_id.', '.$id.', '.$option_id.', '.$option_value_id.', 7, 1, '.$row['price'].', "+", 0, "+", 0, "+");';
								$result = mysqli_query($this->connection, $query);
							} elseif($option_value_id = $this->addOptionValue($row['title'], $option_id)) {
								$query = 'INSERT INTO oc_product_option_value (`product_option_id`, `product_id`, `option_id`, `option_value_id`, `quantity`, `subtract`, `price`, `price_prefix`, `points`, `points_prefix`, `weight`, `weight_prefix`) VALUES ';
								$query .= '('.$product_option_id.', '.$id.', '.$option_id.', '.$option_value_id.', 7, 1, '.$row['price'].', "+", 0, "+", 0, "+");';
								$result = mysqli_query($this->connection, $query);
							}
						}
					}
				} elseif($option_id = $this->addOption($option['title'], $option['type'])) {
					$query = 'INSERT INTO oc_product_option (`product_id`, `option_id`, `value`, `required`) VALUES ';
					$query .= '('.$id.', '.$option_id.', "", "'.$option['required'].'");';
					$result = mysqli_query($this->connection, $query);
					$product_option_id = mysqli_insert_id($this->connection);
					if(isset($option['rows']) && count($option['rows'])) {
						foreach($option['rows'] as $row) {
							//Max title length 128 characters
							if(strlen($row['title']) > 128) $row['title'] = substr($row['title'], 0, 128);
							if($option_value_id = $this->optionValueExists($row['title'], $option_id)) {
								$query = 'INSERT INTO oc_product_option_value (`product_option_id`, `product_id`, `option_id`, `option_value_id`, `quantity`, `subtract`, `price`, `price_prefix`, `points`, `points_prefix`, `weight`, `weight_prefix`) VALUES ';
								$query .= '('.$product_option_id.', '.$id.', '.$option_id.', '.$option_value_id.', 7, 1, '.$row['price'].', "+", 0, "+", 0, "+");';
								$result = mysqli_query($this->connection, $query);
							} elseif($option_value_id = $this->addOptionValue($row['title'], $option_id)) {
								$query = 'INSERT INTO oc_product_option_value (`product_option_id`, `product_id`, `option_id`, `option_value_id`, `quantity`, `subtract`, `price`, `price_prefix`, `points`, `points_prefix`, `weight`, `weight_prefix`) VALUES ';
								$query .= '('.$product_option_id.', '.$id.', '.$option_id.', '.$option_value_id.', 7, 1, '.$row['price'].', "+", 0, "+", 0, "+");';
								$result = mysqli_query($this->connection, $query);
							}
						}
					}
				}
			}
		}

		if($result) {
		    return true;
		} else {
		    return false;
		}
	}

	public function attributeExists($title) {
		if($this->attributes) {
			foreach($this->attributes as $key => $attribute) {
				if($attribute['name'] == $title) {
					return $attribute['attribute_id'];
				}
			}
		}
		return null;
	}

	public function optionExists($title, $type) {
		if($this->options) {
			foreach($this->options as $key => $option) {
				if(($option['name'] == $title) && ($option['type'] == $type)) {
					return $option['option_id'];
				}
			}
		}
		return null;
	}

	public function optionValueExists($title, $option_id) {
		if($this->optionValues) {
			foreach($this->optionValues as $key => $optionValue) {
				if(($optionValue['name'] == $title) && ($optionValue['option_id'] == $option_id)) {
					return $optionValue['option_value_id'];
				}
			}
		}
		return null;
	}

	public function getProductCategories() {
		$query = '
				SELECT
					c.category_id, c.top, c.parent_id, d.name
				FROM
					oc_category c
				LEFT JOIN
					oc_category_description d
				ON
					d.category_id = c.category_id;';
		$result = mysqli_query($this->connection, $query);
		if($result && (mysqli_num_rows($result) > 0)) {
		    return mysqli_fetch_all($result, MYSQLI_ASSOC);
		} else {
		    return false;
		}
	}

	public function getProductCategoryID($shopcategory, $sku) {
		$categoryID = 0;
		$currentCategory = $this->getProductCategoryIndex();
		$shopCategories = explode(' > ', $shopcategory);
		foreach($shopCategories as $shopCategory) {
			if(isset($currentCategory[md5($shopCategory)])) {
				$currentCategory = $currentCategory[md5($shopCategory)];
				$categoryID = $currentCategory['id'];
				if(isset($currentCategory['childs'])) $currentCategory = $currentCategory['childs'];
			}
		}
		if($categoryID == 0) {
			$this->log('Error: No category found for '.$sku.': '.$shopcategory);
		}
	    return $categoryID;
	}

	public function getProductCategoryIndex() {
		$categories = $this->getProductCategories();
		$categoriesByID = array();
		foreach($categories as $category) {
			$categoriesByID[$category['category_id']] = htmlspecialchars_decode($category['name']);
		}

		$childCategories = array();
		foreach($categories as $category) {
			if(isset($childCategories[$category['parent_id']])) {
				array_push($childCategories[$category['parent_id']], $category['category_id']);
			} else {
				$childCategories[$category['parent_id']] = array($category['category_id']);
			}
		}

		$categoryIndex = array();
		foreach($categories as $category) {
			if($category['parent_id'] == 0) {
				$categoryIndex[md5(htmlspecialchars_decode($category['name']))]['id'] = $category['category_id'];
				$categoryIndex[md5(htmlspecialchars_decode($category['name']))]['title'] = htmlspecialchars_decode($category['name']);
				if(isset($childCategories[$category['category_id']])) {
					$categoryIndex[md5(htmlspecialchars_decode($category['name']))]['childs'] = $this->getSubCategoryIndex($categoriesByID, $childCategories, $category['category_id']);
				}
			}
		}
		//var_dump($categoriesByID);
		//var_dump($childCategories);

		return $categoryIndex;
	}

	public function getSubCategoryIndex($categories, $childCategories, $id) {
		$subCategories = array();
		foreach($childCategories[$id] as $child) {
			$subCategories[md5($categories[$child])]['id'] = $child;
			$subCategories[md5($categories[$child])]['title'] = $categories[$child];
			if(isset($childCategories[$child])) {
				$subCategories[md5($categories[$child])]['childs'] = $this->getSubCategoryIndex($categories, $childCategories, $child);
			}
		}
		return $subCategories;
	}

	public function insertProduct($sku, $name, $catid) {
		// Attempt insert query execution
		$date_available = date('Y-m-d');
		$date_added = date('Y-m-d H:i:s');
		$query = 'INSERT INTO oc_product (`model`, `sku`, `upc`, `ean`, `jan`, `isbn`, `mpn`, `location`, `quantity`, `stock_status_id`, `image`, `manufacturer_id`, `shipping`, `price`, `points`, `tax_class_id`, `date_available`, `weight`, `weight_class_id`, `length`, `width`, `height`, `length_class_id`, `subtract`, `minimum`, `sort_order`, `status`, `viewed`, `date_added`, `date_modified`) VALUES ("'.$sku.'", "'.$sku.'", "", "", "", "", "", "", 1, 6, "", 0, 1, "0.0000", 0, 0, "'.$date_available.'", "0.00000000", 1, "0.00000000", "0.00000000", "0.00000000", 1, 1, 1, 1, 1, 3, "'.$date_added.'", "'.$date_added.'");';
		if(mysqli_query($this->connection, $query)) {
			$id = mysqli_insert_id($this->connection);

			$queryDescription = 'INSERT INTO oc_product_description (`product_id`, `language_id`, `name`, `description`, `tag`, `meta_title`, `meta_description`, `meta_keyword`) VALUES ('.$id.', 1, "'.$name.'", "", "", "'.$name.'", "", "");';
			mysqli_query($this->connection, $queryDescription);

			$queryCategory = 'INSERT INTO oc_product_to_category (`product_id`, `category_id`) VALUES ('.$id.', '.$catid.');';
			mysqli_query($this->connection, $queryCategory);

			$queryLayout = 'INSERT INTO oc_product_to_layout (`product_id`, `store_id`, `layout_id`) VALUES ('.$id.', 0, 0);';
			mysqli_query($this->connection, $queryLayout);

			$queryStore = 'INSERT INTO oc_product_to_store (`product_id`, `store_id`) VALUES ('.$id.', 0);';
			mysqli_query($this->connection, $queryStore);

			$keyword = strtolower($name.'-'.$sku);
			$keyword = str_replace('&', '', $keyword);
			$keyword = str_replace(',', '', $keyword);
			$keyword = str_replace('„', '', $keyword);
			$keyword = str_replace('“', '', $keyword);
			$keyword = str_replace('”', '', $keyword);
			$keyword = str_replace('!', '', $keyword);
			$keyword = str_replace('+', '', $keyword);
			$keyword = str_replace('™', '', $keyword);
			$keyword = str_replace('*', '', $keyword);
			$keyword = str_replace('^', '', $keyword);
			$keyword = str_replace('$', '', $keyword);
			$keyword = str_replace('@', '', $keyword);
			$keyword = str_replace('#', '', $keyword);
			$keyword = str_replace('–', '', $keyword);
			$keyword = str_replace(' ', '-', $keyword);
			$keyword = str_replace('--', '-', $keyword);
			$keyword = str_replace('--', '-', $keyword);
			$keyword = str_replace('ä', 'ae', $keyword);
			$keyword = str_replace('ü', 'ue', $keyword);
			$keyword = str_replace('ö', 'oe', $keyword);
			$querySeo = 'INSERT INTO oc_seo_url (`store_id`, `language_id`, `query`, `keyword`) VALUES (0, 1, "product_id='.$id.'", "'.$keyword.'");';
			mysqli_query($this->connection, $querySeo);
		    return $id;
		} else{
		    return false;
		}
	}

	public function removeProduct($product_id) {
		// Attempt delete query execution
		$query = 'DELETE FROM oc_product WHERE `product_id` = '.$product_id.';';
		if(mysqli_query($this->connection, $query)) {
			$queryAttribute = 'DELETE FROM oc_product_attribute WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryAttribute);

			$queryDescription = 'DELETE FROM oc_product_description WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryDescription);

			$queryDiscount = 'DELETE FROM oc_product_discount WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryDiscount);

			$queryFilter = 'DELETE FROM oc_product_filter WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryFilter);

			$queryImage = 'DELETE FROM oc_product_image WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryImage);

			$queryOption = 'DELETE FROM oc_product_option WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryOption);

			$queryOptionValue = 'DELETE FROM oc_product_option_value WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryOptionValue);

			$queryRecurring = 'DELETE FROM oc_product_recurring WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryRecurring);

			$queryRelated = 'DELETE FROM oc_product_related WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryRelated);

			$queryReward = 'DELETE FROM oc_product_reward WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryReward);

			$querySpecial = 'DELETE FROM oc_product_special WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $querySpecial);

			$queryCategory = 'DELETE FROM oc_product_to_category WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryCategory);

			$queryDownload = 'DELETE FROM oc_product_to_download WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryDownload);

			$queryLayout = 'DELETE FROM oc_product_to_layout WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryLayout);

			$queryStore = 'DELETE FROM oc_product_to_store WHERE `product_id` = '.$product_id.';';
			mysqli_query($this->connection, $queryStore);

			$querySeo = 'DELETE FROM oc_seo_url WHERE `query` = "product_id='.$product_id.'";';
			mysqli_query($this->connection, $querySeo);
		} else{
		    return false;
		}
	}

	public function updateProductSeoUrl($name, $sku, $id) {
		// Update product SEO Url
		$keyword = strtolower($name.'-'.$sku);
		$keyword = str_replace('&', '', $keyword);
		$keyword = str_replace(',', '', $keyword);
		$keyword = str_replace('„', '', $keyword);
		$keyword = str_replace('“', '', $keyword);
		$keyword = str_replace('”', '', $keyword);
		$keyword = str_replace('!', '', $keyword);
		$keyword = str_replace('+', '', $keyword);
		$keyword = str_replace('™', '', $keyword);
		$keyword = str_replace('*', '', $keyword);
		$keyword = str_replace('^', '', $keyword);
		$keyword = str_replace('$', '', $keyword);
		$keyword = str_replace('@', '', $keyword);
		$keyword = str_replace('#', '', $keyword);
		$keyword = str_replace('–', '', $keyword);
		$keyword = str_replace('/', '-', $keyword);
		$keyword = str_replace(' ', '-', $keyword);
		$keyword = str_replace('--', '-', $keyword);
		$keyword = str_replace('--', '-', $keyword);
		$keyword = str_replace('ä', 'ae', $keyword);
		$keyword = str_replace('ü', 'ue', $keyword);
		$keyword = str_replace('ö', 'oe', $keyword);
		$query = "UPDATE oc_seo_url SET keyword = '".$keyword."' WHERE query = 'product_id=".$id."';";
		$result = mysqli_query($this->connection, $query);

		if($result) {
		    return true;
		} else {
		    return false;
		}
	}

	public function log($message) {
		$file = BASE_PATH.'/logs/opencart.log';
	    error_log(date("Y-m-d H:i:s").' '.$message." \n", 3, $file);
	}
}
