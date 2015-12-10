<?php

class Processes_ProcessController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	protected $_currency = null;

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

		$this->_currency = new Zend_Currency();
		if(($this->view->action != 'index') && ($this->view->action != 'select') && ($this->view->action != 'search') && ($this->view->action != 'download') && ($this->view->action != 'save') && ($this->view->action != 'preview') && ($this->view->action != 'get'))
			$this->_currency->setFormat(array('display' => Zend_Currency::NO_SYMBOL));

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function getAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$element = $this->_getParam('element', null);
		$form = new Processes_Form_Toolbar();
		if(isset($form->$element)) {
			$options = $form->$element->getMultiOptions();
			echo Zend_Json::encode($options);
		} else {
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS')));
		}
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Processes_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$processes = $this->search($params, $options['categories']);

		//Get positions
		$processIDs = array();
		foreach($processes as $process) {
			array_push($processIDs, $process['id']);
		}

		$this->view->processes = $processes;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->positions = $this->getPositions($processIDs);
		$this->view->paymentstatus = $this->_helper->PaymentStatus->getPaymentStatus();
		$this->view->deliverystatus = $this->_helper->DeliveryStatus->getDeliveryStatus();
		$this->view->supplierorderstatus = $this->_helper->SupplierOrderStatus->getSupplierOrderStatus();
		$this->view->messages = array_merge(
						$this->_flashMessenger->getMessages(),
						$this->_flashMessenger->getCurrentMessages()
						);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Processes_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$processes = $this->search($params, $options['categories']);

		//Get positions
		$processIDs = array();
		foreach($processes as $process) {
			array_push($processIDs, $process['id']);
		}

		$this->view->processes = $processes;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->positions = $this->getPositions($processIDs);
		$this->view->paymentstatus = $this->_helper->PaymentStatus->getPaymentStatus();
		$this->view->deliverystatus = $this->_helper->DeliveryStatus->getDeliveryStatus();
		$this->view->supplierorderstatus = $this->_helper->SupplierOrderStatus->getSupplierOrderStatus();
		$this->view->messages = array_merge(
						$this->_flashMessenger->getMessages(),
						$this->_flashMessenger->getCurrentMessages()
						);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function addAction()
	{
		$customerid = $this->_getParam('customerid', 0);

		$data = array();
		$data['customerid'] = $customerid;
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_user['clientid'];

		$processDb = new Processes_Model_DbTable_Process();
		$id = $processDb->addProcess($data);

		$this->_helper->redirector->gotoSimple('edit', 'process', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);//		$element = $this->_getParam('element', null);
		$activeTab = $request->getCookie('tab', null);

		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);

		if($process['completed'] || $process['cancelled']) {
			$this->_helper->redirector->gotoSimple('view', 'process', null, array('id' => $id));
		} elseif($this->isLocked($process['locked'], $process['lockedtime'])) {
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
			$processDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Processes_Form_Process();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($process['customerid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContact($process['customerid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($process['customerid']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($process['customerid']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($process['customerid']);

				$this->view->contact = $contact;
			}

			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$data['contactperson'] = $this->_user['name'];
					$data['modified'] = $this->_date;
					$data['modifiedby'] = $this->_user['id'];
					if(isset($data['taxfree'])) {
						$calculations = $this->_helper->Calculate($id, $this->_currency, $this->_date, $this->_user['id'], $data['taxfree']);
						$data['subtotal'] = $calculations['subtotal'];
						$data['taxes'] = $calculations['taxes'];
						$data['total'] = $calculations['total'];
					}
					if(isset($data['total'])) {
						$data['total'] =  Zend_Locale_Format::getNumber($data['total'], array('precision' => 2,'locale' => $locale));
					}
					if(isset($data['supplierinvoicetotal'])) {
						$data['supplierinvoicetotal'] =  Zend_Locale_Format::getNumber($data['supplierinvoicetotal'], array('precision' => 2,'locale' => $locale));
					}
					if(isset($data['prepaymenttotal'])) {
						$data['prepaymenttotal'] =  Zend_Locale_Format::getNumber($data['prepaymenttotal'], array('precision' => 2,'locale' => $locale));
					}
					$processDb->updateProcess($id, $data);
					echo Zend_Json::encode($processDb->getProcess($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $process;
					if($process['customerid']) {
						$data['customerinfo'] = $contact['info'];
						$form->customerinfo->setAttrib('data-id', $contact['id']);
						$form->customerinfo->setAttrib('data-controller', 'contact');
						$form->customerinfo->setAttrib('data-module', 'contacts');
						$form->customerinfo->setAttrib('readonly', null);
					}
					$data['total'] = $this->_currency->toCurrency($data['total']);
					$data['prepaymenttotal'] = $this->_currency->toCurrency($data['prepaymenttotal']);
					$data['creditnotetotal'] = $this->_currency->toCurrency($data['creditnotetotal']);
					if($process['editpositionsseparately']) {
						$form->deliverystatus->setAttrib('disabled', 'disabled');
						$form->shippingmethod->setAttrib('disabled', 'disabled');
						$form->deliverydate->setAttrib('disabled', 'disabled');
						$form->shipmentdate->setAttrib('disabled', 'disabled');
						$form->shipmentnumber->setAttrib('disabled', 'disabled');
						$form->deliveryorderid->setAttrib('disabled', 'disabled');
						$form->deliveryorderdate->setAttrib('disabled', 'disabled');
						$form->supplierid->setAttrib('disabled', 'disabled');
						$form->purchaseorderid->setAttrib('disabled', 'disabled');
						$form->suppliersalesorderid->setAttrib('disabled', 'disabled');
						$form->supplierinvoiceid->setAttrib('disabled', 'disabled');
						$form->supplierinvoicetotal->setAttrib('disabled', 'disabled');
						$form->supplierorderstatus->setAttrib('disabled', 'disabled');
						$form->suppliername->setAttrib('disabled', 'disabled');
						$form->purchaseorderdate->setAttrib('disabled', 'disabled');
						$form->suppliersalesorderdate->setAttrib('disabled', 'disabled');
						$form->supplierinvoicedate->setAttrib('disabled', 'disabled');
						$form->supplierpaymentdate->setAttrib('disabled', 'disabled');
					}
					$form->populate($data);

					//Toolbar
					$toolbar = new Processes_Form_Toolbar();
					$toolbar->state->setValue($data['state']);
					$toolbarPositions = new Processes_Form_ToolbarPositions();

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
					$this->view->toolbarPositions = $toolbarPositions;
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function viewAction()
	{
		$id = $this->_getParam('id', 0);
		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);

		$process['processdate'] = date('d.m.Y', strtotime($process['processdate']));

		//Get positions
		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positionsObject = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('processid = ?', $id)
				->order('ordering')
		);
		$positions = array();
		foreach($positionsObject as $positionObject) {
			foreach($positionObject as $key => $value) {
				$positions[$positionObject->id][$key] = $value;
			}
			$positions[$positionObject->id]['price'] =  $this->_currency->toCurrency($positions[$positionObject->id]['price']);
			$positions[$positionObject->id]['quantity'] = Zend_Locale_Format::toNumber($positions[$positionObject->id]['quantity'],array('precision' => 2,'locale' => Zend_Registry::get('Zend_Locale')));
		}

		//Get units of measurement
		$uomDb = new Application_Model_DbTable_Uom();
		$uom = $uomDb->fetchAll();
		$uoms = array();
		foreach($uom as $value) {
			$uoms[$value->title] = $value->title;
		}

		$toolbar = new Processes_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->process = $process;
		$this->view->positions = $positions;
		$this->view->uoms = $uoms;
		$this->view->evaluate = $this->evaluate($positionsObject, $process['taxfree']);
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);

		$data = $process;
		unset($data['id'], $data['processid']);
		$data['title'] = $process['title'].' 2';
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['contactperson'] = $this->_user['name'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = 0;

		echo $newID = $processDb->addProcess($data);

		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('processid = ?', $id)
				->where('clientid = ?', $this->_user['clientid'])
		);
		foreach($positions as $position) {
			$positionData = $position->toArray();
			unset($positionData['id']);
			$positionData['processid'] = $newID;
			$positionData['created'] = $this->_date;
			$positionData['createdby'] = $this->_user['id'];
			$positionData['modified'] = '0000-00-00';
			$positionData['modifiedby'] = 0;
			$positionsDb->addPosition($positionData);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$process = new Processes_Model_DbTable_Process();
			$process->setState($id, 7, $this->_date, $this->_user['id']);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$process = new Processes_Model_DbTable_Process();
			$process->deleteProcess($id);

			$positionsDb = new Processes_Model_DbTable_Processpos();
			$positions = $positionsDb->fetchAll(
				$positionsDb->select()
					->where('processid = ?', $id)
			);
			foreach($positions as $position) {
				$positionsDb->deletePosition($position->id);
			}
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);
		if($this->isLocked($process['locked'], $process['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($process['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$processDb->lock($id, $this->_user['id'], $this->_date);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$processDb = new Processes_Model_DbTable_Process();
		$processDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$processDb = new Processes_Model_DbTable_Process();
		$processDb->lock($id, $this->_user['id'], $this->_date);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Processes_Form_Process();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function search($params, $categories)
	{
		$processesDb = new Processes_Model_DbTable_Process();

		$columns = array('p.title', 'p.customerid', 'p.billingname1', 'p.billingname2', 'p.billingdepartment', 'p.billingstreet', 'p.billingpostcode', 'p.billingcity', 'p.shippingname1', 'p.shippingname2', 'p.shippingdepartment', 'p.shippingstreet', 'p.shippingpostcode', 'p.shippingcity');

		$query = '';
		$schema = 'p';
		if($params['keyword']) $query = $this->_helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $this->_helper->Query->getQueryCategory($query, $params['catid'], $categories, 'c');
		if($params['states']) $query = $this->_helper->Query->getQueryStates($query, $params['states'], $schema);
		if($params['daterange']) $query = $this->_helper->Query->getQueryDaterange($query, $params['from'], $params['to'], $schema);
		if($params['country']) $query = $this->_helper->Query->getQueryCountry($query, $params['country'], $schema);
		if($params['paymentstatus']) $query = $this->_helper->Query->getQueryPaymentstatus($query, $params['paymentstatus'], $schema);

		if($params['catid']) {
			$processes = $processesDb->fetchAll(
				$processesDb->select()
					->setIntegrityCheck(false)
					->from(array('p' => 'process'))
					->join(array('c' => 'contact'), 'p.customerid = c.id', array('catid'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($processes) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$processes = $processesDb->fetchAll(
					$processesDb->select()
						->setIntegrityCheck(false)
						->from(array('p' => 'process'))
						->join(array('c' => 'contact'), 'p.customerid = c.id', array('catid'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		} else {
			$processes = $processesDb->fetchAll(
				$processesDb->select()
					->setIntegrityCheck(false)
					->from(array('p' => 'process'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order($params['order'].' '.$params['sort'])
					->limit($params['limit'])
			);
			if(!count($processes) && $params['keyword']) {
				$this->_flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');
				$query = $this->_helper->Query->getQueryKeyword('', $params['keyword'], $columns);
				$processes = $processesDb->fetchAll(
					$processesDb->select()
						->setIntegrityCheck(false)
						->from(array('p' => 'process'))
						->group($schema.'.id')
						->where($query ? $query : 1)
						->order($params['order'].' '.$params['sort'])
						->limit($params['limit'])
				);
			}
		}

		$processes->subtotal = 0;
		$processes->total = 0;
		foreach($processes as $process) {
			$processes->subtotal += $process->subtotal;
			$processes->total += $process->total;
			if($process->prepayment == 0.0000) $process->prepayment = 0;
			else {
				//$process->stillToPay = $this->_currency->toCurrency($processes->subtotal-$process->prepayment);
				$process->prepayment = $this->_currency->toCurrency($process->prepayment);
			}
			if($process->total == 0.0000) $process->total = 0;
			else $process->total = $this->_currency->toCurrency($process->total);
			if($process->prepaymenttotal == 0.0000) $process->prepaymenttotal = 0;
			else $process->prepaymenttotal = $this->_currency->toCurrency($process->prepaymenttotal);
			if($process->creditnotetotal == 0.0000) $process->creditnotetotal = 0;
			else $process->creditnotetotal = $this->_currency->toCurrency($process->creditnotetotal);
		}
		$processes->total = $this->_currency->toCurrency($processes->total);

		return $processes;
	}

	protected function getPositions($processIDs)
	{
		$positions = array();
		if(!empty($processIDs)) {
			$positionsDb = new Processes_Model_DbTable_Processpos();
			$positionsObject = $positionsDb->fetchAll(
				$positionsDb->select()
					->where('processid IN (?)', $processIDs)
					->order('ordering')
			);

			foreach($positionsObject as $position) {
				if(!isset($previous[$position->processid])) {
					$previous[$position->processid] = array();
					$previous[$position->processid]['ordering'] = 0;
					$previous[$position->processid]['quantity'] = 1;
					$previous[$position->processid]['deliverystatus'] = '';
					$previous[$position->processid]['deliverydate'] = '0000-00-00';
					$previous[$position->processid]['supplierorderstatus'] = '';
				}
				if($previous[$position->processid]['ordering'] && ($previous[$position->processid]['deliverystatus'] == $position->deliverystatus) && ($previous[$position->processid]['deliverydate'] == $position->deliverydate) && ($previous[$position->processid]['supplierorderstatus'] == $position->supplierorderstatus)) {
					$positions[$position->processid][$position->ordering] = $positions[$position->processid][$previous[$position->processid]['ordering']];
					$positions[$position->processid][$position->ordering]['quantity'] = ($previous[$position->processid]['quantity'] + 1);
					unset($positions[$position->processid][$previous[$position->processid]['ordering']]);
					$previous[$position->processid]['ordering'] = $position->ordering ? $position->ordering : 0;
					$previous[$position->processid]['quantity'] = $positions[$position->processid][$position->ordering]['quantity'];
					$previous[$position->processid]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
					$previous[$position->processid]['deliverydate'] = $position->deliverydate ? $position->deliverydate : '0000-00-00';
					$previous[$position->processid]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
				} else {
					$positions[$position->processid][$position->ordering]['deliverystatus'] = $position->deliverystatus;
					if($position->deliverydate != '0000-00-00')
						$positions[$position->processid][$position->ordering]['deliverydate'] = $position->deliverydate;
					if($position->itemtype == 'deliveryItem')
						$positions[$position->processid][$position->ordering]['supplierorderstatus'] = $position->supplierorderstatus;
					$previous[$position->processid] = array();
					$previous[$position->processid]['ordering'] = $position->ordering ? $position->ordering : 0;
					$previous[$position->processid]['quantity'] = 1;
					$previous[$position->processid]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
					$previous[$position->processid]['deliverydate'] = $position->deliverydate ? $position->deliverydate : '0000-00-00';
					$previous[$position->processid]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
				}
			}
		}
		return $positions;
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
