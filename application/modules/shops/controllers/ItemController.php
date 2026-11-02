<?php

class Shops_ItemController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$shop = $this->_site;
		$id = (int)$this->_getParam('id', 0);

		$itemDb = new Shops_Model_DbTable_Item();
		$item = $itemDb->getItem($id);

		if (!$item) {
			$this->renderSiteError(404);
			return;
		}

		$path = (new Shops_Model_DbTable_Slug())->getPublicPath('item', (int)$item['id']);
		if ($path) $this->setCanonicalPath($path);

		$currency = $this->_helper->Currency->getCurrency($item['currency'], 'USE_SYMBOL');

		if ($item['taxid']) {
			$taxrateDb = new Shops_Model_DbTable_Taxrate();
			$taxrate = $taxrateDb->getTaxRate($item['taxid']);
			$priceWithTax = $item['price'] * ((100 + $taxrate['rate']) / 100);
			$specialPriceWithTax = $item['specialprice'] * ((100 + $taxrate['rate']) / 100);
		} else {
			$priceWithTax = $item['price'];
			$specialPriceWithTax = $item['specialprice'];
		}

		$prices = [
			'raw' => $item['price'],
			'rawtax' => $priceWithTax,
			'rawspecialprice' => $item['specialprice'],
			'rawspecialpricetax' => $specialPriceWithTax,
			'formatted' => $currency->toCurrency($item['price']),
			'formattedtax' => $currency->toCurrency($priceWithTax),
			'formattedspecialprice' => $currency->toCurrency($item['specialprice']),
			'formattedspecialpricetax' => $currency->toCurrency($specialPriceWithTax),
		];

		$categoryDb = new Shops_Model_DbTable_Category();
		$category = $categoryDb->getCategory((int)$item['shopcatid']);

		if (!$category) {
			$this->renderSiteError(404);
			return;
		}

		$mediaDb = new Shops_Model_DbTable_Media();
		$images = $mediaDb->getMedia($id, 'items', 'item');
		$categoryImages = $mediaDb->getCategoryMediaById((int)$category['id']);
		$parentCategoryImages = $mediaDb->getCategoryMediaById((int)$category['parentid']);

		$manufacturersDb = new Shops_Model_DbTable_Manufacturer();
		$manufacturers = $manufacturersDb->getManufacturers();

		$clientid = (string)$shop['clientid'];
		$dir1 = substr($clientid, 0, 1);
		$dir2 = strlen($clientid) > 1 ? substr($clientid, 1, 1) : '0';

		$this->view->item = $item;
		$this->view->images = $images;
		$this->view->categoryImages = $categoryImages;
		$this->view->parentCategoryImages = $parentCategoryImages;
		$this->view->prices = $prices;
		$this->view->manufacturers = $manufacturers;
		$this->view->category = $category;
		$this->view->imagePath = $dir1 . '/' . $dir2 . '/' . $clientid;
		$this->view->attributeSets = $this->_helper->Attributes->getAttributes((int)$item['id']);
		$this->view->optionSets = $this->_helper->Options->getOptions((int)$item['id']);

		$this->assignMessages();
	}

	public function feedAction()
	{
		$shop = $this->_site;

		// Disable the view renderer (we're outputting XML directly)
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout->disableLayout();

		// Set the content type to XML
		$this->getResponse()->setHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

		// Initialize the base URL
		$baseUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost();
		$xml = static fn($value): string => htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

		// Begin XML output
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
		echo '<channel>';

		// Store information
		echo '<title>' . $xml($shop['title']) . '</title>';
		echo '<link>' . $xml($baseUrl . '/') . '</link>';
		echo '<description>' . $xml($shop['title']) . '</description>';

		// Fetch slugs for the current shop
		$slugTable = new Shops_Model_DbTable_Slug();
		$slugs = $slugTable->getSlugs();

		// Organize slugs into a dictionary with entityid as the key
		$slugDict = [];
		foreach ($slugs as $slug) {
			$slugDict[$slug['entityid']] = $slug;
			//echo $slug['entityid']."\n";
		}
		//print_r($slugDict);

		// Helper function to build the full slug path using entityid with parent-child hierarchy
		$getFullSlug = function ($item, $slugDict) {
			$slug = $item['slug'];
			
			// Continue while the item has a parent
			while ($item['parentid']) {
				// Find the parent item in the slugDict
				if (isset($slugDict[$item['parentid']])) {
					$parentItem = $slugDict[$item['parentid']];
					// Prepend the parent's slug to the current slug
					$slug = $parentItem['slug'] . '/' . $slug;
					$item = $parentItem;
				} else {
					break; // Parent not found, stop the loop
				}
			}
			
			return $slug;
		};

		// Loop through categories and add to the sitemap
		foreach ($slugs as $slug) {
			if (!empty($slug['slug'])) { // Ensure slug exists
				$fullSlug = $getFullSlug($slug, $slugDict); // Get full slug path
				$slugUrl = $shop['url'] . '/' . $fullSlug;
				//echo '<url>';
				//echo '<loc>' . htmlspecialchars($slugUrl) . '</loc>';
				//echo '<changefreq>weekly</changefreq>';
				//echo '<priority>0.6</priority>';
				//echo '</url>';
			}
		}

		// Get items for this shop
		$itemTable = new Shops_Model_DbTable_Item();
		$items = $itemTable->getItems();

		$images = array();
		$mediaDb = new Shops_Model_DbTable_Media();
		$images['items'] = $mediaDb->getItemMedia($items);

		//Get tax rates
		$taxratesDb = new Shops_Model_DbTable_Taxrate();
		$taxrates = $taxratesDb->getTaxRates();

		// Loop through items and add to the sitemap
		foreach ($items as $item) {
			$totalImages = count($images['items'][$item->id]);
			//echo 'cat:'.$item->shopcatid."\n";
			if($totalImages && $item->shopcatid && isset($slugDict[$item->id])) {
				// Get full slug path
				$fullSlug = $getFullSlug($slugDict[$item->id], $slugDict);
				$slugUrl = $shop['url'] . '/' . $fullSlug;

				// Ensure tax ID exists
				$taxRate = isset($taxrates[$item['taxid']]) ? $taxrates[$item['taxid']] : 0;

				// Calculate tax-inclusive price
				if($item['specialprice']) {
					$priceWithTax = $item['specialprice'] * ((100 + $taxRate) / 100);
				} else {
					$priceWithTax = $item['price'] * ((100 + $taxRate) / 100);
				}

				echo '<item>';
				echo '<g:id>' . $xml($item['sku']) . '</g:id>';
				echo '<g:title>' . $xml($item['title']) . '</g:title>';
				echo '<g:description>' . $xml($item['description']) . '</g:description>';
				echo '<g:link>' . $xml($slugUrl) . '</g:link>';
				$i = 0;
				foreach ($images['items'][$item->id] as $image) {
					if ($i == 0) {
						echo '<g:image_link>' . $xml($shop['url'] . '/media/images/' . $image['url']) . '</g:image_link>';
					} else {
						echo '<g:additional_image_link>' . $xml($shop['url'] . '/media/images/' . $image['url']) . '</g:additional_image_link>';
					}

					$i++;
				}
				echo '<g:availability>in_stock</g:availability>';
				echo '<g:quantity>10</g:quantity>';
				echo '<g:price>' . $xml($priceWithTax . ' ' . $item['currency']) . '</g:price>';
				echo '<g:brand>' . $xml($shop['title']) . '</g:brand>';
				echo '<g:condition>new</g:condition>';
				echo '<g:shipping>';
				echo '<g:country>DE</g:country>';
				echo '<g:price>29.00 EUR</g:price>';
				echo '</g:shipping>';
				echo '</item>';
			}
		}

		// Close the XML tags
		echo '</channel>';
		echo '</rss>';
	}
}
