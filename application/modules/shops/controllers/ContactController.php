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
		if (!$this->isReasonableSubmission($data, 30, 16384, 2)) {
			return $this->rejectSubmission(413, 'SHOPS_SUBMISSION_TOO_LARGE');
		}

		try {
			$rate = $this->consumeSubmissionLimit('contact', 5, 900);
		} catch (Throwable $e) {
			error_log('Shop contact rate limiter unavailable: ' . $e->getMessage());
			return $this->rejectSubmission(503, 'SHOPS_SUBMISSION_UNAVAILABLE');
		}
		if (!$rate['allowed']) {
			return $this->rejectSubmission(429, 'SHOPS_RATE_LIMIT', (int)$rate['retryAfter']);
		}

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
		$expectedToken = (string)($this->contactDataSession->inquiryToken ?? '');
		if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
			return $this->rejectSubmission(400, 'SHOPS_FORM_EXPIRED');
		}
		$storedData = array_merge($this->getDisplayData($values), [
			'origin' => 'contact',
			'source_path' => $this->getSourcePath(),
		]);

		$inquiryDb = new Shops_Model_DbTable_Inquirydata();
		try {
			$stored = $inquiryDb->saveOnce(0, $token, $storedData);
		} catch (Throwable $e) {
			error_log('Shop contact inquiry could not be stored: ' . $e->getMessage());
			return $this->rejectSubmission(500, 'SHOPS_SUBMISSION_FAILED');
		}

		$this->contactDataSession->formData = $this->getDisplayData($values);
		$this->contactDataSession->stored = true;
		$this->contactDataSession->duplicate = !$stored['inserted'];
		$this->contactDataSession->notificationSent = null;

		if ($stored['inserted']) {
			$this->markSiteConversion('contact_submitted');
			try {
				$emailResult = $this->_helper->Email->sendEmail('shops', 'contact', 'contact');
				$this->contactDataSession->notificationSent = !empty($emailResult['customerAccepted']);
				if(($emailResult['internalAccepted'] ?? null) === false) error_log('Stored shop contact internal notification was not accepted by SMTP');
			} catch (Throwable $e) {
				error_log('Stored shop contact inquiry could not be emailed: ' . $e->getMessage());
				$this->contactDataSession->notificationSent = false;
			}
		}

		$this->contactDataSession->inquiryToken = bin2hex(random_bytes(16));

		return $this->_helper->redirector->gotoRoute([], 'contact_success', true);
	}

	public function successAction()
	{
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
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

	private function rejectSubmission(int $status, string $message, int $retryAfter = 0)
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
		$this->getResponse()->setHttpResponseCode($status);
		$this->getResponse()->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
		if ($retryAfter > 0) $this->getResponse()->setHeader('Retry-After', (string)$retryAfter, true);
		$this->getResponse()->setBody((string)$this->view->translate($message));

		return null;
	}
}
