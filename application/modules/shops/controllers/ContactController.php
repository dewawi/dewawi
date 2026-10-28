<?php

class Shops_ContactController extends Shops_Controller_Action
{
	protected $contactDataSession;

	public function init()
	{
		parent::init();

		$this->contactDataSession = new Zend_Session_Namespace('ShopsContact');
	}

	public function sendAction()
	{
		$request = $this->getRequest();

		if (!$request->isPost()) {
			return $this->_helper->redirector->gotoSimple('index', 'index', 'shops');
		}

		$data = (array)$request->getPost();

		if (!empty($data['fax_number'])) {
			$this->contactDataSession->formData = [];
			$this->contactDataSession->stored = false;
			return $this->_helper->redirector->gotoRoute([], 'contact_success', true);
		}

		$form = new Shops_Form_Contact();

		if (!$form->isValid($data)) {
			$this->contactDataSession->formData = $this->getDisplayData($data);
			$this->contactDataSession->errors = $form->getMessages();
			$this->_flashMessenger->addMessage('MESSAGES_FORM_INVALID');

			return $this->_helper->redirector->gotoRoute([], 'contact_error', true);
		}

		$values = $form->getValues();
		$token = (string)($values['inquiry_token'] ?? '');
		$storedData = array_merge($this->getDisplayData($values), [
			'origin' => 'contact',
			'source_path' => $this->getSourcePath(),
		]);

		$inquiryDb = new Shops_Model_DbTable_Inquirydata();
		$stored = $inquiryDb->saveOnce(0, $token, $storedData);

		$this->contactDataSession->formData = $this->getDisplayData($values);
		$this->contactDataSession->stored = true;
		$this->contactDataSession->duplicate = !$stored['inserted'];
		$this->contactDataSession->notificationSent = null;

		if ($stored['inserted']) {
			try {
				$emailResult = $this->_helper->Email->sendEmail('shops', 'contact', 'contact');
				$this->contactDataSession->notificationSent = !empty($emailResult['ok']);
			} catch (Exception $e) {
				error_log('Stored shop contact inquiry could not be emailed: ' . $e->getMessage());
				$this->contactDataSession->notificationSent = false;
				$this->_flashMessenger->addMessage('MESSAGES_EMAIL_SENT_ERROR');
			}
		}

		$this->contactDataSession->inquiryToken = bin2hex(random_bytes(16));

		return $this->_helper->redirector->gotoRoute([], 'contact_success', true);
	}

	public function successAction()
	{
		$this->initSiteLayout();

		$this->view->formData = $this->contactDataSession->formData ?? [];
		$this->view->stored = $this->contactDataSession->stored ?? false;
		$this->view->duplicate = $this->contactDataSession->duplicate ?? false;
		$this->view->notificationSent = $this->contactDataSession->notificationSent ?? null;

		$this->assignMessages();
	}

	public function errorAction()
	{
		$this->initSiteLayout();

		$this->view->formData = $this->contactDataSession->formData ?? [];
		$this->view->errors = $this->contactDataSession->errors ?? [];

		$this->assignMessages();
	}

	private function getDisplayData(array $data): array
	{
		return array_intersect_key($data, array_flip([
			'name', 'email', 'phone', 'subject', 'message', 'privacy',
		]));
	}

	private function getSourcePath(): string
	{
		$path = parse_url((string)$this->getRequest()->getHeader('Referer'), PHP_URL_PATH);

		return is_string($path) ? $path : '';
	}
}
