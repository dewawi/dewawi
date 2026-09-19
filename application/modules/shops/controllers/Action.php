<?php

abstract class Shops_Controller_Action extends DEEC_Controller_SiteAction
{
	protected $cart = null;

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

		$categories = [];
		if ($this->_siteContext->hasFeature('catalog')) {
			$categoryDb = new Shops_Model_DbTable_Category();
			$categories = $categoryDb->getCategories();
		}

		if ($this->_siteContext->hasFeature('cart')) {
			$this->initSiteCart();
		}

		$this->initSiteContactForm();

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus();

		$menuitems = [];
		$menuitemDb = new Shops_Model_DbTable_Menuitem();

		foreach ($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems((int)$menu->id);
		}

		$this->view->shop = $this->_site;
		$this->view->categories = $categories;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
	}

	protected function initSiteContactForm(): void
	{
		if ($this->_siteContext->hasFeature('contact') || $this->_siteContext->hasFeature('inquiry')) {
			$this->view->contact = new Shops_Form_Contact();
		}
	}
}
