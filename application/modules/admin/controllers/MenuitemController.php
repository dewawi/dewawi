<?php

class Admin_MenuitemController extends Zend_Controller_Action
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
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function getAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$element = $this->_getParam('element', null);
		$form = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($form);
		if(isset($form->$element)) {
			$options = $form->$element->getMultiOptions();
			echo Zend_Json::encode($options);
		} else {
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS')));
		}
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Menuitem();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$menuItemsDb = new Admin_Model_DbTable_Menuitem();

		if($params['type'] == 'shop') {
			$menuItems = $menuItemsDb->getMenuitems($params['type'], null, $params['shopid']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($menuItems));
		} else {
			$menuItems = $menuItemsDb->getMenuitems($params['type']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($menuItems));
		}

		if($params['type'] == 'shop') {
			$slugs = array();
			$slugDb = new Admin_Model_DbTable_Slug();
			$menuDb = new Admin_Model_DbTable_Menu();
			foreach($menuItems as $menuItem) {
				$menu = $menuDb->getMenu($menuItem['menuid']);
				$slug = $slugDb->getSlug('shops', 'page', 120, $menuItem['pageid']);
				$slugs[$menuItem['id']] = $slug['slug'];
			}
		}

		$this->view->form = $form;
		$this->view->menuItems = $menuItems;
		$this->view->slugs = $slugs;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Menuitem();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$menuItemsDb = new Admin_Model_DbTable_Menuitem();

		if($params['type'] == 'shop') {
			$menuItems = $menuItemsDb->getMenuitems($params['type'], null, $params['shopid']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($menuItems));
		} else {
			$menuItems = $menuItemsDb->getMenuitems($params['type']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($menuItems));
		}

		$slugs = array();
		$slugDb = new Admin_Model_DbTable_Slug();
		$menuDb = new Admin_Model_DbTable_Menu();
		foreach($menuItems as $menuItem) {
			$menu = $menuDb->getMenu($menuItem['menuid']);
			$slug = $slugDb->getSlug('shops', 'page', 120, $menuItem['pageid']);
			$slugs[$menuItem['id']] = $slug['slug'];
		}

		$this->view->form = $form;
		$this->view->menuItems = $menuItems;
		$this->view->slugs = $slugs;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Menuitem();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			//if($form->isValid($data)) {
				$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
				if(!isset($data['shopid'])) $data['shopid'] = 0;
				//$data['parentid'] = $params['parentid'];

				$menuItemDb = new Admin_Model_DbTable_Menuitem();
				$id = $menuItemDb->addMenuitem($data);

				if($data['shopid']) {
					$slugDb = new Admin_Model_DbTable_Slug();
					$slugDb->addSlug('shops', 'page', $data['shopid'], $data['parentid'], $id, $id);
				}

				//echo Zend_Json::encode($data);
				echo Zend_Json::encode($menuItemDb->getMenuitem($id));
			//} else {
			//	echo Zend_Json::encode($data);
				//echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			//}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$menuItemDb = new Admin_Model_DbTable_Menuitem();
		$menuItem = $menuItemDb->getMenuitem($id);

		$menuDb = new Admin_Model_DbTable_Menu();
		$menu = $menuDb->getMenu($menuItem['menuid']);

		if($this->isLocked($menuItem['locked'], $menuItem['lockedtime'])) {
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_LOCKED')));
			} else {
				$this->_flashMessenger->addMessage('MESSAGES_LOCKED');
				$this->_helper->redirector('index');
			}
		} else {
			$menuItemDb->lock($id);

			$form = new Admin_Form_Menuitem();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$menuItemsDb = new Admin_Model_DbTable_Menuitem();
			$menuItems = $menuItemsDb->getMenuitems($params['type']);
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				//if(isset($form->$element) && $form->isValidPartial($data)) { // to do add options for parentid before form validation
				if(true) {
					$menuItemDb = new Admin_Model_DbTable_Menuitem();
					if($element == 'parentid') {
						$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
						$menuItemArray = $menuItemDb->getMenuitem($id);
						$menuItemDb->updateMenuitem($id, $data);

						//sort old parent menuItem
						$this->setOrdering($menuItemArray['clientid'], $menuItemArray['type'], $menuItemArray['parentid']);

						$slugDb = new Admin_Model_DbTable_Slug();
						$slugDb->updateSlug('shops', 'page', $menuItemArray['shopid'], $data['parentid'], $id);

						/*$menuItems = $this->_helper->Menuitems->getMenuitems(null, $params['clientid'], $params['type'], $menuItemArray['parentid']);
						$i = 1;
						foreach($menuItems as $menuItem) {
							if(isset($menuItem['id'])) {
								//if($menuItem['ordering'] != $i)
								$menuItemDb->updateMenuitem($menuItem['id'], array('ordering' => $i));
								++$i;
							}index/type/shop
						}*/
						echo Zend_Json::encode($menuItemDb->getMenuitem($id));
					} elseif($element == 'slug') {
						$slugDb = new Admin_Model_DbTable_Slug();
						$slugDb->updateSlug('shops', 'page', $menuItem['shopid'], $menuItem['parentid'], $id, $data['slug']);
						echo Zend_Json::encode(array('slug' => $data['slug']));
					} else {
						$menuItemDb->updateMenuitem($id, $data);
						echo Zend_Json::encode($menuItemDb->getMenuitem($id));
					}
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$form->populate($menuItem);

					//Toolbar
					$toolbar = new Admin_Form_Toolbar();

					//Tags
					$get = new Shops_Model_Get();
					$tags = $get->tags('shops', 'menuItem', $menuItem['id']);

					//Get slug
					$slugDb = new Admin_Model_DbTable_Slug();
					$slug = $slugDb->getSlug('shops', 'page', $menuItem['shopid'], $id);
					$form->slug->setValue($slug['slug']);

					$this->view->form = $form;
					$this->view->tags = $tags;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$menuItemDb = new Admin_Model_DbTable_Menuitem();
		$data = $menuItemDb->getMenuitem($id);
		unset($data['id']);

		$menuItemsDb = new Admin_Model_DbTable_Menuitem();
		$menuItems = $menuItemsDb->getMenuitems($data['type'], $data['parentid']);
		foreach($menuItems as $menuItem) {
			if(isset($menuItem['ordering'])) {
				if($menuItem['ordering'] > $data['ordering']) {
					if(!isset($menuItemsDb)) $menuItemsDb = new Admin_Model_DbTable_Menuitem();
					$menuItemsDb->sortMenuitem($menuItem['id'], $menuItem['ordering'] + 1);
				}
			}
		}

		$data['title'] = $data['title'].' 2';
		$data['ordering'] = $data['ordering'] + 1;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$newId = $menuItemDb->addMenuitem($data);
		//print_r($data);

		if($data['shopid']) {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slugDb->addSlug('shops', 'page', $data['shopid'], $data['parentid'], $newId, $newId);
		}

		$childMenuitems = $menuItemsDb->getMenuitems($data['type'], $id);
		if(isset($childMenuitems[$id]['childs'])) $this->copyChilds($id, $childMenuitems, $newId);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');


	}

	public function sortAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$data = $request->getPost();
			$menuItemDb = new Admin_Model_DbTable_Menuitem();
			$menuItem = $menuItemDb->getMenuitem($data['id']);
			$orderings = $this->getOrdering($menuItem['clientid'], $menuItem['type'], $menuItem['parentid']);
			$currentOrdering = array_search($data['id'], $orderings);
			if(($data['ordering'] == 'down') && (isset($orderings[$currentOrdering+1]))) {
				$menuItemDb->sortMenuitem($data['id'], $currentOrdering+1);
				$menuItemDb->sortMenuitem($orderings[$currentOrdering+1], $currentOrdering);
			} elseif(($data['ordering'] == 'up') && (isset($orderings[$currentOrdering-1]))) {
				$menuItemDb->sortMenuitem($data['id'], $currentOrdering-1);
				$menuItemDb->sortMenuitem($orderings[$currentOrdering-1], $currentOrdering);
			} elseif($data['ordering'] > 0) {
				if($data['ordering'] < $currentOrdering) {
					$menuItemDb->sortMenuitem($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering < $currentOrdering) && ($ordering >= $data['ordering'])) $menuItemDb->sortMenuitem($id, $ordering+1);
					}
				} elseif($data['ordering'] > $currentOrdering) {
					$menuItemDb->sortMenuitem($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering > $currentOrdering) && ($ordering <= $data['ordering'])) $menuItemDb->sortMenuitem($id, $ordering-1);
					}
				}
			}
			$this->setOrdering($menuItem['clientid'], $menuItem['type'], $menuItem['parentid']);
		}
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$menuItemDb = new Admin_Model_DbTable_Menuitem();
			$menuItem = $menuItemDb->getMenuitem($id);

			if($menuItem['type'] == 'contact') {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contacts = $contactDb->getContactsByMenuitem($id);
				if(!empty($contacts)) {
					//Do not delete the menuItem if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$menuItemsDb = new Admin_Model_DbTable_Menuitem();
					$menuItems = $menuItemsDb->getMenuitems($menuItem['type'], $menuItem['id']);
					if(!empty($menuItems)) {
						//Do not delete the menuItem if it has child menuItems
						$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$menuItemDb->deleteMenuitem($id);
						$this->setOrdering($menuItem['clientid'], $menuItem['type'], $menuItem['parentid']);
						$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
					}
				}
			} elseif($menuItem['type'] == 'item') {
				$itemDb = new Items_Model_DbTable_Item();
				$items = $itemDb->getItemsByMenuitem($id);
				if(!empty($items)) {
					//Do not delete the menuItem if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$menuItemsDb = new Admin_Model_DbTable_Menuitem();
					$menuItems = $menuItemsDb->getMenuitems($menuItem['type'], $menuItem['id']);
					if(!empty($menuItems)) {
						//Do not delete the menuItem if it has child menuItems
						$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$menuItemDb->deleteMenuitem($id);
						$this->setOrdering($menuItem['clientid'], $menuItem['type'], $menuItem['parentid']);
						$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
					}
				}
			} elseif($menuItem['type'] == 'shop') {
				$itemDb = new Items_Model_DbTable_Item();
				$items = $itemDb->getItemsByMenuitem($id);
				if(!empty($items)) {
					//Do not delete the menuItem if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$menuItemsDb = new Admin_Model_DbTable_Menuitem();
					$menuItems = $menuItemsDb->getMenuitems($menuItem['type'], $menuItem['id']);
					if(!empty($menuItems)) {
						//Do not delete the menuItem if it has child menuItems
						$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$menuItemDb->deleteMenuitem($id);
						$this->setOrdering($menuItem['clientid'], $menuItem['type'], $menuItem['parentid']);

						if($category['shopid']) {
							$slugDb = new Admin_Model_DbTable_Slug();
							$slugDb->deleteSlug('shops', 'page', $menuItem['shopid'], $id);
						}
						$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
					}
				}
			}
		}
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$menuItemDb = new Admin_Model_DbTable_Menuitem();
		$menuItem = $menuItemDb->getMenuitem($id);
		if($this->isLocked($menuItem['locked'], $menuItem['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($menuItem['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$menuItemDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$menuItemDb = new Admin_Model_DbTable_Menuitem();
		$menuItemDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$menuItemDb = new Admin_Model_DbTable_Menuitem();
		$menuItemDb->lock($id);
	}


	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Menuitem();

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function isLocked($locked, $lockedtime)
	{
		if($locked && ($locked != $this->_user['id'])) {
			$timeout = strtotime($lockedtime) + 300; // 5 minutes
			$timestamp = strtotime($this->_date);
			if($timeout < $timestamp) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	protected function setOrdering($clientid, $type, $parentid)
	{
		$i = 1;
		$menuItemsDb = new Admin_Model_DbTable_Menuitem();
		$menuItems = $menuItemsDb->getMenuitems($type, $parentid);
		foreach($menuItems as $menuItem) {
			if(isset($menuItem['ordering'])) {
				//if($menuItem['ordering'] != $i) {
					if(!isset($menuItemsDb)) $menuItemsDb = new Admin_Model_DbTable_Menuitem();
					//print_r($menuItem);
					//print_r($i);
					$menuItemsDb->sortMenuitem($menuItem['id'], $i);
					++$i;
				//}
			}
		}
	}

	protected function getOrdering($clientid, $type, $parentid)
	{
		$i = 1;
		$menuItemsDb = new Admin_Model_DbTable_Menuitem();
		$menuItems = $menuItemsDb->getMenuitems($type, $parentid);
		$orderings = array();
		foreach($menuItems as $menuItem) {
			if(isset($menuItem['id'])) {
				$orderings[$i] = $menuItem['id'];
				++$i;
			}
		}
		return $orderings;
	}

	protected function getLatestOrdering($clientid, $type, $parentid)
	{
		$ordering = $this->getOrdering($clientid, $type, $parentid);
		end($ordering);
		return key($ordering);
	}

	protected function copyChilds($oldId, $menuItems, $newId)
	{
		foreach($menuItems[$oldId]['childs'] as $child) {
			$menuItemDb = new Admin_Model_DbTable_Menuitem();
			$data = $menuItemDb->getMenuitem($child);
			unset($data['id']);
			$data['parentid'] = $newId;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			$data['locked'] = 0;
			$data['lockedtime'] = NULL;
			$newChildId = $menuItemDb->addMenuitem($data);

			$childMenuitems = $menuItemDb->getMenuitems($data['type'], $child);
			if(isset($childMenuitems[$child]['childs'])) $this->copyChilds($child, $childMenuitems, $newChildId);
		}
	}
}
