<?php

class Contacts_TagController extends Zend_Controller_Action
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

	public function indexAction()
	{
		$request = $this->getRequest();
		$contactid = $this->_getParam('contactid', 0);
		$data = $request->getPost();
		$module = isset($data['module']) ? $data['module'] : 0;
		$controller = isset($data['controller']) ? $data['controller'] : 0;
		$documentid = isset($data['documentid']) ? $data['documentid'] : 0;

		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->getHelper('layout')->disableLayout();

		//$toolbar = new Contacts_Form_Toolbar();
		//$options = $this->_helper->Options->getOptions($toolbar);
		//$params = $this->_helper->Params->getParams($toolbar, $options);

		//Get email messages
		$emailmessagesDb = new Contacts_Model_DbTable_Emailmessage();
		$emailmessages = $emailmessagesDb->getEmailmessages($contactid, $documentid, $module, $controller);

		$userDb = new Users_Model_DbTable_User();
		$users = $userDb->getUsers();

		$this->view->module = $module;
		$this->view->controller = $controller;
		$this->view->url = $this->_helper->Directory->getUrl($documentid);
		$this->view->users = $users;
		$this->view->emailmessages = $emailmessages;
		//$this->view->options = $options;
		//$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$tagEntityDb = new Application_Model_DbTable_Tagentity();
				$tagEntityDataBefore = $tagEntityDb->getTagEntities('contacts', 'contact', $data['contactid']);
				$latest = end($tagEntityDataBefore);
				if(isset($data['tagid']) && $data['tagid']) {
					header('Content-type: application/json');
					$existingTags = array();
					foreach($tagEntityDataBefore as $tagEntity) {
						$existingTags[$tagEntity['tagid']] = $tagEntity['tagid'];
					}
					if(array_search($data['tagid'], $existingTags) !== false) {
						echo Zend_Json::encode(array('message' => $this->view->translate('TAG_ALREADY_EXISTS')));
					} else {
						$tagEntityDb->addTagEntity(array('tagid' => $data['tagid'], 'entityid' => $data['contactid'], 'module' => 'contacts', 'controller' => 'contact', 'ordering' => $latest['ordering']+1));
						$tagEntityDataAfter = $tagEntityDb->getTagEntities('contacts', 'contact', $data['contactid']);
						$tagEntity = end($tagEntityDataAfter);
						echo Zend_Json::encode($tagEntity);
					}
				} else {
					$tagEntityDb->addTagEntity(array('tagid' => 0, 'entityid' => $data['contactid'], 'module' => 'contacts', 'controller' => 'contact', 'ordering' => $latest['ordering']+1));
					$tagEntityDataAfter = $tagEntityDb->getTagEntities('contacts', 'contact', $data['contactid']);
					$tagEntity = end($tagEntityDataAfter);
					echo $this->view->MultiForm('tag', $tagEntity);
				}
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$tagDb = new Application_Model_DbTable_Tag();
				$tags = $tagDb->getTags('contacts', 'contact');

				$key = array_search($data['tag'], $tags);
				if(false !== $key) {
					$data['tagid'] = $key;
				} else {
					$data['tagid'] = $tagDb->addTag(array('title' => $data['tag'], 'module' => 'contacts', 'controller' => 'contact'));
				}
				unset($data['tag']);

				$tagEntityDb = new Application_Model_DbTable_Tagentity();
				if($id > 0) {
					$tagEntityDb->updateTagEntity($id, $data);
					echo Zend_Json::encode($data);
				}
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}

		$this->view->form = $form;
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$tagEntityDb = new Application_Model_DbTable_Tagentity();
			$tagEntityDb->deleteTagEntity($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
