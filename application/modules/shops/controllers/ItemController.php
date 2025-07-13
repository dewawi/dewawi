<?php

class Shops_ItemController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		//if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'item', $this->_flashMessenger);
		//if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'media', $this->_flashMessenger);

		$this->cart = new Shops_Model_ShoppingCart();

		// Make the cart accessible in all views
		$this->view->cart = $this->cart;
	}

	public function indexAction()
	{
		$shop = Zend_Registry::get('Shop');

		$id = $this->_getParam('id');

		$this->_helper->getHelper('layout')->setLayout('shop');

		$toolbar = new Items_Form_Toolbar();
		//list($options, $optionSets) = $this->_helper->Options->getOptions();
		//$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Shops_Model_Get();
		//$tags = $get->tags('items', 'item');
		//list($items, $records) = $get->items($params, $options);

		/*$tagEntites = array();
		foreach($items as $item) {
			$tagEntites[$item->id] = $get->tags('items', 'item', $item->id);
		}*/

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$itemDb = new Shops_Model_DbTable_Item();
		$item = $itemDb->getItem($id, $shop['id']);

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($item['currency'], 'USE_SYMBOL');

		// Calculate tax-inclusive price
		if($item['taxid']) {
			$taxrateDb = new Shops_Model_DbTable_Taxrate();
			$taxrate = $taxrateDb->getTaxRate($item['taxid']);
			$priceWithTax = $item['price'] * ((100 + $taxrate['rate']) / 100);
			$specialPriceWithTax = $item['specialprice'] * ((100 + $taxrate['rate']) / 100);
		} else {
			$priceWithTax = $item['price'];
			$specialPriceWithTax = $item['specialprice'];
		}

		//Convert numbers to the display format
		$prices = [];
		$prices['raw'] = $item['price'];
		$prices['rawtax'] = $priceWithTax;
		$prices['rawspecialprice'] = $item['specialprice'];
		$prices['formatted'] = $currency->toCurrency($item['price']);
		$prices['formattedtax'] = $currency->toCurrency($priceWithTax);
		$prices['formattedspecialprice'] = $currency->toCurrency($item['specialprice']);
		$prices['formattedspecialpricetax'] = $currency->toCurrency($specialPriceWithTax);

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();
		$category = $categoryDb->getCategory($item['shopcatid']);

		$mediaDb = new Shops_Model_DbTable_Media();
		$images = $mediaDb->getMedia($id, 'items', 'item');
		$categoryImages = $mediaDb->getCategoryMediaById($category->id);
		$parentCategoryImages = $mediaDb->getCategoryMediaById($category->parentid);

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus($shop['id']);

		$menuitems = array();
		$menuitemDb = new Shops_Model_DbTable_Menuitem();
		foreach($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems($menu->id);
		}

		$manufacturersDb = new Shops_Model_DbTable_Manufacturer();
		$manufacturers = $manufacturersDb->getManufacturers();

		//$this->view->tags = $tags;
		//$this->view->tagEntites = $tagEntites;
		$this->view->shop = $shop;
		$this->view->item = $item;
		$this->view->images = $images;
		$this->view->categoryImages = $categoryImages;
		$this->view->parentCategoryImages = $parentCategoryImages;
		$this->view->prices = $prices;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
		$this->view->manufacturers = $manufacturers;
		//$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->category = $category;
		$this->view->categories = $categories;
		$this->view->attributeSets = $this->_helper->Attributes->getAttributes($item['id']);
		$this->view->optionSets = $this->_helper->Options->getOptions($item['id']);
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Shops_Form_Account();
		$toolbar = new Shops_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Shops_Model_Get();
		$stats = array();
		$items = array();
		$accounts = $get->accounts($params, $options);
		foreach($accounts as $account) {
			$params['limit'] = 0;
			$params['shopid'] = $account['id'];
			list($items[$account['id']], $records) = $get->items($params, $options);
			$stats[$account['id']]['total'] = count($items[$account['id']]);
			$stats[$account['id']]['listed'] = 0;
			foreach($items[$account['id']] as $item) {
				if($item->listedby) ++$stats[$account['id']]['listed'];
			}
		}

		$this->view->form = $form;
		$this->view->stats = $stats;
		$this->view->accounts = $accounts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function feedAction()
	{
		$shop = Zend_Registry::get('Shop');

		// Disable the view renderer (we're outputting XML directly)
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout->disableLayout();

		// Set the content type to XML
		$this->getResponse()->setHeader('Content-Type', 'application/xml');

		// Initialize the base URL
		$baseUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost();

		// Begin XML output
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
		echo '<channel>';

		// Get shop details from the registry
		$shop = Zend_Registry::get('Shop');

		// Store information
		echo '<title>' . $shop['title'] . '</title>';
		echo '<link>' . $baseUrl . '/</link>';
		echo '<description>'.$shop['title'].'</description>';

		// Fetch slugs for the current shop
		$slugTable = new Zend_Db_Table('slug');
		$slugs = $slugTable->fetchAll(['shopid = ?' => $shop['id']]);

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
		$itemTable = new Zend_Db_Table('item');
		$items = $itemTable->fetchAll(['shopid = ?' => $shop['id']]);

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
				echo '<g:id>' . $item['sku'] . '</g:id>';
				echo '<g:title>' . $item['title'] . '</g:title>';
				echo '<g:description>' . htmlspecialchars($item['description']) . '</g:description>';
				echo '<g:link>' . htmlspecialchars($slugUrl) . '</g:link>';
				$i = 0;
				foreach ($images['items'][$item->id] as $image) {
					if ($i == 0) {
						echo '<g:image_link>'.$shop['url'].'/media/images/'.$image->url.'</g:image_link>';
					} else {
						echo '<g:additional_image_link>'.$shop['url'].'/media/images/'.$image->url.'</g:additional_image_link>';
					}
					$i++;
				}
				echo '<g:availability>in_stock</g:availability>';
				echo '<g:quantity>10</g:quantity>';
				echo '<g:price>' . $priceWithTax . ' ' . $item['currency'] . '</g:price>';
				echo '<g:brand>'.$shop['title'].'</g:brand>';
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

	public function syncAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$shopid = $this->_getParam('shopid', 0);

		if($shopid) {
			$accountDb = new Shops_Model_DbTable_Account();
			$account = $accountDb->getAccount($shopid);

			if($account) {
				$config = parse_ini_file(BASE_PATH.'/configs/database.ini');

				// DB Settings
				define('DB_SERVER', $config['resources.db.params.host']);
				define('DB_USER', $config['resources.db.params.username']);
				define('DB_PASSWORD', $config['resources.db.params.password']);
				define('DB_NAME', $config['resources.db.params.dbname']);

				require_once(BASE_PATH.'/library/DEEC/Shop.php');
				$Shops = new DEEC_Shop(BASE_PATH, DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
				$Shops->listItems($shopid);

				$accountDb->updateAccount($shopid, array('updated' => date('Y-m-d H:i:s'), 'updatedby' => $this->_user['id']));

				$this->_flashMessenger->addMessage('MESSAGES_RECORDS_SUCCESFULLY_UPDATED');
			}
		}

		$this->_helper->redirector->gotoSimple('index', 'index');
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Shops_Form_Account();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			if($form->isValid($data)) {
				$accountDb = new Shops_Model_DbTable_Account();
				$id = $accountDb->addAccount($data);
				echo Zend_Json::encode($accountDb->getAccount($id));
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$accountDb = new Shops_Model_DbTable_Account();
		$account = $accountDb->getAccount($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'account', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $account['locked'], $account['lockedtime']);

			$form = new Shops_Form_Account();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$accountDb->updateAccount($id, $data);
					echo Zend_Json::encode($accountDb->getAccount($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$form->populate($account);

					//Toolbar
					$toolbar = new Shops_Form_Toolbar();

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
				}
			}
		}
		$this->view->messages = array_merge(
			$this->_helper->flashMessenger->getMessages(),
			$this->_helper->flashMessenger->getCurrentMessages()
		);
		$this->_helper->flashMessenger->clearCurrentMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$item = new Shops_Model_DbTable_Item();
		$data = $item->getItem($id);
		unset($data['id']);
		$data['quantity'] = 0;
		$data['inventory'] = 1;
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		echo $itemid = $item->addItem($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$item = new Shops_Model_DbTable_Item();
			$item->deleteItem($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function lockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->lock($id, $this->_user['id']);
	}

	public function unlockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->keepalive($id);
	}

	public function validateAction()
	{
		$this->_helper->Validate();
	}
}
