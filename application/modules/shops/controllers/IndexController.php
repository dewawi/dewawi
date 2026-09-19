<?php

class Shops_IndexController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$pageDb = new Shops_Model_DbTable_Page();
		$page = $pageDb->getPageByType('home');

		$pageblocks = [];

		if ($page) {
			$pageblockDb = new Application_Model_DbTable_Pageblock();
			$pageblockDb->setClientId($this->_siteContext->getClientId());
			$pageblocks = $pageblockDb->getBlocksByPageId((int)$page['id'], true, 0);
		}

		$slide = null;
		$slideImages = [];

		if (!$pageblocks) {
			$slideDb = new Shops_Model_DbTable_Slide();
			$slide = $slideDb->getByPosition('home');

			if ($slide) {
				$mediaDb = new Shops_Model_DbTable_Media();
				$slideImages = $mediaDb->getSlideImages((int)$slide['id']);
			}
		}

		$images = ['categories' => []];

		if ($this->_siteContext->hasFeature('catalog')) {
			$imageDb = new Shops_Model_DbTable_Media();
			$images['categories'] = $imageDb->getCategoryMedia($this->view->categories);
		}

		$this->view->page = $page;
		$this->view->pageblocks = $pageblocks;
		$this->view->slide = $slide;
		$this->view->slideImages = $slideImages;
		$this->view->images = $images;

		$this->assignMessages();
	}
}
