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

        $get = new Processes_Model_Get();
		$processes = $get->processes($params, $options['categories'], $this->_user['clientid'], $this->_helper, $this->_currency, $this->_flashMessenger);

		//Get positions
		$processIDs = array();
		foreach($processes as $process) {
			array_push($processIDs, $process['id']);

            $deliverydate = new Zend_Date($process['deliverydate']);
            if($process['deliverydate'] == '0000-00-00') $process['deliverydate'] = '';
            else $process['deliverydate'] = $deliverydate->get('dd.MM.yyyy');
		}

		$this->view->processes = $processes;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->positions = $this->getPositions($processIDs);
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

        $get = new Processes_Model_Get();
		$processes = $get->processes($params, $options['categories'], $this->_user['clientid'], $this->_helper, $this->_currency, $this->_flashMessenger);

		//Get positions
		$processIDs = array();
		foreach($processes as $process) {
			array_push($processIDs, $process['id']);
		}

		$this->view->processes = $processes;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->positions = $this->getPositions($processIDs);
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
		$data['state'] = 100;

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
			$processDb->lock($id);

			$form = new Processes_Form_Process();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			//Get contact
			if($process['customerid']) {
				$contactDb = new Contacts_Model_DbTable_Contact();
				$contact = $contactDb->getContactWithID($process['customerid']);

				//Phone
				$phoneDb = new Contacts_Model_DbTable_Phone();
				$contact['phone'] = $phoneDb->getPhone($contact['id']);

				//Email
				$emailDb = new Contacts_Model_DbTable_Email();
				$contact['email'] = $emailDb->getEmail($contact['id']);

				//Internet
				$internetDb = new Contacts_Model_DbTable_Internet();
				$contact['internet'] = $internetDb->getInternet($contact['id']);

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
					if(isset($data['taxfree'])) {
						$calculations = $this->_helper->Calculate($id, $this->_currency, $this->_date, $this->_user['id'], $data['taxfree']);
						$data['subtotal'] = $calculations['row']['subtotal'];
						$data['taxes'] = $calculations['row']['taxes']['total'];
						$data['total'] = $calculations['row']['total'];
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
					if(isset($data['paymentdate'])) {
                        if(Zend_Date::isDate($data['paymentdate'])) {
                            $paymentdate = new Zend_Date($data['paymentdate'], Zend_Date::DATES, 'de');
                            $data['paymentdate'] = $paymentdate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['invoicedate'])) {
                        if(Zend_Date::isDate($data['invoicedate'])) {
                            $invoicedate = new Zend_Date($data['invoicedate'], Zend_Date::DATES, 'de');
                            $data['invoicedate'] = $invoicedate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['prepaymentdate'])) {
                        if(Zend_Date::isDate($data['prepaymentdate'])) {
                            $prepaymentdate = new Zend_Date($data['prepaymentdate'], Zend_Date::DATES, 'de');
                            $data['prepaymentdate'] = $prepaymentdate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['prepaymentinvoicedate'])) {
                        if(Zend_Date::isDate($data['prepaymentinvoicedate'])) {
                            $prepaymentinvoicedate = new Zend_Date($data['prepaymentinvoicedate'], Zend_Date::DATES, 'de');
                            $data['prepaymentinvoicedate'] = $prepaymentinvoicedate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['creditnotedate'])) {
                        if(Zend_Date::isDate($data['creditnotedate'])) {
                            $creditnotedate = new Zend_Date($data['creditnotedate'], Zend_Date::DATES, 'de');
                            $data['creditnotedate'] = $creditnotedate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['deliverydate'])) {
                        if(Zend_Date::isDate($data['deliverydate'])) {
                            $deliverydate = new Zend_Date($data['deliverydate'], Zend_Date::DATES, 'de');
                            $data['deliverydate'] = $deliverydate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['deliveryorderdate'])) {
                        if(Zend_Date::isDate($data['deliveryorderdate'])) {
                            $deliveryorderdate = new Zend_Date($data['deliveryorderdate'], Zend_Date::DATES, 'de');
                            $data['deliveryorderdate'] = $deliveryorderdate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['purchaseorderdate'])) {
                        if(Zend_Date::isDate($data['purchaseorderdate'])) {
                            $purchaseorderdate = new Zend_Date($data['purchaseorderdate'], Zend_Date::DATES, 'de');
                            $data['purchaseorderdate'] = $purchaseorderdate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['suppliersalesorderdate'])) {
                        if(Zend_Date::isDate($data['suppliersalesorderdate'])) {
                            $suppliersalesorderdate = new Zend_Date($data['suppliersalesorderdate'], Zend_Date::DATES, 'de');
                            $data['suppliersalesorderdate'] = $suppliersalesorderdate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['supplierinvoicedate'])) {
                        if(Zend_Date::isDate($data['supplierinvoicedate'])) {
                            $supplierinvoicedate = new Zend_Date($data['supplierinvoicedate'], Zend_Date::DATES, 'de');
                            $data['supplierinvoicedate'] = $supplierinvoicedate->get('yyyy-MM-dd');
					    }
					}
					if(isset($data['supplierpaymentdate'])) {
                        if(Zend_Date::isDate($data['supplierpaymentdate'])) {
                            $supplierpaymentdate = new Zend_Date($data['supplierpaymentdate'], Zend_Date::DATES, 'de');
                            $data['supplierpaymentdate'] = $supplierpaymentdate->get('yyyy-MM-dd');
					    }
					}

                    //Update file manager subfolder if contact is changed
                    if(isset($data['contactid']) && $data['contactid']) {
                        $dir1 = substr($data['contactid'], 0, 1).'/';
                        if(strlen($data['contactid']) > 1) $dir2 = substr($data['contactid'], 1, 1).'/';
                        else $dir2 = '0/';
                        $defaultNamespace = new Zend_Session_Namespace('RF');
                        $defaultNamespace->subfolder = 'contacts/'.$dir1.$dir2.$data['contactid'].'/';
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
                    //Convert dates to the display format
                    $paymentdate = new Zend_Date($data['paymentdate']);
                    if($data['paymentdate'] == '0000-00-00') $data['paymentdate'] = '';
                    else $data['paymentdate'] = $paymentdate->get('dd.MM.yyyy');
                    $invoicedate = new Zend_Date($data['invoicedate']);
                    if($data['invoicedate'] == '0000-00-00') $data['invoicedate'] = '';
                    else $data['invoicedate'] = $invoicedate->get('dd.MM.yyyy');
                    $prepaymentdate = new Zend_Date($data['prepaymentdate']);
                    if($data['prepaymentdate'] == '0000-00-00') $data['prepaymentdate'] = '';
                    else $data['prepaymentdate'] = $prepaymentdate->get('dd.MM.yyyy');
                    $prepaymentinvoicedate = new Zend_Date($data['prepaymentinvoicedate']);
                    if($data['prepaymentinvoicedate'] == '0000-00-00') $data['prepaymentinvoicedate'] = '';
                    else $data['prepaymentinvoicedate'] = $prepaymentinvoicedate->get('dd.MM.yyyy');
                    $creditnotedate = new Zend_Date($data['creditnotedate']);
                    if($data['creditnotedate'] == '0000-00-00') $data['creditnotedate'] = '';
                    else $data['creditnotedate'] = $creditnotedate->get('dd.MM.yyyy');
                    $deliverydate = new Zend_Date($data['deliverydate']);
                    if($data['deliverydate'] == '0000-00-00') $data['deliverydate'] = '';
                    else $data['deliverydate'] = $deliverydate->get('dd.MM.yyyy');
                    $deliveryorderdate = new Zend_Date($data['deliveryorderdate']);
                    if($data['deliveryorderdate'] == '0000-00-00') $data['deliveryorderdate'] = '';
                    else $data['deliveryorderdate'] = $deliveryorderdate->get('dd.MM.yyyy');
                    $purchaseorderdate = new Zend_Date($data['purchaseorderdate']);
                    if($data['purchaseorderdate'] == '0000-00-00') $data['purchaseorderdate'] = '';
                    else $data['purchaseorderdate'] = $purchaseorderdate->get('dd.MM.yyyy');
                    $suppliersalesorderdate = new Zend_Date($data['suppliersalesorderdate']);
                    if($data['suppliersalesorderdate'] == '0000-00-00') $data['suppliersalesorderdate'] = '';
                    else $data['suppliersalesorderdate'] = $suppliersalesorderdate->get('dd.MM.yyyy');
                    $supplierinvoicedate = new Zend_Date($data['supplierinvoicedate']);
                    if($data['supplierinvoicedate'] == '0000-00-00') $data['supplierinvoicedate'] = '';
                    else $data['supplierinvoicedate'] = $supplierinvoicedate->get('dd.MM.yyyy');
                    $supplierpaymentdate = new Zend_Date($data['supplierpaymentdate']);
                    if($data['supplierpaymentdate'] == '0000-00-00') $data['supplierpaymentdate'] = '';
                    else $data['supplierpaymentdate'] = $supplierpaymentdate->get('dd.MM.yyyy');

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

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($process['contactid']);

		$process['processdate'] = date('d.m.Y', strtotime($process['processdate']));

		//Get positions
		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positionsObject = $positionsDb->getPositions($id);
		$positions = array();
		foreach($positionsObject as $positionObject) {
			foreach($positionObject as $key => $value) {
				$positions[$positionObject->id][$key] = $value;
			}
			$positions[$positionObject->id]['price'] =  $this->_currency->toCurrency($positions[$positionObject->id]['price']);
			$positions[$positionObject->id]['quantity'] = Zend_Locale_Format::toNumber($positions[$positionObject->id]['quantity'],array('precision' => 2,'locale' => Zend_Registry::get('Zend_Locale')));
		}

		$toolbar = new Processes_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		$this->view->process = $process;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
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
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = 0;

		echo $newID = $processDb->addProcess($data);

		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$positionData = $position->toArray();
			unset($positionData['id']);
			$positionData['processid'] = $newID;
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
			$process->setState($id, 7);
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
			$positions = $positionsDb->getPositions($id);
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
			$processDb->lock($id);
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
		$processDb->lock($id);
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

	protected function getPositions($processIDs)
	{
		$positions = array();
		if(!empty($processIDs)) {
			$positionsDb = new Processes_Model_DbTable_Processpos();
		    $positionsObject = $positionsDb->getPositions($processIDs);

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
                        //$deliverydate = new Zend_Date($position->deliverydate);
                        //if($position->deliverydate == '0000-00-00') $position->deliverydate = '';
                        //else $position->deliverydate = $deliverydate->get('dd.MM.yyyy');
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
