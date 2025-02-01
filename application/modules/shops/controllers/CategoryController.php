<?php

class Shops_CategoryController extends Zend_Controller_Action
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
		//$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar);

		/*$tagEntites = array();
		foreach($items as $item) {
			$tagEntites[$item->id] = $get->tags('items', 'item', $item->id);
		}*/

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();
		$category = $categoryDb->getCategory($id);

		//Get tax rates
		$taxratesDb = new Shops_Model_DbTable_Taxrate();
		$taxrates = $taxratesDb->getTaxRates();

		//$tagsDb = new Shops_Model_DbTable_Tag();
		//$tags = $tagsDb->getTags('shops', 'category', $category['id']);
		//print_r($tags);

		//$tagsEntityDb = new Shops_Model_DbTable_Tagentity();
		//$tagEntities = $tagsEntityDb->getTagEntities('shops', 'category', $category['id']);
		//print_r($tagEntities);

		$get = new Shops_Model_Get();
		//$tags = $get->tags('shops', 'category', $category['id']);
		//print_r($tags);

		//if($category['parentid'] != 0) {
			$tagEntites = array();
			foreach($categories as $categoryy) {
				$tagEntites[$categoryy['id']] = $get->tags('shops', 'category', $categoryy['id']);
			}
			$this->view->tagEntites = $tagEntites;
		//}

		$params['catid'] = $category['id'];
		list($items, $records) = $get->items($params, $shop['id']);

		$prices = [];
		foreach($items as $item) {
			//Get currency
			$currency = $this->_helper->Currency->getCurrency($item['currency'], 'USE_SYMBOL');

			// Ensure tax ID exists
			$taxRate = isset($taxrates[$item['taxid']]) ? $taxrates[$item['taxid']] : 0;

			// Calculate tax-inclusive price
			$priceWithTax = $item['price'] * ((100 + $taxRate) / 100);

			//Convert numbers to the display format
			$prices[$item->id]['raw'] = $item['price'];
			$prices[$item->id]['rawtax'] = $priceWithTax;
			$prices[$item->id]['formatted'] = $currency->toCurrency($item['price']);
			$prices[$item->id]['formattedtax'] = $currency->toCurrency($priceWithTax);
		}

		$images = array();
		$imageDb = new Shops_Model_DbTable_Media();
		$images['items'] = $imageDb->getItemMedia($items);
		$images['categories'] = $imageDb->getCategoryMedia($categories);
		//print_r($images);

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus($shop['id']);

		$menuitems = array();
		$menuitemDb = new Shops_Model_DbTable_Menuitem();
		foreach($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems($menu->id);
		}

		$manufacturersDb = new Shops_Model_DbTable_Manufacturer();
		$manufacturers = $manufacturersDb->getManufacturers();

		//Get image path
		$clientid = $shop['clientid'];
		$dir1 = substr($clientid, 0, 1);
		if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
		else $dir2 = '0';
		$imagePath = $dir1.'/'.$dir2.'/'.$clientid;

		//$this->view->tags = $tags;
		$this->view->shop = $shop;
		$this->view->items = $items;
		$this->view->manufacturers = $manufacturers;
		$this->view->prices = $prices;
		$this->view->images = $images;
		$this->view->imagePath = $imagePath;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
		//$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->category = $category;
		$this->view->categories = $categories;

		$attributeSets = array();
		$optionSets = array();
		if(isset($items)) {
			foreach($items as $item) {
				$attributeSets[$item->id] = $this->_helper->Attributes->getAttributes($item->id);
				$optionSets[$item->id] = $this->_helper->Options->getOptions($item->id);
			}
		}
		$this->view->attributeSets = $attributeSets;
		$this->view->optionSets = $optionSets;
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
		//$options = $this->_helper->Options->getOptions($toolbar);
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
		//$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
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
