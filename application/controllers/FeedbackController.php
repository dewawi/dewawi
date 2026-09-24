<?php

class FeedbackController extends Zend_Controller_Action
{
	public function init()
	{
		$this->_helper->layout->disableLayout();
		$this->getResponse()->setHeader('Referrer-Policy', 'no-referrer', true);
		$this->getResponse()->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
	}

	public function indexAction()
	{
		$token = trim((string)$this->_getParam('token', ''));
		$service = new DEEC_Feedback();
		$feedback = $service->getPublic($token);

		$this->view->valid = (bool)$feedback;
		$this->view->submitted = !empty($feedback['responded']);
		$this->view->error = false;
		$this->view->token = $token;
		$this->view->status = '';
		$this->view->reason = '';
		$this->view->message = '';
		$this->view->config = [];

		if(!$feedback) {
			$this->getResponse()->setHttpResponseCode(404);
			return;
		}

		$config = $service->getTypeConfig((string)$feedback['type']);
		if(!$config) {
			$this->getResponse()->setHttpResponseCode(404);
			$this->view->valid = false;
			return;
		}

		$this->view->config = $config;
		$preselected = trim((string)$this->_getParam('status', ''));
		if(isset($config['statuses'][$preselected])) $this->view->status = $preselected;

		if(!$this->getRequest()->isPost() || $this->view->submitted) return;

		$status = trim((string)$this->_getParam('status', ''));
		$reason = trim((string)$this->_getParam('reason', ''));
		$message = trim((string)$this->_getParam('message', ''));

		$this->view->status = $status;
		$this->view->reason = $reason;
		$this->view->message = $message;

		try {
			$feedback = $service->submit($token, $status, $reason, $message);
			$this->view->submitted = !empty($feedback['responded']);
		} catch(InvalidArgumentException $e) {
			$this->view->error = true;
		}
	}
}
