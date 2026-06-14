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
}
