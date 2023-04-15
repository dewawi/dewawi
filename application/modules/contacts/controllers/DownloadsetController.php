<?php

class Contacts_DownloadsetController extends Zend_Controller_Action
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
		$this->_helper->redirector->gotoSimple('index', 'download');
	}

	public function addAction()
	{
		$request = $this->getRequest();

		$form = new Contacts_Form_Downloadset();
		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($form);

		if($request->isPost()) {
			$formData = $request->getPost();
			if($form->isValid($formData)) {
				$data = array();
				$data['column'] = 1;
				$data['title'] = $formData['title'];
				$data['description'] = $formData['description'];
				$data['parentid'] = 0;
				//$data['ordering'] = $this->getLatestOrdering($id, $module, $controller) + 1;

				$downloadSet = new Contacts_Model_DbTable_Downloadset();
				$downloadSet->addDownloadset($data);
				$this->_helper->redirector->gotoSimple('index', 'download');
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

		$downloadSetDb = new Contacts_Model_DbTable_Downloadset();
		if($id) $downloadSet = $downloadSetDb->getDownloadset($id);

		//Redirect to index if there is no data
		if(!$downloadSet) {
			$this->_helper->redirector->gotoSimple('index', 'download');
			$this->_flashMessenger->addMessage('MESSAGES_NOT_FOUND');
		}

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'download', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $downloadSet['locked'], $downloadSet['lockedtime']);

			$form = new Contacts_Form_Downloadset();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$downloadSetDb->updateDownloadset($id, $data);
					echo Zend_Json::encode($downloadSetDb->getDownloadset($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $downloadSet;
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
