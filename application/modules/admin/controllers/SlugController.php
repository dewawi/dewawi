<?php

class Admin_SlugController extends DEEC_Controller_AdminAction
{
	public function indexAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$slugTable = new Zend_Db_Table('slug');

		// Fetch menu items and categories for the current shop
		$menuTable = new Zend_Db_Table('menu');
		$menuItemTable = new Zend_Db_Table('menuitem');

		// Get all menus for the current shop
		$menus = $menuTable->fetchAll(['shopid = ?' => 120]);
		$menuIds = array();
		foreach ($menus as $menu) {
			$menuIds[] = $menu['id']; // Collect menu IDs into an array
		}

		// Fetch menu items only if there are menu IDs
		if (!empty($menuIds)) {
			$menuItems = $menuItemTable->fetchAll(
				$menuItemTable->select()->where('menuid IN (?)', $menuIds)
			);
		} else {
			$menuItems = []; // No menu items if no menu IDs are present
		}
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
}
