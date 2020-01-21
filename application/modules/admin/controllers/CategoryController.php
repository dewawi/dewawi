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
		$this->view->client = Zend_Registry::get('Client');
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
		$categories = $this->_helper->Categories->getCategories($form, $params['clientid'], $params['type']);

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
		$categories = $this->_helper->Categories->getCategories($form, $params['clientid'], $params['type']);

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
				$data['created'] = $this->_date;
				$data['createdby'] = $this->_user['id'];
				$data['clientid'] = $params['clientid'];
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
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

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
			$categoryDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Admin_Form_Category();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$categories = $this->_helper->Categories->getCategories($form, $params['clientid'], $params['type']);
			if($request->isPost()) {
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$categoryDb = new Admin_Model_DbTable_Category();
				    $data['modified'] = $this->_date;
				    $data['modifiedby'] = $this->_user['id'];
                    if($element == 'parentid') {
				        $data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
                        $categoryArray = $categoryDb->getCategory($id);
					    $categoryDb->updateCategory($id, $data);

			            //sort old parent category
                		$categories = $this->_helper->Categories->getCategories(null, $params['clientid'], $params['type'], $categoryArray['parentid']);
                        $i = 1;
                        foreach($categories as $category) {
                            if(isset($category['id'])) {
                                //if($category['ordering'] != $i)
                                $categoryDb->updateCategory($category['id'], array('ordering' => $i));
                                ++$i;
                            }
                        }
                    } else {
					    $categoryDb->updateCategory($id, $data);
                    }
					echo Zend_Json::encode($categoryDb->getCategory($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
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
		$data['company'] = $data['company'].' 2';
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$categoryDb->addCategory($data);

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
			if($data['ordering'] == 'down') {
				$categoryDb->sortCategory($data['id'], $currentOrdering+1);
				$categoryDb->sortCategory($orderings[$currentOrdering+1], $currentOrdering);
			} elseif($data['ordering'] == 'up') {
				$categoryDb->sortCategory($data['id'], $currentOrdering-1);
				$categoryDb->sortCategory($orderings[$currentOrdering-1], $currentOrdering);
			} elseif($data['ordering'] > 0) {
				if($data['ordering'] < $currentOrdering) {
					$categoryDb->sortCategory($data['id'], $data['ordering']);
					foreach($orderings as  $ordering => $id) {
						if(($ordering < $currentOrdering) && ($ordering >= $data['ordering'])) $categoryDb->sortCategory($id, $ordering+1);
					}
				} elseif($data['ordering'] > $currentOrdering) {
					$categoryDb->sortCategory($data['id'], $data['ordering']);
					foreach($orderings as  $ordering => $id) {
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
			$categoryDb->deleteCategory($id);
			$this->setOrdering($category['clientid'], $category['type'], $category['parentid']);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
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
			$categoryDb->lock($id, $this->_user['id'], $this->_date);
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
		$categoryDb->lock($id, $this->_user['id'], $this->_date);
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
		$categories = $this->_helper->Categories->getCategories(null, $clientid, $type, $parentid);
		foreach($categories as $category) {
			if($category['ordering'] != $i) {
				if(!isset($categoriesDb)) $categoriesDb = new Admin_Model_DbTable_Category();
				$categoriesDb->sortCategory($category['id'], $i);
			}
			++$i;
		}
	}

	protected function getOrdering($clientid, $type, $parentid)
	{
		$categories = $this->_helper->Categories->getCategories(null, $clientid, $type, $parentid);
		$i = 1;
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
}
