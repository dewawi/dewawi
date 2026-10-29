<?php

class Contacts_DownloadController extends Zend_Controller_Action
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
		$this->view->client = Zend_Registry::get('Client');
		$this->view->downloadsurl = (new DEEC_Directory())->getShortUrl($this->view->client['id']) . '/';
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'attachment', $this->_flashMessenger);
	}

	public function indexAction()
	{
		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		//Downloads
		$downloadsDb = new Contacts_Model_DbTable_Download();
		$downloads = $downloadsDb->getDownloads();

		//Download sets
		$downloadSetsDb = new Contacts_Model_DbTable_Downloadset();
		$downloadSets = $downloadSetsDb->getDownloadsets();

		//Download tracking
		$downloadtrackingsDb = new Contacts_Model_DbTable_Downloadtracking();
		$downloadtrackings = $downloadtrackingsDb->getDownloadtrackings();

		$clientid = $this->view->client['id'];
		$dir1 = substr($clientid, 0, 1);
		if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
		else $dir2 = '0';

		$documentUrl = '/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid;

		$this->view->downloads = $downloads;
		$this->view->downloadSets = $downloadSets;
		$this->view->downloadtrackings = $downloadtrackings;
		$this->view->documentUrl = $documentUrl;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$request = $this->getRequest();

		$form = new Contacts_Form_Download();
		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($form);

		if($request->isPost()) {
			$formData = $request->getPost();
			if($form->isValid($formData)) {
				//$documentUrl = $this->_helper->Directory->getUrl($this->view->client['id']);

				$clientid = $this->view->client['id'];
				$dir1 = substr($clientid, 0, 1);
				if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
				else $dir2 = '0';

				$info = $this->receiveFile(BASE_PATH.'/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid);
				if (!$info) return;

				$data = array();
				$data['setid'] = $formData['setid'];
				$data['title'] = $formData['title'];
				$data['filename'] = $info['name'];
				$data['filesize'] = $info['size'];
				$data['parentid'] = 0;
				//$data['ordering'] = $this->getLatestOrdering($id, $module, $controller) + 1;

				$download = new Contacts_Model_DbTable_Download();
				$download->addDownload($data);
				$this->_helper->redirector('index');
			} else {
				$form->populate($formData);
			}
		}
		$this->view->form = $form;
		$this->view->toolbar = $toolbar;
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$downloadDb = new Contacts_Model_DbTable_Download();
		if($id) $download = $downloadDb->getDownload($id);

		//Redirect to index if there is no data
		if(!$download) {
			$this->_helper->redirector->gotoSimple('index', 'download');
			$this->_flashMessenger->addMessage('MESSAGES_NOT_FOUND');
		}

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'download', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $download['locked'], $download['lockedtime']);

			$form = new Contacts_Form_Download();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$data = $request->getPost();
				if(isset($data['MAX_FILE_SIZE'])) {
					$clientid = $this->view->client['id'];
					$dir1 = substr($clientid, 0, 1);
					if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
					else $dir2 = '0';

					$info = $this->receiveFile(BASE_PATH.'/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid);
					if (!$info) return;

					$updateData = array();
					$updateData['setid'] = $data['setid'];
					$updateData['title'] = $data['title'];
					$updateData['filename'] = $info['name'];
					$updateData['filesize'] = $info['size'];
					$updateData['parentid'] = 0;
					//$updateData['ordering'] = $this->getLatestOrdering($id, $module, $controller) + 1;

					$downloadDb->updateDownload($id, $updateData);
					$this->_helper->redirector->gotoSimple('edit', 'download', null, array('id' => $id));
				} else {
					$this->_helper->viewRenderer->setNoRender();
					$this->_helper->getHelper('layout')->disableLayout();
					$element = key($data);
					if(isset($form->$element) && $form->isValidPartial($data)) {
					$data = array_intersect_key($data, $form->getElements());
						$downloadDb->updateDownload($id, array_intersect_key($form->getValues(), $data));
						echo Zend_Json::encode($downloadDb->getDownload($id));
					} else {
						echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
					}
				}
			} else {
				if($id > 0) {
					$data = $download;
					$form->populate($data);

					//Toolbar
					$toolbar = new Contacts_Form_Toolbar();

					$this->view->form = $form;
					$this->view->options = $options;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
				}
			}
		}
		$this->view->messages = array_merge(
			$this->_helper->flashMessenger->getMessages(),
			$this->_helper->flashMessenger->getCurrentMessages()
		);
		$this->_helper->flashMessenger->clearCurrentMessages();
	}

	protected function getUploadAdapter()
	{
		return new Zend_File_Transfer_Adapter_Http();
	}

	protected function receiveFile(string $directory): ?array
	{
		try {
			$upload = $this->getUploadAdapter();
			$upload->addValidator('Count', false, 1);
			$upload->addValidator('Size', false, ['min' => 1, 'max' => 10240000]);
			$files = $upload->getFileInfo();
			if (count($files) !== 1) throw new InvalidArgumentException('One download file is required');
			$field = array_key_first($files);
			$info = $files[$field];
			if (!$upload->isUploaded($field) || !$upload->isValid($field) || !DEEC_Emailattachment::validUpload($info['name'], $info['tmp_name'])) throw new InvalidArgumentException('Invalid download file');
			$target = $directory . '/' . $info['name'];
			DEEC_Emailattachment::checkPath($target);
			if (file_exists($target)) throw new RuntimeException('Download file already exists');
			if (!is_dir($directory) && !mkdir($directory, 0775, true)) throw new RuntimeException('Download directory unavailable');
			$upload->setDestination($directory);
			if (!$upload->receive($field)) throw new RuntimeException('Download upload failed');
			return ['name' => $info['name'], 'size' => filesize($target)];
		} catch (Exception $exception) {
			error_log((string)$exception);
			$this->getResponse()->setHttpResponseCode(400);
			$this->_flashMessenger->addMessage('MESSAGES_FORM_IS_INVALID');
			return null;
		}
	}
}
