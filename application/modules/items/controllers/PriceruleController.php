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
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $pricerule['locked'], $pricerule['lockedtime']);

			$form = new Items_Form_Pricerule();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					if(array_key_exists('amount', $data)) {
						if($data['amount']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['amount'] = Zend_Locale_Format::getNumber($data['amount'],array('precision' => 2,'locale' => $locale));
						} else {
							$data['amount'] = NULL;
						}
					}
					if(isset($data['amountmin'])) {
						if($data['amountmin']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['amountmin'] = Zend_Locale_Format::getNumber($data['amountmin'],array('precision' => 2,'locale' => $locale));
						} else {
							$data['amountmin'] = NULL;
						}
					}
					if(isset($data['amountmax'])) {
						if($data['amountmax']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['amountmax'] = Zend_Locale_Format::getNumber($data['amountmax'],array('precision' => 2,'locale' => $locale));
						} else {
							$data['amountmax'] = NULL;
						}
					}
					if(isset($data['pricefrom'])) {
						if($data['pricefrom']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['pricefrom'] = Zend_Locale_Format::getNumber($data['pricefrom'],array('precision' => 2,'locale' => $locale));
						} else {
							$data['pricefrom'] = NULL;
						}
					}
					if(isset($data['priceto'])) {
						if($data['priceto']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['priceto'] = Zend_Locale_Format::getNumber($data['priceto'],array('precision' => 2,'locale' => $locale));
						} else {
							$data['priceto'] = NULL;
						}
					}
					if(isset($data['datefrom'])) {
						if(Zend_Date::isDate($data['datefrom'])) {
							$datefrom = new Zend_Date($data['datefrom'], Zend_Date::DATES, 'de');
							$data['datefrom'] = $datefrom->get('yyyy-MM-dd');
						}
					}
					if(isset($data['dateto'])) {
						if(Zend_Date::isDate($data['dateto'])) {
							$dateto = new Zend_Date($data['dateto'], Zend_Date::DATES, 'de');
							$data['dateto'] = $dateto->get('yyyy-MM-dd');
						}
					}
					$priceruleDb->updatePricerule($id, $data);
					echo Zend_Json::encode($priceruleDb->getPricerule($id));
				} else {
					throw new Exception('Form is invalid');
				}
			} else {
				if($id > 0) {
					$currency = $this->_helper->Currency->getCurrency('EUR');
					//$currency = $this->_helper->Currency->getCurrency($pricerule['currency']);
					$pricerule['amount'] = $currency->toCurrency($pricerule['amount']);
					if($pricerule['amountmin']) $pricerule['amountmin'] = $currency->toCurrency($pricerule['amountmin']);
					if($pricerule['amountmax']) $pricerule['amountmax'] = $currency->toCurrency($pricerule['amountmax']);
					if($pricerule['pricefrom']) $pricerule['pricefrom'] = $currency->toCurrency($pricerule['pricefrom']);
					if($pricerule['priceto']) $pricerule['priceto'] = $currency->toCurrency($pricerule['priceto']);

					//Convert dates to the display format
					$datefrom = new Zend_Date($pricerule['datefrom']);
					if($pricerule['datefrom']) $pricerule['datefrom'] = $datefrom->get('dd.MM.yyyy');
					$dateto = new Zend_Date($pricerule['dateto']);
					if($pricerule['dateto']) $pricerule['dateto'] = $dateto->get('dd.MM.yyyy');

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
}
