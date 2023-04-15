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

		$clientid = $this->_user['clientid'];
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

				$clientid = $this->_user['clientid'];
				$dir1 = substr($clientid, 0, 1);
				if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
				else $dir2 = '0';

				if(!file_exists(BASE_PATH.'/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid.'/')) {
					mkdir(BASE_PATH.'/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid.'/', 0777, true);
				}

				/* Uploading Document File on Server */
				$upload = new Zend_File_Transfer_Adapter_Http();
				$upload->setDestination(BASE_PATH.'/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid.'/');

				try {
					// upload received file(s)
					$upload->receive();
				} catch (Zend_File_Transfer_Exception $e) {
					$e->getMessage();
				}
				$file = $upload->getFileName();
				$info = $upload->getFileInfo();

				$data = array();
				$data['setid'] = $formData['setid'];
				$data['title'] = $formData['title'];
				$data['filename'] = $info['file_0_']['name'];
				$data['filesize'] = filesize($file);
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
					$clientid = $this->_user['clientid'];
					$dir1 = substr($clientid, 0, 1);
					if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
					else $dir2 = '0';

					if(!file_exists(BASE_PATH.'/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid.'/')) {
						mkdir(BASE_PATH.'/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid.'/', 0777, true);
					}

					/* Uploading Document File on Server */
					$upload = new Zend_File_Transfer_Adapter_Http();
					$upload->setDestination(BASE_PATH.'/files/downloads/'.$dir1.'/'.$dir2.'/'.$clientid.'/');

					try {
						// upload received file(s)
						$upload->receive();
					} catch (Zend_File_Transfer_Exception $e) {
						$e->getMessage();
					}
					$file = $upload->getFileName();
					$info = $upload->getFileInfo();

					$updateData = array();
					$updateData['setid'] = $data['setid'];
					$updateData['title'] = $data['title'];
					$updateData['filename'] = $info['file_0_']['name'];
					$updateData['filesize'] = filesize($file);
					$updateData['parentid'] = 0;
					//$updateData['ordering'] = $this->getLatestOrdering($id, $module, $controller) + 1;

					$downloadDb->updateDownload($id, $updateData);
					$this->_helper->redirector->gotoSimple('edit', 'download', null, array('id' => $id));
				} else {
					$this->_helper->viewRenderer->setNoRender();
					$this->_helper->getHelper('layout')->disableLayout();
					$element = key($data);
					if(isset($form->$element) && $form->isValidPartial($data)) {
						$downloadDb->updateDownload($id, $data);
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
}
