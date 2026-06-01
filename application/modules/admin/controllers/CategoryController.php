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

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$form = new Admin_Form_Category();
		$options = $this->_helper->Options->applyFormOptions($form);

		$toolbar = new Admin_Form_Toolbar();
		$categoryDb = new Admin_Model_DbTable_Category();

		$category = $categoryDb->getById($id);

		if (!$category) {
			if ($isAjax) {
				$this->disableView();

				return $this->_helper->json([
					'ok' => false,
					'message' => 'not_found',
				]);
			}

			$this->_flashMessenger->addMessage('MESSAGES_CATEGORY_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'category');
		}

		$this->_helper->Access->lock($id, $this->_user['id'], $category['locked'] ?? 0, $category['lockedtime'] ?? null);

		if ($request->isPost()) {
			if ($isAjax) {
				$this->disableView();

				return $this->_helper->json(
					$this->saveFormAjax($form, $categoryDb, $id)
				);
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				$categoryDb->updateById($id, $values);

				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'category', null, ['id' => $id]);
			}
		} else {
			$locale = Zend_Registry::get('Zend_Locale');
			$categoryDisplay = DEEC_Display::rowToFormValues($form, $category, $locale);

			$form->setValues($categoryDisplay);

			$this->_helper->MultiEntityLoader->populate($form, $id, 'admin', 'category');
		}

		$vmService = new Admin_Service_CategoryEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$category);

		$this->view->assign(array_merge($vm, [
			'id' => $id,
			'form' => $form,
			'toolbar' => $toolbar,
			'options' => $options,
			'activeTab' => $request->getCookie('tab', null),
		]));

		$this->assignMessages();
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
