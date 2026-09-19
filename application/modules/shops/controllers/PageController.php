<?php

class Shops_PageController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();

		$id = (int)$this->_getParam('id', 0);

		$pageDb = new Shops_Model_DbTable_Page();
		$page = $pageDb->getPage($id);

		if (!$page) {
			throw new Zend_Controller_Action_Exception('Page not found', 404);
		}

		$pageblockDb = new Application_Model_DbTable_Pageblock();
		$pageblockDb->setClientId($this->_siteContext->getClientId());

		$this->view->page = $page;
		$this->view->pageblocks = $pageblockDb->getBlocksByPageId((int)$page['id'], true, 0);

		$this->assignMessages();
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (!array_intersect_key($values, array_flip(['slug', 'parentid', 'shopid']))) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slugDb->saveSlug(
			'shops',
			'page',
			(int)($values['shopid'] ?? $oldRow['shopid']),
			(int)($values['parentid'] ?? $oldRow['parentid']),
			$id,
			array_key_exists('slug', $values) ? (string)$values['slug'] : null
		);
	}
}
