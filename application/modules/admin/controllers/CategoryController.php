<?php

class Admin_CategoryController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function getAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$element = $this->_getParam('element', null);
		$form = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($form);
		if(isset($form->$element)) {
			$options = $form->$element->getMultiOptions();
			echo Zend_Json::encode($options);
		} else {
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS')));
		}
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Category();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$categoriesDb = new Admin_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories($params['type']);
		$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($categories));

		$this->view->form = $form;
		$this->view->categories = $categories;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Category();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$categoriesDb = new Admin_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories($params['type']);
		$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($categories));

		$this->view->form = $form;
		$this->view->categories = $categories;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
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
				//$data['parentid'] = $params['parentid'];

				$categoryDb = new Admin_Model_DbTable_Category();
				$id = $categoryDb->addCategory($data);
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
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$categoryDb = new Admin_Model_DbTable_Category();
		$category = $categoryDb->getCategory($id);

		if($this->isLocked($category['locked'], $category['lockedtime'])) {
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_LOCKED')));
			} else {
				$this->_flashMessenger->addMessage('MESSAGES_LOCKED');
				$this->_helper->redirector('index');
			}
		} else {
			$categoryDb->lock($id);

			$form = new Admin_Form_Category();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$categoriesDb = new Admin_Model_DbTable_Category();
			$categories = $categoriesDb->getCategories($params['type']);
			if($request->isPost()) {
				$data = $request->getPost();
				$element = key($data);
				//if(isset($form->$element) && $form->isValidPartial($data)) { // to do add options for parentid before form validation
				if(isset($form->$element)) {
					$categoryDb = new Admin_Model_DbTable_Category();
					if($element == 'parentid') {
						$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
						$categoryArray = $categoryDb->getCategory($id);
						$categoryDb->updateCategory($id, $data);

						//sort old parent category
						$this->setOrdering($categoryArray['clientid'], $categoryArray['type'], $categoryArray['parentid']);

						/*$categories = $this->_helper->Categories->getCategories(null, $params['clientid'], $params['type'], $categoryArray['parentid']);
						$i = 1;
						foreach($categories as $category) {
							if(isset($category['id'])) {
								//if($category['ordering'] != $i)
								$categoryDb->updateCategory($category['id'], array('ordering' => $i));
								++$i;
							}
						}*/
					} else {
						$categoryDb->updateCategory($id, $data);
					}
					echo Zend_Json::encode($categoryDb->getCategory($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$form->populate($category);

					//Toolbar
					$toolbar = new Admin_Form_Toolbar();

					$this->view->form = $form;
					$this->view->toolbar = $toolbar;
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
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

		$childCategories = $categoriesDb->getCategories($data['type'], $id);
		if(isset($childCategories[$id]['childs'])) $this->copyChilds($id, $childCategories, $newId);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');


	}

	public function sortAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$data = $request->getPost();
			$categoryDb = new Admin_Model_DbTable_Category();
			$category = $categoryDb->getCategory($data['id']);
			$orderings = $this->getOrdering($category['clientid'], $category['type'], $category['parentid']);
			$currentOrdering = array_search($data['id'], $orderings);
			if(($data['ordering'] == 'down') && (isset($orderings[$currentOrdering+1]))) {
				$categoryDb->sortCategory($data['id'], $currentOrdering+1);
				$categoryDb->sortCategory($orderings[$currentOrdering+1], $currentOrdering);
			} elseif(($data['ordering'] == 'up') && (isset($orderings[$currentOrdering-1]))) {
				$categoryDb->sortCategory($data['id'], $currentOrdering-1);
				$categoryDb->sortCategory($orderings[$currentOrdering-1], $currentOrdering);
			} elseif($data['ordering'] > 0) {
				if($data['ordering'] < $currentOrdering) {
					$categoryDb->sortCategory($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering < $currentOrdering) && ($ordering >= $data['ordering'])) $categoryDb->sortCategory($id, $ordering+1);
					}
				} elseif($data['ordering'] > $currentOrdering) {
					$categoryDb->sortCategory($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering > $currentOrdering) && ($ordering <= $data['ordering'])) $categoryDb->sortCategory($id, $ordering-1);
					}
				}
			}
			$this->setOrdering($category['clientid'], $category['type'], $category['parentid']);
		}
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
			}
		}
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$categoryDb = new Admin_Model_DbTable_Category();
		$category = $categoryDb->getCategory($id);
		if($this->isLocked($category['locked'], $category['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($category['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$categoryDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$categoryDb = new Admin_Model_DbTable_Category();
		$categoryDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$categoryDb = new Admin_Model_DbTable_Category();
		$categoryDb->lock($id);
	}


	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Category();

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
		$categoriesDb = new Admin_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories($type, $parentid);
		foreach($categories as $category) {
			if(isset($category['ordering'])) {
				//if($category['ordering'] != $i) {
					if(!isset($categoriesDb)) $categoriesDb = new Admin_Model_DbTable_Category();
					//print_r($category);
					//print_r($i);
					$categoriesDb->sortCategory($category['id'], $i);
					++$i;
				//}
			}
		}
	}

	protected function getOrdering($clientid, $type, $parentid)
	{
		$i = 1;
		$categoriesDb = new Admin_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories($type, $parentid);
		$orderings = array();
		foreach($categories as $category) {
			if(isset($category['id'])) {
				$orderings[$i] = $category['id'];
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

	protected function copyChilds($oldId, $categories, $newId)
	{
		foreach($categories[$oldId]['childs'] as $child) {
			$categoryDb = new Admin_Model_DbTable_Category();
			$data = $categoryDb->getCategory($child);
			unset($data['id']);
			$data['parentid'] = $newId;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			$data['locked'] = 0;
			$data['lockedtime'] = NULL;
			$newChildId = $categoryDb->addCategory($data);
			$childCategories = $categoryDb->getCategories($data['type'], $child);
			if(isset($childCategories[$child]['childs'])) $this->copyChilds($child, $childCategories, $newChildId);
		}
	}
}
