<?php

class Contacts_EmailController extends DEEC_Controller_Action
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
		$this->_helper->layout->disableLayout();

		if (!$request->isPost()) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'invalid_request',
			]);
		}

		$post = (array)$request->getPost();

		$parentid = (int)$this->_getParam('parent_id', 0);

		if ($parentid <= 0) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'missing_parent',
			]);
		}

		$parentModule = !empty($post['parent_module']) ? (string)$post['parent_module'] : 'contacts';
		$parentController = !empty($post['parent_controller']) ? (string)$post['parent_controller'] : 'contact';

		$client = Zend_Registry::get('Client');

		$data = [
			'password' => password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT),
		];

		$emailDb = new Contacts_Model_DbTable_Email();
		$newId = $emailDb->createForParent($parentid, $parentModule, $parentController, $data);

		$row = $emailDb->getById($newId);
		if (!$row) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$rowForm = new Contacts_Form_Email();
		$this->_helper->Options->applyFormOptions($rowForm);

		$ctx = [
			'module' => 'contacts',
			'controller' => 'email',
		];

		echo $rowForm->renderMultiItem('email', $row, $ctx);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		if (!$request->isPost() || $id <= 0) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		$form = new Contacts_Form_Email();
		$this->_helper->Options->applyFormOptions($form);

		$post = (array)$request->getPost();

		if (!$form->isValidPartial($post)) {
			return $this->_helper->json([
				'ok' => false,
				'errors' => $this->toErrorMessages($form->getErrors(), $form),
			]);
		}

		$values = $form->getFilteredValuesPartial($post);

		$emailDb = new Contacts_Model_DbTable_Email();

		try {
			$emailDb->updateById($id, $values);
		} catch (Exception $e) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$row = $emailDb->getById($id);
		if (!$row) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		$changedFields = array_keys($values);
		$display = DEEC_Display::fromRow($form, $row, $changedFields);

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
			'values' => array_intersect_key($row, array_flip($changedFields)),
			'display' => $display,
		]);
	}

	public function sendAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$this->_helper->Email->sendEmail('contacts', 'contact', 'contact');
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

	/**
	 * Sanitize input string: removes spaces, line breaks,
	 * and normalizes case.
	 */
	private function sanitizeEmail(string $email): string
	{
		// remove all kinds of whitespace (spaces, tabs, newlines, non-breaking spaces)
		$email = preg_replace('/\s+/u', '', $email);
		// trim in case of invisible unicode chars
		$email = trim($email);
		// normalize to lowercase
		$email = mb_strtolower($email);
		return $email;
	}
}
