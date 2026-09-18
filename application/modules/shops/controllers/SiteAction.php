<?php

abstract class Shops_Controller_SiteAction extends Zend_Controller_Action
{
	protected $_date = null;
	protected $_flashMessenger = null;
	protected $_siteContext = null;
	protected $_shop = [];
	protected $cart = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		$this->_siteContext = Zend_Registry::get('SiteContext');
		$this->_shop = $this->_siteContext->getSite();

		$this->view->id = isset($params['id']) ? (int)$params['id'] : 0;
		$this->view->action = $params['action'] ?? '';
		$this->view->controller = $params['controller'] ?? '';
		$this->view->module = $params['module'] ?? '';
		$this->view->siteContext = $this->_siteContext;
		$this->view->shop = $this->_shop;
	}

	protected function initSiteCart(): Shops_Model_ShoppingCart
	{
		if (!$this->cart) {
			$this->cart = new Shops_Model_ShoppingCart();
			$this->view->cart = $this->cart;
		}

		return $this->cart;
	}

	protected function initSiteLayout(): void
	{
		$this->_helper->getHelper('layout')->setLayout('site');
		$this->initSiteCart();

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus((int)$this->_shop['id']);

		$menuitems = [];
		$menuitemDb = new Shops_Model_DbTable_Menuitem();

		foreach ($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems((int)$menu->id);
		}

		$this->view->categories = $categories;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
	}

	protected function assignSiteMessages(): void
	{
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}
