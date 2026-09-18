<?php

class Shops_PageController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$shop = $this->_site;
		$id = (int)$this->_getParam('id', 0);

		$toolbar = new Items_Form_Toolbar();

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$pageDb = new Shops_Model_DbTable_Page();
		$page = $pageDb->getPage($id, (int)$shop['id']);

		$pageblocks = [];

		if ($page) {
			$pageblockDb = new Application_Model_DbTable_Pageblock();
			$pageblockDb->setClientId((int)$shop['clientid']);
			$pageblocks = $pageblockDb->getBlocksByPageId((int)$page['id'], true, 0);
		}

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
