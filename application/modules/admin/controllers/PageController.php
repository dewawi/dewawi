<?php

class Admin_PageController extends DEEC_Controller_Action
{
	protected function requirePage(int $id, bool $silent = false): ?array
	{
		$pageDb = new Admin_Model_DbTable_Page();
		$page = $pageDb->getPage($id);

		if ($page) {
			return $page;
		}

		$request = $this->getRequest();

		// AJAX
		if ($request->isXmlHttpRequest()) {
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->layout->disableLayout();

			$this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);

			return null;
		}

		// Silent mode (PDF etc.)
		if ($silent) {
			$this->_helper->viewRenderer->setNoRender();
			return null;
		}

		// Default redirect
		$this->_flashMessenger->addMessage('MESSAGES_PAGE_NOT_FOUND');
		$this->_helper->redirector->gotoSimple('index', 'page');

		return null;
	}

	public function indexAction()
	{
		if ($this->getRequest()->isPost()) {
			$this->_helper->getHelper('layout')->disableLayout();
		}

		$this->buildIndexView();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$this->buildIndexView();
	}

	protected function buildIndexView(): void
	{
		$toolbar = new Admin_Form_Toolbar();
		$toolbarInline = new Admin_Form_ToolbarInline();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$pagesDb = new Admin_Model_DbTable_Page();
		if($params['type'] == 'shop') {
			$items = $pagesDb->getPages($params['type'], null, $params['shopid']);
		} else {
			$items = $pagesDb->getPages($params['type']);
		}

		$pages = new Admin_Model_List_Pages();
		$pages->configure([
			'items' => $items,
			'options' => $options,
			'view' => $this->view,
			'module' => $this->getRequest()->getModuleName(),
			'controller' => $this->getRequest()->getControllerName(),
			'toolbarInline' => $toolbarInline,
			'context' => [
				'user' => $this->_user,
			],
		]);

		$this->view->pages = $pages;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->toolbarInline = $toolbarInline;
		$this->view->messages = array_merge(
			$this->_flashMessenger->getMessages(),
			$this->_flashMessenger->getCurrentMessages()
		);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Page();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			//if($form->isValid($data)) {
				$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
				if(!isset($data['shopid'])) $data['shopid'] = 0;
				//$data['parentid'] = $params['parentid'];

				$pageDb = new Admin_Model_DbTable_Page();
				$id = $pageDb->addPage($data);
				//echo Zend_Json::encode($data);
				echo Zend_Json::encode($pageDb->getPage($id));
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

		$page = $this->requirePage($id);
		if (!$page) return;

		$pageDb = new Admin_Model_DbTable_Page();

		$this->_helper->Access->lock($id, $this->_user['id'], $page['locked'] ?? 0, $page['lockedtime'] ?? null);

		$formFactory = new Admin_Service_EditFormFactory();
		$formData = $formFactory->create('Admin_Form_Page');
		$form = $formData['form'];
		$options = $formData['options'];
		$toolbar = new Admin_Form_Toolbar();

		if ($request->isPost()) {
			if ($isAjax) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				$post = (array)$request->getPost();

				return $this->_helper->json($this->savePageAjax($form, $pageDb, $id, $page, $post));
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				if (isset($values['parentid']) && (string)$values['parentid'] !== (string)$page['parentid']) {
					$values['ordering'] = $this->getLatestOrdering(
						$values['clientid'] ?? $page['clientid'],
						$values['type'] ?? $page['type'],
						$values['parentid']
					) + 1;
				}

				$pageDb->updatePage($id, $values);

				if (isset($values['parentid']) && (string)$values['parentid'] !== (string)$page['parentid']) {
					$this->setOrdering($page['clientid'], $page['type'], $page['parentid']);
				}

				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'page', null, ['id' => $id]);
			}
		} else {
			$formFactory->populate($form, $page, $id, 'shops', 'page');
		}

		//$get = new Shops_Model_Get();
		//$tags = $get->tags('shops', 'page', $id);

		$this->view->assign([
			'id' => $id,
			'form' => $form,
			'toolbar' => $toolbar,
			'options' => $options,
			//'tags' => $tags,
			'activeTab' => $request->getCookie('tab', null),
		]);

		$this->view->messages = array_merge(
			$this->_flashMessenger->getMessages(),
			$this->_flashMessenger->getCurrentMessages()
		);
		$this->_flashMessenger->clearCurrentMessages();
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

	public function sortAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$data = $request->getPost();
			$pageDb = new Admin_Model_DbTable_Page();
			$page = $pageDb->getPage($data['id']);
			$orderings = $this->getOrdering($page['clientid'], $page['type'], $page['parentid']);
			$currentOrdering = array_search($data['id'], $orderings);
			if(($data['ordering'] == 'down') && (isset($orderings[$currentOrdering+1]))) {
				$pageDb->sortPage($data['id'], $currentOrdering+1);
				$pageDb->sortPage($orderings[$currentOrdering+1], $currentOrdering);
			} elseif(($data['ordering'] == 'up') && (isset($orderings[$currentOrdering-1]))) {
				$pageDb->sortPage($data['id'], $currentOrdering-1);
				$pageDb->sortPage($orderings[$currentOrdering-1], $currentOrdering);
			} elseif($data['ordering'] > 0) {
				if($data['ordering'] < $currentOrdering) {
					$pageDb->sortPage($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering < $currentOrdering) && ($ordering >= $data['ordering'])) $pageDb->sortPage($id, $ordering+1);
					}
				} elseif($data['ordering'] > $currentOrdering) {
					$pageDb->sortPage($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering > $currentOrdering) && ($ordering <= $data['ordering'])) $pageDb->sortPage($id, $ordering-1);
					}
				}
			}
			$this->setOrdering($page['clientid'], $page['type'], $page['parentid']);
		}
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

	protected function savePageAjax(DEEC_Form $form, Admin_Model_DbTable_Page $pageDb, int $id, array $page, array $post): array
	{
		if (!$form->isValidPartial($post)) {
			return [
				'ok' => false,
				'errors' => $this->toAjaxErrorMessages($form->getErrors()),
			];
		}

		$values = $form->getFilteredValuesPartial($post);

		try {
			if (array_key_exists('parentid', $values) && (string)$values['parentid'] !== (string)$page['parentid']) {
				$targetClientId = $values['clientid'] ?? $page['clientid'];
				$targetType = $values['type'] ?? $page['type'];

				$values['ordering'] = $this->getLatestOrdering(
					$targetClientId,
					$targetType,
					$values['parentid']
				) + 1;

				$pageDb->updatePage($id, $values);

				$this->setOrdering($page['clientid'], $page['type'], $page['parentid']);
			} else {
				$pageDb->updatePage($id, $values);
			}
		} catch (Exception $e) {
			return [
				'ok' => false,
				'message' => 'save_failed',
			];
		}

		$row = $pageDb->getPage($id);
		$changedFields = array_keys($values);
		$display = DEEC_Display::fromRow($form, $row, $changedFields);

		return [
			'ok' => true,
			'id' => $id,
			'values' => array_intersect_key($row, array_flip($changedFields)),
			'display' => $display,
			'meta' => [
				'recalc' => [],
			],
		];
	}

	protected function toAjaxErrorMessages(array $errors): array
	{
		$out = [];

		foreach ($errors as $field => $codes) {
			$messages = [];

			foreach ($codes as $code) {
				switch ($code) {
					case 'required':
						$messages[] = 'This field is required.';
						break;
					case 'email':
						$messages[] = 'Please enter a valid email address.';
						break;
					case 'number':
						$messages[] = 'Please enter a number.';
						break;
					case 'min':
						$messages[] = 'The value is too small.';
						break;
					case 'max':
						$messages[] = 'The value is too large.';
						break;
					case 'pattern':
						$messages[] = 'The format is invalid.';
						break;
					default:
						$messages[] = 'Invalid value.';
						break;
				}
			}

			$out[$field] = implode(' ', $messages);
		}

		return $out;
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$pageDb = new Admin_Model_DbTable_Page();
		$page = $pageDb->getPage($id);
		if($this->isLocked($page['locked'], $page['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($page['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$pageDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$pageDb = new Admin_Model_DbTable_Page();
		$pageDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$pageDb = new Admin_Model_DbTable_Page();
		$pageDb->lock($id);
	}


	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Page();

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function isLocked($locked, $lockedtime)
	{
		if($locked && ($locked != $this->_user['id'])) {
			$timeout = strtotime($lockedtime) + 300; // 5 minutes
			$timestamp = strtotime($this->_date);
			if($timeout < $timestamp) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	protected function setOrdering($clientid, $type, $parentid)
	{
		$i = 1;
		$pagesDb = new Admin_Model_DbTable_Page();
		$pages = $pagesDb->getPages($type, $parentid);
		foreach($pages as $page) {
			if(isset($page['ordering'])) {
				//if($page['ordering'] != $i) {
					if(!isset($pagesDb)) $pagesDb = new Admin_Model_DbTable_Page();
					//print_r($page);
					//print_r($i);
					$pagesDb->sortPage($page['id'], $i);
					++$i;
				//}
			}
		}
	}

	protected function getOrdering($clientid, $type, $parentid)
	{
		$i = 1;
		$pagesDb = new Admin_Model_DbTable_Page();
		$pages = $pagesDb->getPages($type, $parentid);
		$orderings = array();
		foreach($pages as $page) {
			if(isset($page['id'])) {
				$orderings[$i] = $page['id'];
				++$i;
			}
		}
		return $orderings;
	}

	protected function getLatestOrdering($clientid, $type, $parentid)
	{
		$ordering = $this->getOrdering($clientid, $type, $parentid);
		end($ordering);
		return key($ordering);
	}

	protected function copyChilds($oldId, $pages, $newId)
	{
		foreach($pages[$oldId]['childs'] as $child) {
			$pageDb = new Admin_Model_DbTable_Page();
			$data = $pageDb->getPage($child);
			unset($data['id']);
			$data['parentid'] = $newId;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			$data['locked'] = 0;
			$data['lockedtime'] = NULL;
			$newChildId = $pageDb->addPage($data);

			$childPages = $pageDb->getPages($data['type'], $child);
			if(isset($childPages[$child]['childs'])) $this->copyChilds($child, $childPages, $newChildId);
		}
	}
}
