<?php

class Shops_CategoryController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$shop = $this->_site;
		$id = (int)$this->_getParam('id', 0);
		$categories = $this->view->categories;

		$categoryDb = new Shops_Model_DbTable_Category();
		$category = $categoryDb->getCategory($id);

		if (!$category) {
			$this->renderSiteError(404);
			return;
		}

		$path = (new Shops_Model_DbTable_Slug())->getPublicPath('category', (int)$category['id']);

		$taxratesDb = new Shops_Model_DbTable_Taxrate();
		$taxrates = $taxratesDb->getTaxRates();

		$tagEntityDb = new Shops_Model_DbTable_Tagentity();
		$tagEntities = $tagEntityDb->getByEntityId((int)$category['id'], 'shops', 'category');

		$toolbar = new Items_Form_Toolbar();
		$params = $this->_helper->Params->getParams($toolbar);
		$params['order'] = in_array(($params['order'] ?? ''), ['modified', 'created', 'title'], true) ? $params['order'] : 'modified';
		$params['sort'] = in_array(strtoupper((string)($params['sort'] ?? '')), ['ASC', 'DESC'], true) ? strtoupper((string)$params['sort']) : 'DESC';

		$itemDb = new Shops_Model_DbTable_Item();
		$totalItems = $itemDb->countItemsByCategory((int)$category['id']);
		$pages = Shops_Model_DbTable_Item::getPageCount($totalItems);
		$page = Shops_Model_DbTable_Item::normalizePage($this->_getParam('page', 1), $totalItems);
		$params['limit'] = Shops_Model_DbTable_Item::PER_PAGE;
		$params['offset'] = ($page - 1) * Shops_Model_DbTable_Item::PER_PAGE;
		$items = $itemDb->getItemsByCategory((int)$category['id'], $params);

		if ($path) $this->setCanonicalPath($path . ($page > 1 ? '?page=' . $page : ''));

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
		$this->view->tagEntities = $tagEntities;
		$this->view->prices = $prices;
		$this->view->images = $images;
		$this->view->imagePath = $dir1 . '/' . $dir2 . '/' . $clientid;
		$this->view->attributeSets = $attributeSets;
		$this->view->optionSets = $optionSets;
		$this->view->categoryPagination = [
			'page' => $page,
			'pages' => $pages,
			'total' => $totalItems,
			'perPage' => Shops_Model_DbTable_Item::PER_PAGE,
			'order' => (string)($params['order'] ?? 'modified'),
			'sort' => strtoupper((string)($params['sort'] ?? 'DESC')),
		];

		$this->assignMessages();
	}
}
