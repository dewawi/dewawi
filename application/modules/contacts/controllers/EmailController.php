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

	protected function afterMultiUpdate(int $id, array $values, array $row): void
	{
		if(!array_key_exists('suppressed', $values)) return;

		$email = (string)($values['email'] ?? $row['email'] ?? '');

		$emailDb = new Contacts_Model_DbTable_Email();

		if($values['suppressed']) {
			$emailDb->suppressByEmail($email, 'manual');
		} else {
			$emailDb->unsuppressByEmail($email);
		}
	}

	public function assigncontactpersonAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			$this->getResponse()->setHttpResponseCode(405);
			return $this->assignmentError('MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_INVALID');
		}

		$post = (array)$this->getRequest()->getPost();
		$contactId = filter_var($post['contactid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		$emailId = filter_var($post['emailid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		$assignmentType = (string)($post['assignment_type'] ?? '');

		if ($contactId === false || $emailId === false || !in_array($assignmentType, ['new', 'existing'], true)) {
			return $this->assignmentError('MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_INVALID');
		}

		try {
			$contactpersonData = $assignmentType === 'new'
				? $this->getAssignmentContactpersonData($post)
				: [];
		} catch (InvalidArgumentException $e) {
			return $this->assignmentError('MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_INVALID');
		}

		$clientId = $this->getContactClientId();
		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($clientId);
		$emailDb = (new Contacts_Model_DbTable_Email())->setClientId($clientId);
		$contactpersonDb = (new Contacts_Model_DbTable_Contactperson())->setClientId($clientId);
		$adapter = $emailDb->getAdapter();

		try {
			$adapter->beginTransaction();
			$contact = $contactDb->getByIdForUpdate((int)$contactId);
			$email = $emailDb->getByIdForUpdate((int)$emailId);

			if (
				!$contact
				|| !$email
				|| (int)$email['parentid'] !== (int)$contactId
				|| (string)$email['module'] !== 'contacts'
				|| (string)$email['controller'] !== 'contact'
				|| (int)$email['clientid'] !== $clientId
			) {
				throw new DomainException('Invalid email assignment.');
			}

			if ($assignmentType === 'new') {
				$contactpersonId = $contactpersonDb->createForParent(
					(int)$contactId,
					'contacts',
					'contact',
					$contactpersonData
				);
			} else {
				$contactpersonId = filter_var($post['contactpersonid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
				$contactperson = $contactpersonId === false
					? null
					: $contactpersonDb->getByIdForUpdate((int)$contactpersonId);

				if (
					!$contactperson
					|| (int)$contactperson['parentid'] !== (int)$contactId
					|| (string)$contactperson['module'] !== 'contacts'
					|| (string)$contactperson['controller'] !== 'contact'
				) {
					throw new DomainException('Invalid contact person assignment.');
				}
			}

			$emailDb->updateById((int)$emailId, [
				'parentid' => (int)$contactpersonId,
				'module' => 'contacts',
				'controller' => 'contactperson',
			]);

			$updatedEmail = $emailDb->getById((int)$emailId);

			if (
				!$updatedEmail
				|| (int)$updatedEmail['id'] !== (int)$emailId
				|| (int)$updatedEmail['parentid'] !== (int)$contactpersonId
				|| (string)$updatedEmail['controller'] !== 'contactperson'
			) {
				throw new RuntimeException('Email assignment could not be verified.');
			}

			$adapter->commit();
		} catch (DomainException $e) {
			$adapter->rollBack();
			return $this->assignmentError('MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_INVALID');
		} catch (Exception $e) {
			$adapter->rollBack();
			return $this->assignmentError('MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_FAILED');
		}

		return $this->_helper->json([
			'ok' => true,
			'emailid' => (int)$emailId,
			'contactpersonid' => (int)$contactpersonId,
		]);
	}

	private function getAssignmentContactpersonData(array $post): array
	{
		$fields = ['salutation', 'title', 'firstname', 'lastname', 'position', 'department'];
		$data = array_intersect_key($post, array_flip($fields));
		$data['salutation'] = (string)($data['salutation'] ?? '0');

		if (!in_array($data['salutation'], ['0', 'Herr', 'Frau'], true)) {
			throw new InvalidArgumentException('Invalid salutation.');
		}

		$form = new Contacts_Form_Contactperson();

		if (trim((string)($data['lastname'] ?? '')) === '' || !$form->isValidPartial($data)) {
			throw new InvalidArgumentException('Invalid contact person.');
		}

		return $form->getFilteredValuesPartial($data);
	}

	private function getContactClientId(): int
	{
		$client = Zend_Registry::get('Client');
		$clientId = !empty($client['parentid'])
			? (int)($client['modules']['contacts'] ?? 0)
			: (int)$client['id'];

		if ($clientId <= 0) {
			throw new RuntimeException('Contact client context is missing.');
		}

		return $clientId;
	}

	private function assignmentError(string $message)
	{
		return $this->_helper->json([
			'ok' => false,
			'message' => $this->view->translate($message),
		]);
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

		$params['contactid'] = $this->_getParam(
			'contactid',
			0
		);

		$params['module'] = $request->getPost('module', $request->getQuery('module', ''));
		$params['controller'] = $request->getPost('controller', $request->getQuery('controller', ''));
		$params['documentid'] = $request->getPost('documentid', $request->getQuery('documentid', 0));

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

		$request = $this->getRequest();

		$params['contactid'] = $this->_getParam(
			'contactid',
			0
		);

		$params['module'] = $request->getPost('module', $request->getQuery('module', ''));
		$params['controller'] = $request->getPost('controller', $request->getQuery('controller', ''));
		$params['documentid'] = $request->getPost('documentid', $request->getQuery('documentid', 0));

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
