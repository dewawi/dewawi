<?php

class Contacts_AttachmentController extends Zend_Controller_Action
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

		$this->view->id = isset($params['id']) ? (int) $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function indexAction()
	{
		if ($this->getRequest()->isPost()) {
			$this->_helper->getHelper('layout')->disableLayout();
		}

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Contacts_Model_Get();
		list($contacts, $records) = $get->contacts($params, $options);

		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function uploadAction()
	{
		$id = (int) $this->_getParam('id', 0);
		$module = (string) $this->_getParam('cmodule', '');
		$controller = (string) $this->_getParam('ccontroller', '');

		$this->_helper->getHelper('layout')->setLayout('plain');

		$form = new Application_Form_Upload();

		$this->view->form = $form;
		$this->view->uploads = [];
		$this->view->documentUrl = '';

		if (!$this->getRequest()->isPost()) {
			return;
		}

		if ($id <= 0 || $module === '' || $controller === '') {
			return;
		}

		$documentUrl = $this->_helper->Directory->getUrl($id);
		$destination = BASE_PATH . '/files/attachments/' . $module . '/' . $controller . '/' . $documentUrl . '/';

		if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
			return;
		}

		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		$upload = new Zend_File_Transfer_Adapter_Http();

		$upload->setDestination($destination);
		$upload->addValidator('Count', false, 10);
		$upload->addValidator('Size', false, 10240000);
		$upload->addValidator('Extension', false, 'pdf,jpg,jpeg,png,gif,csv,zip');

		try {
			$uploads = [];
			$files = $upload->getFileInfo();

			foreach ($files as $file => $fileInfo) {
				if (!$upload->isUploaded($file)) {
					continue;
				}

				if (!$upload->isValid($file)) {
					continue;
				}

				if (!$upload->receive($file)) {
					continue;
				}

				$info = $upload->getFileInfo($file);

				if (!isset($info[$file])) {
					continue;
				}

				if (!empty($info[$file]['error']) || empty($info[$file]['validated'])) {
					continue;
				}

				$data = [];
				$data['documentid'] = $id;
				$data['filename'] = $info[$file]['name'];
				$data['filetype'] = $info[$file]['type'];
				$data['filesize'] = $info[$file]['size'];
				$data['location'] = rtrim($info[$file]['destination'], '/');
				$data['module'] = $module;
				$data['controller'] = $controller;
				$data['ordering'] = $this->getLatestOrdering($id, $module, $controller) + 1;
				$data['id'] = $emailattachmentDb->addEmailattachment($data);

				$uploads[$data['id']] = $data;
			}

			$this->view->uploads = $uploads;
			$this->view->documentUrl = $documentUrl;
		} catch (Zend_File_Transfer_Exception $e) {
			// Keep response quiet in iframe context
		}
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if (!$this->getRequest()->isPost()) {
			return;
		}

		$body = $this->getRequest()->getRawBody();
		$data = json_decode($body, true);

		$ids = isset($data['id']) && is_array($data['id']) ? $data['id'] : [];

		if (empty($ids)) {
			return;
		}

		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();

		foreach ($ids as $id) {
			$id = (int) $id;
			if ($id <= 0) {
				continue;
			}

			$emailattachment = $emailattachmentDb->getEmailattachment($id);
			if (!$emailattachment) {
				continue;
			}

			$file = rtrim((string) $emailattachment['location'], '/') . '/' . (string) $emailattachment['filename'];

			$emailattachmentDb->deleteEmailattachment($id);

			if (is_file($file)) {
				unlink($file);
			}
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function lockAction()
	{
		$id = (int) $this->_getParam('id', 0);
		$this->_helper->Access->lock($id, $this->_user['id']);
	}

	public function unlockAction()
	{
		$id = (int) $this->_getParam('id', 0);
		$this->_helper->Access->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = (int) $this->_getParam('id', 0);
		$this->_helper->Access->keepalive($id);
	}

	public function validateAction()
	{
		$this->_helper->Validate();
	}

	protected function setOrdering($documentid)
	{
		$i = 1;
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		$emailattachments = $emailattachmentDb->getEmailattachments($documentid);

		foreach ($emailattachments as $emailattachment) {
			if ($emailattachment->ordering != $i) {
				// Keep placeholder for future reorder logic
			}
			++$i;
		}
	}

	protected function getOrdering($documentid, $module, $controller)
	{
		$i = 1;
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		$emailattachments = $emailattachmentDb->getEmailattachments($documentid, $module, $controller);

		$orderings = [];
		foreach ($emailattachments as $emailattachment) {
			$orderings[$i] = $emailattachment['id'];
			++$i;
		}

		return $orderings;
	}

	protected function getLatestOrdering($documentid, $module, $controller)
	{
		$ordering = $this->getOrdering($documentid, $module, $controller);
		end($ordering);

		$key = key($ordering);
		return $key !== null ? (int) $key : 0;
	}
}
