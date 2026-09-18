<?php

class Shops_CategoryController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$shop = $this->_site;
		$id = (int)$this->_getParam('id', 0);
		$categories = $this->view->categories;

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$categoryDb = new Shops_Model_DbTable_Category();
		$category = $categoryDb->getCategory($id);

		$taxratesDb = new Shops_Model_DbTable_Taxrate();
		$taxrates = $taxratesDb->getTaxRates();

		$get = new Shops_Model_Get();

		$tagEntites = [];
		foreach ($categories as $categoryRow) {
			$tagEntites[$categoryRow['id']] = $get->tags('shops', 'category', $categoryRow['id']);
		}

		$toolbar = new Items_Form_Toolbar();
		$params = $this->_helper->Params->getParams($toolbar);
		$params['catid'] = $category['id'];

		list($items, $records) = $get->items($params, (int)$shop['id']);

		$prices = [];
		foreach ($items as $item) {
			$currency = $this->_helper->Currency->getCurrency($item['currency'], 'USE_SYMBOL');
			$taxRate = $taxrates[$item['taxid']] ?? 0;
			$priceWithTax = $item['price'] * ((100 + $taxRate) / 100);
			$specialPriceWithTax = $item['specialprice'] * ((100 + $taxRate) / 100);

			$prices[$item->id] = [
				'raw' => $item['price'],
				'rawtax' => $priceWithTax,
				'rawspecialprice' => $item['specialprice'],
				'formatted' => $currency->toCurrency($item['price']),
				'formattedtax' => $currency->toCurrency($priceWithTax),
				'formattedspecialprice' => $currency->toCurrency($item['specialprice']),
				'formattedspecialpricetax' => $currency->toCurrency($specialPriceWithTax),
			];
		}

		$imageDb = new Shops_Model_DbTable_Media();
		$images = [
			'items' => $imageDb->getItemMedia($items),
			'categories' => $imageDb->getCategoryMedia($categories),
		];

		$manufacturersDb = new Shops_Model_DbTable_Manufacturer();
		$manufacturers = $manufacturersDb->getManufacturers();

		$clientid = (string)$shop['clientid'];
		$dir1 = substr($clientid, 0, 1);
		$dir2 = strlen($clientid) > 1 ? substr($clientid, 1, 1) : '0';

		$attributeSets = [];
		$optionSets = [];

		foreach ($items as $item) {
			$attributeSets[$item->id] = $this->_helper->Attributes->getAttributes($item->id);
			$optionSets[$item->id] = $this->_helper->Options->getOptions($item->id);
		}

		$this->view->category = $category;
		$this->view->items = $items;
		$this->view->tagEntites = $tagEntites;
		$this->view->manufacturers = $manufacturers;
		$this->view->prices = $prices;
		$this->view->images = $images;
		$this->view->imagePath = $dir1 . '/' . $dir2 . '/' . $clientid;
		$this->view->attributeSets = $attributeSets;
		$this->view->optionSets = $optionSets;

		$this->assignMessages();
	}
}
