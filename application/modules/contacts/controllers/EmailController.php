<?php

class Contacts_EmailController extends Zend_Controller_Action
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
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$request = $this->getRequest();
		$params['contactid'] = $this->_getParam('contactid', 0);
		$data = $request->getPost();
		$params['module'] = isset($data['module']) ? $data['module'] : 0;
		$params['controller'] = isset($data['controller']) ? $data['controller'] : 0;
		$params['documentid'] = isset($data['documentid']) ? $data['documentid'] : 0;

		//Get email messages
		$get = new Contacts_Model_Get();
		$emailmessages = $get->emailmessages($params, $options);
		foreach($emailmessages as $id => $emailmessage) {
			if($emailmessage['documentid']) {
				$emailmessages[$id]['url'] = $this->_helper->Directory->getUrl($emailmessage['documentid']);
			} elseif($emailmessage['contactid']) {
				$emailmessages[$id]['url'] = $this->_helper->Directory->getUrl($emailmessage['contactid']);
			}
		}

		$userDb = new Users_Model_DbTable_User();
		$users = $userDb->getUsers();

		$this->view->users = $users;
		$this->view->emailmessages = $emailmessages;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$request = $this->getRequest();
		$params['contactid'] = $this->_getParam('contactid', 0);
		$data = $request->getPost();
		$params['module'] = isset($data['module']) ? $data['module'] : 0;
		$params['controller'] = isset($data['controller']) ? $data['controller'] : 0;
		$params['documentid'] = isset($data['documentid']) ? $data['documentid'] : 0;

		//Get email messages
		$get = new Contacts_Model_Get();
		$emailmessages = $get->emailmessages($params, $options);
		foreach($emailmessages as $id => $emailmessage) {
			$emailmessages[$id]['url'] = $this->_helper->Directory->getUrl($emailmessage['documentid']);
		}

		$userDb = new Users_Model_DbTable_User();
		$users = $userDb->getUsers();

		$this->view->users = $users;
		$this->view->emailmessages = $emailmessages;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
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
				$emailDb = new Contacts_Model_DbTable_Email();
				$emailDataBefore = $emailDb->getEmails($data['parentid'], $data['module'], $data['controller']);
				$latestOrdering = is_array($emailDataBefore) && !empty($emailDataBefore)
					? end($emailDataBefore)['ordering']
					: 0;
				$dataArray = array();
				$dataArray['module'] = $data['module'];
				$dataArray['controller'] = $data['controller'];
				$dataArray['parentid'] = $data['parentid'];
				$dataArray['password'] = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
				$dataArray['ordering'] = $latestOrdering+1;
				$emailDb->addEmail($dataArray);
				$emailDataAfter = $emailDb->getEmails($data['parentid'], $data['module'], $data['controller']);
				$email = end($emailDataAfter);
				echo $this->view->MultiForm('contacts', 'email', $email);
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
				$emailDb = new Contacts_Model_DbTable_Email();
				if($id > 0) {
					$emailDb->updateEmail($id, $data);
					echo Zend_Json::encode($data);
				}
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}

		$this->view->form = $form;
	}

	public function sendAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$this->_helper->Email->sendEmail('contacts', 'contact');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$emailDb = new Contacts_Model_DbTable_Email();
			$emailDb->deleteEmail($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
