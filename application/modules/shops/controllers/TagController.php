<?php

class Shops_TagController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$id = (int)$this->_getParam('id', 0);
		$categories = $this->view->categories;

		$tagDb = new Shops_Model_DbTable_Tag();
		$tag = $tagDb->getTag($id);

		if (!$tag) {
			throw new Zend_Controller_Action_Exception('Tag not found', 404);
		}

		$tagEntityDb = new Shops_Model_DbTable_Tagentity();
		$tagEntities = $tagEntityDb->getByTagId($id, 'shops', 'category');

		$imageDb = new Shops_Model_DbTable_Media();

		$this->view->tag = $tag;
		$this->view->tagEntities = $tagEntities;
		$this->view->images = [
			'categories' => $imageDb->getCategoryMedia($categories),
		];

		$this->assignMessages();
	}
}
