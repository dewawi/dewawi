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

		$get = new Contacts_Model_Get();
		$contacts = $get->contacts($params, $options['categories']);

		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	protected function uploadAction()
	{
		$id = $this->_getParam('id', 0);
		$module = $this->_getParam('cmodule', 0);
		$controller = $this->_getParam('ccontroller', 0);

		$this->_helper->getHelper('layout')->setLayout('plain');

		$form = new Application_Form_Upload();

		if($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if($form->isValid($formData)) {
				$documentUrl = $this->_helper->Directory->getUrl($id);
				$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();

				/* Uploading Document File on Server */
				$upload = new Zend_File_Transfer_Adapter_Http();
				$upload->setDestination(BASE_PATH.'/files/attachments/'.$module.'/'.$controller.'/'.$documentUrl.'/');
				try {
					// upload received file(s)
					$uploads = array();
					$files  = $upload->getFileInfo();
					foreach($files as $file => $fileInfo) {
						if($upload->isUploaded($file)) {
							if($upload->isValid($file)) {
								if($upload->receive($file)) {
									$info = $upload->getFileInfo($file);
									if(!$info[$file]['error'] && $info[$file]['validated']) {
										//Add the file to the database
										$data = array();
										$data['documentid'] = $id;
										$data['filename'] = $info[$file]['name'];
										$data['filetype'] = $info[$file]['type'];
										$data['filesize'] = $info[$file]['size'];
										$data['location'] = $info[$file]['destination'];
										$data['module'] = $module;
										$data['controller'] = $controller;
										$data['ordering'] = $this->getLatestOrdering($id, $module, $controller) + 1;
										$data['id'] = $emailattachmentDb->addEmailattachment($data);

										$uploads[$data['id']]  = $data;
									}
								}
							}
						}
					}
					$this->view->uploads = $uploads;
					$this->view->documentUrl = $documentUrl;
				} catch (Zend_File_Transfer_Exception $e) {
					$e->getMessage();
				}
			} else {
				$form->populate($formData);
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


			$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
			$emailattachmentDb->deleteEmailattachment($id);

			/*if($id && ((BASE_PATH.'/files/attachments/'.$module.'/'.$controller.'/'.$url.'/'));
			unlink('test.html');*/
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function lockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->lock($id, $this->_user['id']);
	}

	public function unlockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
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
		foreach($emailattachments as $emailattachment) {
			if($emailattachment->ordering != $i) {
				//$positionsDb->sortPosition($position->id, $i);
			}
			++$i;
		}
	}

	protected function getOrdering($documentid, $module, $controller)
	{
		$i = 1;
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		$emailattachments = $emailattachmentDb->getEmailattachments($documentid, $module, $controller);
		$orderings = array();
		foreach($emailattachments as $emailattachment) {
			$orderings[$i] = $emailattachment['id'];
			++$i;
		}
		return $orderings;
	}

	protected function getLatestOrdering($documentid, $module, $controller)
	{
		$ordering = $this->getOrdering($documentid, $module, $controller);
		end($ordering);
		return key($ordering);
	}
}
