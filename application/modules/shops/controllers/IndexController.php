<?php

class Shops_IndexController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$shop = $this->_site;

		$toolbar = new Shops_Form_Toolbar();

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$slideDb = new Shops_Model_DbTable_Slide();
		$slide = $slideDb->getByPosition('home', (int)$shop['id']);

		$slideImages = [];

		if ($slide) {
			$mediaDb = new Shops_Model_DbTable_Media();
			$slideImages = $mediaDb->getSlideImages((int)$slide['id']);
		}

		$this->view->slide = $slide;
		$this->view->slideImages = $slideImages;

		$imageDb = new Shops_Model_DbTable_Media();
		$this->view->images = [
			'categories' => $imageDb->getCategoryMedia($this->view->categories),
		];

		$pageDb = new Shops_Model_DbTable_Page();
		$page = $pageDb->getPageByType('home', (int)$shop['id']);

		$pageblocks = [];

		if ($page) {
			$pageblockDb = new Application_Model_DbTable_Pageblock();
			$pageblockDb->setClientId((int)$shop['clientid']);
			$pageblocks = $pageblockDb->getBlocksByPageId((int)$page['id'], true, 0);
		}

		$this->view->page = $page;
		$this->view->pageblocks = $pageblocks;
		$this->view->toolbar = $toolbar;

		$this->assignMessages();
	}
}
