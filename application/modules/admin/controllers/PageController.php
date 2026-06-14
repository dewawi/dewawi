<?php

class Admin_PageController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'pages',
			'list' => 'Admin_Model_List_Pages',
			'entity' => Admin_Model_Entity_Page::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('ADMIN_NEW_PAGE'),
			'shopid' => (int)$this->_getParam('shopid', 0),
			'parentid' => (int)$this->_getParam('parentid', 0),
			'type' => (string)$this->_getParam('type', ''),
		];
	}

	protected function beforeCreate(array $data): array
	{
		$data['shopid'] = (int)($data['shopid'] ?? 0);
		$data['parentid'] = (int)($data['parentid'] ?? 0);
		$data['type'] = (string)($data['type'] ?? '');

		$db = new Admin_Model_DbTable_Page();

		$data['ordering'] = $db->getNextOrdering([
			'parentid' => $data['parentid'],
			'type' => $data['type'],
			'shopid' => $data['shopid'],
		]);

		return $data;
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
