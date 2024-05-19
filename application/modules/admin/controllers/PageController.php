<?php

class Admin_PageController extends Zend_Controller_Action
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

		$form = new Admin_Form_Page();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$pagesDb = new Admin_Model_DbTable_Page();

		if($params['type'] == 'shop') {
			$shopsDb = new Admin_Model_DbTable_Shop();
			$shops = $shopsDb->getShops();
			foreach($shops as $shop) {
				$toolbar->shopid->addMultiOption($shop->id, $shop->title);
			}
			$pages = $pagesDb->getPages($params['type'], null, $params['shopid']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($pages));
		} else {
			$pages = $pagesDb->getPages($params['type']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($pages));
		}

		$this->view->form = $form;
		$this->view->pages = $pages;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Page();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$pagesDb = new Admin_Model_DbTable_Page();

		if($params['type'] == 'shop') {
			$shopsDb = new Admin_Model_DbTable_Shop();
			$shops = $shopsDb->getShops();
			foreach($shops as $shop) {
				$toolbar->shopid->addMultiOption($shop->id, $shop->title);
			}
			$pages = $pagesDb->getPages($params['type'], null, $params['shopid']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($pages));
		} else {
			$pages = $pagesDb->getPages($params['type']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($pages));
		}

		$this->view->form = $form;
		$this->view->pages = $pages;
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
			$form = new Admin_Form_Page();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			//if($form->isValid($data)) {
				$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
				if(!isset($data['shopid'])) $data['shopid'] = 0;
				//$data['parentid'] = $params['parentid'];

				$pageDb = new Admin_Model_DbTable_Page();
				$id = $pageDb->addPage($data);
				//echo Zend_Json::encode($data);
				echo Zend_Json::encode($pageDb->getPage($id));
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

		$pageDb = new Admin_Model_DbTable_Page();
		$page = $pageDb->getPage($id);

		if($this->isLocked($page['locked'], $page['lockedtime'])) {
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
			$pageDb->lock($id);

			$form = new Admin_Form_Page();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$pagesDb = new Admin_Model_DbTable_Page();
			$pages = $pagesDb->getPages($params['type']);
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				//if(isset($form->$element) && $form->isValidPartial($data)) { // to do add options for parentid before form validation
				if(true) {
					$pageDb = new Admin_Model_DbTable_Page();
					if($element == 'parentid') {
						$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
						$pageArray = $pageDb->getPage($id);
						$pageDb->updatePage($id, $data);

						//sort old parent page
						$this->setOrdering($pageArray['clientid'], $pageArray['type'], $pageArray['parentid']);

						/*$pages = $this->_helper->Pages->getPages(null, $params['clientid'], $params['type'], $pageArray['parentid']);
						$i = 1;
						foreach($pages as $page) {
							if(isset($page['id'])) {
								//if($page['ordering'] != $i)
								$pageDb->updatePage($page['id'], array('ordering' => $i));
								++$i;
							}
						}*/
					} else {
						$pageDb->updatePage($id, $data);
					}
					echo Zend_Json::encode($pageDb->getPage($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$form->populate($page);

					//Toolbar
					$toolbar = new Admin_Form_Toolbar();

					//Tags
					$get = new Shops_Model_Get();
					$tags = $get->tags('shops', 'page', $page['id']);

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
		$pageDb = new Admin_Model_DbTable_Page();
		$data = $pageDb->getPage($id);
		unset($data['id']);

		$pagesDb = new Admin_Model_DbTable_Page();
		$pages = $pagesDb->getPages($data['type'], $data['parentid']);
		foreach($pages as $page) {
			if(isset($page['ordering'])) {
				if($page['ordering'] > $data['ordering']) {
					if(!isset($pagesDb)) $pagesDb = new Admin_Model_DbTable_Page();
					$pagesDb->sortPage($page['id'], $page['ordering'] + 1);
				}
			}
		}

		$data['title'] = $data['title'].' 2';
		$data['ordering'] = $data['ordering'] + 1;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$newId = $pageDb->addPage($data);
		//print_r($data);

		$childPages = $pagesDb->getPages($data['type'], $id);
		if(isset($childPages[$id]['childs'])) $this->copyChilds($id, $childPages, $newId);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');


	}

	public function sortAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$data = $request->getPost();
			$pageDb = new Admin_Model_DbTable_Page();
			$page = $pageDb->getPage($data['id']);
			$orderings = $this->getOrdering($page['clientid'], $page['type'], $page['parentid']);
			$currentOrdering = array_search($data['id'], $orderings);
			if(($data['ordering'] == 'down') && (isset($orderings[$currentOrdering+1]))) {
				$pageDb->sortPage($data['id'], $currentOrdering+1);
				$pageDb->sortPage($orderings[$currentOrdering+1], $currentOrdering);
			} elseif(($data['ordering'] == 'up') && (isset($orderings[$currentOrdering-1]))) {
				$pageDb->sortPage($data['id'], $currentOrdering-1);
				$pageDb->sortPage($orderings[$currentOrdering-1], $currentOrdering);
			} elseif($data['ordering'] > 0) {
				if($data['ordering'] < $currentOrdering) {
					$pageDb->sortPage($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering < $currentOrdering) && ($ordering >= $data['ordering'])) $pageDb->sortPage($id, $ordering+1);
					}
				} elseif($data['ordering'] > $currentOrdering) {
					$pageDb->sortPage($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering > $currentOrdering) && ($ordering <= $data['ordering'])) $pageDb->sortPage($id, $ordering-1);
					}
				}
			}
			$this->setOrdering($page['clientid'], $page['type'], $page['parentid']);
		}
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$pageDb = new Admin_Model_DbTable_Page();
			$page = $pageDb->getPage($id);

			if($page['type'] == 'contact') {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contacts = $contactDb->getContactsByPage($id);
				if(!empty($contacts)) {
					//Do not delete the page if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$pagesDb = new Admin_Model_DbTable_Page();
					$pages = $pagesDb->getPages($page['type'], $page['id']);
					if(!empty($pages)) {
						//Do not delete the page if it has child pages
						$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$pageDb->deletePage($id);
						$this->setOrdering($page['clientid'], $page['type'], $page['parentid']);
						$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
					}
				}
			} elseif($page['type'] == 'item') {
				$itemDb = new Items_Model_DbTable_Item();
				$items = $itemDb->getItemsByPage($id);
				if(!empty($items)) {
					//Do not delete the page if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$pagesDb = new Admin_Model_DbTable_Page();
					$pages = $pagesDb->getPages($page['type'], $page['id']);
					if(!empty($pages)) {
						//Do not delete the page if it has child pages
						$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$pageDb->deletePage($id);
						$this->setOrdering($page['clientid'], $page['type'], $page['parentid']);
						$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
					}
				}
			} elseif($page['type'] == 'shop') {
				$itemDb = new Items_Model_DbTable_Item();
				$items = $itemDb->getItemsByPage($id);
				if(!empty($items)) {
					//Do not delete the page if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$pagesDb = new Admin_Model_DbTable_Page();
					$pages = $pagesDb->getPages($page['type'], $page['id']);
					if(!empty($pages)) {
						//Do not delete the page if it has child pages
						$this->_flashMessenger->addMessage('MESSAGES_PAGE_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$pageDb->deletePage($id);
						$this->setOrdering($page['clientid'], $page['type'], $page['parentid']);
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
		$pageDb = new Admin_Model_DbTable_Page();
		$page = $pageDb->getPage($id);
		if($this->isLocked($page['locked'], $page['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($page['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$pageDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$pageDb = new Admin_Model_DbTable_Page();
		$pageDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$pageDb = new Admin_Model_DbTable_Page();
		$pageDb->lock($id);
	}


	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Page();

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
		$pagesDb = new Admin_Model_DbTable_Page();
		$pages = $pagesDb->getPages($type, $parentid);
		foreach($pages as $page) {
			if(isset($page['ordering'])) {
				//if($page['ordering'] != $i) {
					if(!isset($pagesDb)) $pagesDb = new Admin_Model_DbTable_Page();
					//print_r($page);
					//print_r($i);
					$pagesDb->sortPage($page['id'], $i);
					++$i;
				//}
			}
		}
	}

	protected function getOrdering($clientid, $type, $parentid)
	{
		$i = 1;
		$pagesDb = new Admin_Model_DbTable_Page();
		$pages = $pagesDb->getPages($type, $parentid);
		$orderings = array();
		foreach($pages as $page) {
			if(isset($page['id'])) {
				$orderings[$i] = $page['id'];
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

	protected function copyChilds($oldId, $pages, $newId)
	{
		foreach($pages[$oldId]['childs'] as $child) {
			$pageDb = new Admin_Model_DbTable_Page();
			$data = $pageDb->getPage($child);
			unset($data['id']);
			$data['parentid'] = $newId;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			$data['locked'] = 0;
			$data['lockedtime'] = NULL;
			$newChildId = $pageDb->addPage($data);

			$childPages = $pageDb->getPages($data['type'], $child);
			if(isset($childPages[$child]['childs'])) $this->copyChilds($child, $childPages, $newChildId);
		}
	}
}
