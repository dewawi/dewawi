<?php

class Shops_PageController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$id = (int)$this->_getParam('id', 0);

		$toolbar = new Items_Form_Toolbar();

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$pageDb = new Shops_Model_DbTable_Page();
		$page = $pageDb->getPage($id);

		if (!$page) {
			throw new Zend_Controller_Action_Exception('Page not found', 404);
		}

		$pageblockDb = new Application_Model_DbTable_Pageblock();
		$pageblockDb->setClientId($this->_siteContext->getClientId());
		$pageblocks = $pageblockDb->getBlocksByPageId((int)$page['id'], true, 0);

		$imageDb = new Shops_Model_DbTable_Media();

		$this->view->page = $page;
		$this->view->pageblocks = $pageblocks;
		$this->view->images = [
			'categories' => $imageDb->getCategoryMedia($this->view->categories),
		];
		$this->view->toolbar = $toolbar;

		$this->assignMessages();
	}
}
