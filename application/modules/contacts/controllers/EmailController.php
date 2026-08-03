<?php

class Contacts_EmailController
	extends DEEC_Controller_MultiEntityAction
{
	protected function getParentFormClass(): string
	{
		return Contacts_Form_Contact::class;
	}

	protected function getCreateDefaults(
		array $post
	): array {
		return [
			'password' => password_hash(
				bin2hex(random_bytes(5)),
				PASSWORD_DEFAULT
			),
		];
	}

	public function indexAction()
	{
		if ($this->getRequest()->isPost()) {
			$this->disableLayout();
		}

		$toolbar = new Contacts_Form_Toolbar();

		$options = $this->_helper->Options->getOptions(
			$toolbar
		);

		$params = $this->_helper->Params->getParams(
			$toolbar,
			$options
		);

		$request = $this->getRequest();
		$post = (array)$request->getPost();

		$params['contactid'] = (int)$this->_getParam(
			'contactid',
			0
		);

		$params['module'] = $post['module'] ?? 0;
		$params['controller'] = $post['controller'] ?? 0;
		$params['documentid'] = $post['documentid'] ?? 0;

		$get = new Contacts_Model_Get();

		$emailMessages = $get->emailmessages(
			$params,
			$options
		);

		foreach ($emailMessages as $id => $emailMessage) {
			if (!empty($emailMessage['documentid'])) {
				$emailMessages[$id]['url']
					= $this->_helper->Directory->getUrl(
						$emailMessage['documentid']
					);
			} elseif (!empty($emailMessage['contactid'])) {
				$emailMessages[$id]['url']
					= $this->_helper->Directory->getUrl(
						$emailMessage['contactid']
					);
			}
		}

		$userDb = new Users_Model_DbTable_User();

		$this->view->users = $userDb->getUsers();
		$this->view->emailmessages = $emailMessages;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages
			= $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = (string)$this->_getParam(
			'type',
			'index'
		);

		$this->_helper->viewRenderer->setRender($type);
		$this->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();

		$options = $this->_helper->Options->getOptions(
			$toolbar
		);

		$params = $this->_helper->Params->getParams(
			$toolbar,
			$options
		);

		$post = (array)$this->getRequest()->getPost();

		$params['contactid'] = (int)$this->_getParam(
			'contactid',
			0
		);

		$params['module'] = $post['module'] ?? 0;
		$params['controller'] = $post['controller'] ?? 0;
		$params['documentid'] = $post['documentid'] ?? 0;

		$get = new Contacts_Model_Get();

		$emailMessages = $get->emailmessages(
			$params,
			$options
		);

		foreach ($emailMessages as $id => $emailMessage) {
			$documentId = (int)(
				$emailMessage['documentid'] ?? 0
			);

			if ($documentId > 0) {
				$emailMessages[$id]['url']
					= $this->_helper->Directory->getUrl(
						$documentId
					);
			}
		}

		$userDb = new Users_Model_DbTable_User();

		$this->view->users = $userDb->getUsers();
		$this->view->emailmessages = $emailMessages;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages
			= $this->_flashMessenger->getMessages();
	}

	public function sendAction()
	{
		$this->disableView();

		try {
			$this->_helper->Email->sendEmail(
				'contacts',
				'contact',
				'contact'
			);
		} catch (Exception $e) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'send_failed',
			]);
		}

		return $this->_helper->json([
			'ok' => true,
		]);
	}
}
