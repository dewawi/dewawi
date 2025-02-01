<?php

class Shops_TagController extends Zend_Controller_Action
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

		$tagDb = new Shops_Model_DbTable_Tag();
		$tag = $tagDb->getTag($id);
		//print_r($tag);

		//$tagsDb = new Shops_Model_DbTable_Tag();
		//$tags = $tagsDb->getTags('shops', 'tag', $tag['id']);
		//print_r($tags);
//echo $tag->id;
		$tagsEntityDb = new Shops_Model_DbTable_Tagentity();
		$tagEntities = $tagsEntityDb->getTagEntities('shops', 'category', $tag->id);
		//print_r($tagEntities);

		$get = new Shops_Model_Get();
		//$tags = $get->tags('shops', 'tag', $tag['id']);
		//print_r($tags);

		$tagEntites = array();
		foreach($categories as $tagy) {
			$tagEntites[$tagy['id']] = $get->tags('shops', 'tag', $tagy['id']);
		}

		$params['catid'] = $tag['id'];
		//list($items, $records) = $get->items($params, $shop['id']);
		//print_r($tag);

		$images = array();
		$imageDb = new Shops_Model_DbTable_Media();
		//$images['items'] = $imageDb->getItemMedia($items);
		$images['categories'] = $imageDb->getCategoryMedia($categories);
		//print_r($images);

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus($shop['id']);

		$menuitems = array();
		$menuitemDb = new Shops_Model_DbTable_Menuitem();
		foreach($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems($menu->id);
		}

		//$this->view->tags = $tags;
		$this->view->tagEntities = $tagEntities;
		$this->view->shop = $shop;
		//$this->view->items = $items;
		$this->view->images = $images;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
		//$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->tag = $tag;
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
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Shops_Form_Category();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$tagEntityDb = new Application_Model_DbTable_Tagentity();
				$tagEntityDataBefore = $tagEntityDb->getTagEntities('shops', 'category', $data['parentid']);
				$latestOrdering = is_array($tagEntityDataBefore) && !empty($tagEntityDataBefore)
					? end($tagEntityDataBefore)['ordering']
					: 0;
				if(isset($data['tagid']) && $data['tagid']) {
					header('Content-type: application/json');
					$existingTags = array();
					foreach($tagEntityDataBefore as $tagEntity) {
						$existingTags[$tagEntity['tagid']] = $tagEntity['tagid'];
					}
					if(array_search($data['tagid'], $existingTags) !== false) {
						echo Zend_Json::encode(array('message' => $this->view->translate('TAG_ALREADY_EXISTS')));
					} else {
						$tagEntityDb->addTagEntity(array('tagid' => $data['tagid'], 'entityid' => $data['parentid'], 'module' => 'shops', 'controller' => 'category', 'ordering' => $latestOrdering+1));
						$tagEntityDataAfter = $tagEntityDb->getTagEntities('shops', 'category', $data['parentid']);
						$tagEntity = end($tagEntityDataAfter);
						echo Zend_Json::encode($tagEntity);
					}
				} else {
					$tagEntityDb->addTagEntity(array('tagid' => 0, 'entityid' => $data['parentid'], 'module' => 'shops', 'controller' => 'category', 'ordering' => $latestOrdering+1));
					$tagEntityDataAfter = $tagEntityDb->getTagEntities('shops', 'category', $data['parentid']);
					$tagEntity = end($tagEntityDataAfter);
					echo $this->view->MultiForm('shops', 'tag', $tagEntity);
				}
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Shops_Form_Category();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$tagDb = new Application_Model_DbTable_Tag();
				$tags = $tagDb->getTags('shops', 'category');

				$key = array_search($data['tag'], $tags);
				if(false !== $key) {
					$data['tagid'] = $key;
				} else {
					$data['tagid'] = $tagDb->addTag(array('title' => $data['tag'], 'module' => 'shops', 'controller' => 'category', 'shopid' => '100'));
				}
				unset($data['tag']);

				$tagEntityDb = new Application_Model_DbTable_Tagentity();
				if($id > 0) {
					$tagEntityDb->updateTagEntity($id, $data);
					echo Zend_Json::encode($data);
				}
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}

		$this->view->form = $form;
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

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$tagEntityDb = new Application_Model_DbTable_Tagentity();
			$tagEntityDb->deleteTagEntity($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
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
