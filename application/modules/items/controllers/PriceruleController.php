<?php

class Items_PriceruleController extends Zend_Controller_Action
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

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$pricerules = $get->pricerules($params, $options['categories']);

		$this->view->pricerules = $pricerules;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$pricerules = $get->pricerules($params, $options['categories']);

		$this->view->pricerules = $pricerules;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function selectAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$pricerules = $get->pricerules($params, $options['categories']);

		$this->view->pricerules = $pricerules;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$data = array();

		$pricerule = new Items_Model_DbTable_Pricerule();
		$id = $pricerule->addPricerule($data);

		$this->_helper->redirector->gotoSimple('edit', 'pricerule', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$priceruleDb = new Items_Model_DbTable_Pricerule();
		$pricerule = $priceruleDb->getPricerule($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'pricerule', null, array('id' => $id));
		} elseif($this->isLocked($pricerule['locked'], $pricerule['lockedtime'])) {
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_LOCKED')));
			} else {
				$this->_flashMessenger->addMessage('MESSAGES_LOCKED');
				$this->_helper->redirector('index');
			}
		} else {
			$priceruleDb->lock($id);

			$form = new Items_Form_Pricerule();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					if(array_key_exists('amount', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['amount'] = Zend_Locale_Format::getNumber($data['amount'],array('precision' => 2,'locale' => $locale));
					}
					if(isset($data['from'])) {
						if(Zend_Date::isDate($data['from'])) {
							$from = new Zend_Date($data['from'], Zend_Date::DATES, 'de');
							$data['from'] = $from->get('yyyy-MM-dd');
						}
					}
					if(isset($data['to'])) {
						if(Zend_Date::isDate($data['to'])) {
							$to = new Zend_Date($data['to'], Zend_Date::DATES, 'de');
							$data['to'] = $to->get('yyyy-MM-dd');
						}
					}
					$priceruleDb->updatePricerule($id, $data);
					echo Zend_Json::encode($priceruleDb->getPricerule($id));
				} else {
					throw new Exception('Form is invalid');
				}
			} else {
				if($id > 0) {
					$currency = $this->_helper->Currency->getCurrency($pricerule['currency']);
					$pricerule['amount'] = $currency->toCurrency($pricerule['amount']);

					//Convert dates to the display format
					$from = new Zend_Date($pricerule['from']);
					if($pricerule['from']) $pricerule['from'] = $from->get('dd.MM.yyyy');
					$to = new Zend_Date($pricerule['to']);
					if($pricerule['to']) $pricerule['to'] = $to->get('dd.MM.yyyy');

					$form->populate($pricerule);

					//Toolbar
					$toolbar = new Items_Form_Toolbar();

					$this->view->form = $form;
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

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$pricerule = new Items_Model_DbTable_Pricerule();
		$data = $pricerule->getPricerule($id);
		unset($data['id']);
		$data['activated'] = 0;
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		echo $priceruleid = $pricerule->addPricerule($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$pricerule = new Items_Model_DbTable_Pricerule();
			$pricerule->deletePricerule($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$priceruleDb = new Items_Model_DbTable_Pricerule();
		$pricerule = $priceruleDb->getPricerule($id);
		if($this->isLocked($pricerule['locked'], $pricerule['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($pricerule['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$priceruleDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$priceruleDb = new Items_Model_DbTable_Pricerule();
		$priceruleDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$priceruleDb = new Items_Model_DbTable_Pricerule();
		$priceruleDb->lock($id);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Items_Form_Pricerule();
		$options = $this->_helper->Options->getOptions($form);

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function isLocked($locked, $lockedtime)
	{
		if($locked && ($locked != $this->_user['id'])) {
			$timeout = strtotime($lockedtime) + 300; // 5 minutes
			$timestamp = strtotime($this->_date);
			if($timeout < $timestamp) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}
}
