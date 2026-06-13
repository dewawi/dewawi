<?php

class Admin_CategoryController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'categories',
			'list' => 'Admin_Model_List_Categories',
			'entity' => Admin_Model_Entity_Category::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$db = new Admin_Model_DbTable_Category();

		return [
			'parentid' => (int)$this->_getParam('parentid', 0),
			'shopid' => (int)$this->_getParam('shopid', 0),
			'type' => (string)$this->_getParam('type', ''),
			'ordering' => $db->getNextOrdering(),
		];
	}

	protected function beforeCreate(array $data): array
	{
		$data['type'] = (string)($data['type'] ?? '');
		$data['parentid'] = (int)($data['parentid'] ?? 0);
		$data['shopid'] = (int)($data['shopid'] ?? 0);

		$db = new Admin_Model_DbTable_Category();

		$data['ordering'] = $db->getNextOrdering([
			'parentid' => $data['parentid'],
			'type' => $data['type'],
		]);

		return $data;
	}

	protected function afterCreate(int $id, array $data): void
	{
		if (empty($data['shopid'])) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();
		$slugDb->addSlug(
			'shops',
			'category',
			(int)$data['shopid'],
			(int)$data['parentid'],
			$id,
			$id
		);
	}

	protected function getEntityContext(array $row): array
	{
		$module = 'admin';

		if (($row['type'] ?? '') === 'shop') {
			$module = 'shops';
		}

		if (($row['type'] ?? '') === 'item') {
			$module = 'items';
		}

		if (($row['type'] ?? '') === 'contact') {
			$module = 'contacts';
		}

		return [
			'module' => $module,
			'controller' => 'category',
		];
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		if (array_key_exists('parentid', $values) && (string)$values['parentid'] !== (string)$row['parentid']) {
			$values['ordering'] = $this->getLatestOrdering(
				Admin_Model_DbTable_Category::class,
				'getCategories',
				'sortCategory',
				[(string)($row['type'] ?? ''), (int)$values['parentid']]
			) + 1;
		}

		return $values;
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (array_key_exists('parentid', $values) && (string)$values['parentid'] !== (string)$oldRow['parentid']) {
			$this->resetOrdering(
				Admin_Model_DbTable_Category::class,
				'getCategories',
				'sortCategory',
				[(string)($oldRow['type'] ?? ''), (int)$oldRow['parentid']]
			);

			if (!empty($oldRow['shopid'])) {
				$slugDb = new Admin_Model_DbTable_Slug();
				$slugDb->updateSlug(
					'shops',
					'category',
					(int)$oldRow['shopid'],
					(int)$values['parentid'],
					$id
				);
			}
		}

		if (array_key_exists('slug', $values) && !empty($oldRow['shopid'])) {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slugDb->updateSlug(
				'shops',
				'category',
				(int)$oldRow['shopid'],
				(int)$oldRow['parentid'],
				$id,
				(string)$values['slug']
			);
		}
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$categoryDb = new Admin_Model_DbTable_Category();
		$data = $categoryDb->getCategory($id);
		unset($data['id']);

		$categoriesDb = new Admin_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories($data['type'], $data['parentid']);
		foreach($categories as $category) {
			if(isset($category['ordering'])) {
				if($category['ordering'] > $data['ordering']) {
					if(!isset($categoriesDb)) $categoriesDb = new Admin_Model_DbTable_Category();
					$categoriesDb->sortCategory($category['id'], $category['ordering'] + 1);
				}
			}
		}

		$data['title'] = $data['title'].' 2';
		$data['ordering'] = $data['ordering'] + 1;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$newId = $categoryDb->addCategory($data);
		//print_r($data);

		if($data['shopid']) {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slugDb->addSlug('shops', 'category', $data['shopid'], $data['parentid'], $newId, $newId);
		}

		$childCategories = $categoriesDb->getCategories($data['type'], $id);
		if(isset($childCategories[$id]['childs'])) $this->copyChilds($id, $childCategories, $newId);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$categoryDb = new Admin_Model_DbTable_Category();
			$category = $categoryDb->getCategory($id);

			if($category['type'] == 'contact') {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contacts = $contactDb->getContactsByCategory($id);
				if(!empty($contacts)) {
					//Do not delete the category if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_CATEGORY_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$categoriesDb = new Admin_Model_DbTable_Category();
					$categories = $categoriesDb->getCategories($category['type'], $category['id']);
					if(!empty($categories)) {
						//Do not delete the category if it has child categories
						$this->_flashMessenger->addMessage('MESSAGES_CATEGORY_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$categoryDb->deleteCategory($id);
						$this->setOrdering($category['clientid'], $category['type'], $category['parentid']);
						$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
					}
				}
			} elseif($category['type'] == 'item') {
				$itemDb = new Items_Model_DbTable_Item();
				$items = $itemDb->getItemsByCategory($id);
				if(!empty($items)) {
					//Do not delete the category if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_CATEGORY_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$categoriesDb = new Admin_Model_DbTable_Category();
					$categories = $categoriesDb->getCategories($category['type'], $category['id']);
					if(!empty($categories)) {
						//Do not delete the category if it has child categories
						$this->_flashMessenger->addMessage('MESSAGES_CATEGORY_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$categoryDb->deleteCategory($id);
						$this->setOrdering($category['clientid'], $category['type'], $category['parentid']);
						$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
					}
				}
			} elseif($category['type'] == 'shop') {
				$itemDb = new Items_Model_DbTable_Item();
				$items = $itemDb->getItemsByCategory($id);
				if(!empty($items)) {
					//Do not delete the category if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_CATEGORY_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$categoriesDb = new Admin_Model_DbTable_Category();
					$categories = $categoriesDb->getCategories($category['type'], $category['id']);
					if(!empty($categories)) {
						//Do not delete the category if it has child categories
						$this->_flashMessenger->addMessage('MESSAGES_CATEGORY_CANNOT_BE_DELETED_HAS_CHILDS');
					} else {
						$categoryDb->deleteCategory($id);
						$this->setOrdering($category['clientid'], $category['type'], $category['parentid']);

						if($category['shopid']) {
							$slugDb = new Admin_Model_DbTable_Slug();
							$slugDb->deleteSlug('shops', 'category', $category['shopid'], $id);
						}
						$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
					}
				}
			}
		}
	}
}
