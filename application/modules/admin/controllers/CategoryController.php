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

	protected function beforeEditSave(array $values, array $row): array
	{
		if (array_key_exists('parentid', $values) && (string)$values['parentid'] !== (string)$row['parentid']) {
			$values['ordering'] = $this->getLatestOrdering(
				(int)$values['parentid'],
				(string)($row['type'] ?? ''),
				'category'
			) + 1;
		}

		return $values;
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (array_key_exists('parentid', $values) && (string)$values['parentid'] !== (string)$oldRow['parentid']) {
			$this->setOrdering(
				(int)$oldRow['parentid'],
				(string)($oldRow['type'] ?? ''),
				'category'
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

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Category();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			//if($form->isValid($data)) {
				$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
				if(!isset($data['shopid'])) $data['shopid'] = 0;
				//$data['parentid'] = $params['parentid'];

				$categoryDb = new Admin_Model_DbTable_Category();
				$id = $categoryDb->addCategory($data);

				if($data['shopid']) {
					$slugDb = new Admin_Model_DbTable_Slug();
					$slugDb->addSlug('shops', 'category', $data['shopid'], $data['parentid'], $id, $id);
				}

				//echo Zend_Json::encode($data);
				echo Zend_Json::encode($categoryDb->getCategory($id));
			//} else {
			//	echo Zend_Json::encode($data);
				//echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			//}
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
