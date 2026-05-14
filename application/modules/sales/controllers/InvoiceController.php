<?php

class Sales_InvoiceController extends DEEC_Controller_DocumentAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'invoices',
			'list' => 'Sales_Model_List_Invoices',
			'entity' => Sales_Model_Entity_Invoice::listConfig(),
		]);
	}

	public function addAction()
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Sales_Service_CreateDataFactory();
		$data = $factory->build($controller, $contactId);

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$id = $invoiceDb->addInvoice($data);

		return $this->_helper->redirector->gotoSimple('edit', 'invoice', null, ['id' => $id]);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$invoice = $this->requireRow($id);

		if ($this->isReadonlyState($invoice)) {
			return $this->_helper->redirector->gotoSimple('view', 'invoice', null, ['id' => $id]);
		}

		$invoiceDb = new Sales_Model_DbTable_Invoice();

		$this->_helper->Access->lock($id, $this->_user['id'], $invoice['locked'] ?? 0, $invoice['lockedtime'] ?? null);

		$formFactory = new Sales_Service_EditFormFactory();
		$formData = $formFactory->create('Sales_Form_Invoice');
		$form = $formData['form'];
		$options = $formData['options'];
		$toolbar = new Sales_Form_Toolbar();

		if ($request->isPost()) {
			$this->_helper->Calculate($id, $this->_date, $this->_user['id'], $invoice['taxfree']);

			if ($isAjax) {
				$this->disableView();

				$ajaxSaveService = new Sales_Service_EditAjaxSaveService();

				return $this->_helper->json($ajaxSaveService->save([
					'form' => $form,
					'post' => (array)$request->getPost(),
					'id' => $id,
					'db' => $invoiceDb,
				]));
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				if (isset($values['currency'])) {
					$positionsDb = new Sales_Model_DbTable_Invoicepos();
					$positions = $positionsDb->getPositions($id);

					foreach ($positions as $position) {
						$positionsDb->updatePosition($position->id, ['currency' => $values['currency']]);
					}
				}

				if (isset($values['taxfree'])) {
					$calculations = $this->_helper->Calculate($id, $this->_date, $this->_user['id'], $values['taxfree']);
					$values['subtotal'] = $calculations['row']['subtotal'];
					$values['taxes'] = $calculations['row']['taxes']['total'];
					$values['total'] = $calculations['row']['total'];
				}

				$invoiceDb->updateInvoice($id, $values);
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'invoice', null, ['id' => $id]);
			}
		} else {
			$formFactory->populate($form, $invoice, $id, 'invoices', 'invoice');
		}

		$vmService = new Sales_Service_InvoiceEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$invoice);

		$this->view->assign(array_merge($vm, [
			'id' => $id,
			'form' => $form,
			'toolbar' => $toolbar,
			'options' => $options,
			'activeTab' => $request->getCookie('tab', null),
		]));

		$this->assignMessages();
	}

	public function viewAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$controller = $this->getRequest()->getControllerName();

		$invoice = $this->requireRow($id);

		$this->ensurePdfDocumentExists($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$invoice['contactid']);

		$emailFormFactory = new Sales_Service_EmailFormFactory();
		$attachmentService = new Sales_Service_AttachmentService();
		$readonlyFormFactory = new Sales_Service_ReadonlyFormFactory();

		$this->view->assign([
			'invoice' => $invoice,
			'contact' => $contact,
			'emailForm' => $emailFormFactory->build($invoice, $contact, $controller),
			'form' => $readonlyFormFactory->build('Sales_Form_Invoice', $invoice, Zend_Registry::get('Zend_Locale')),
			'toolbar' => new Sales_Form_Toolbar(),
		] + $attachmentService->sync($invoice, $contact, $controller));

		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);

		$data = $this->requireRow($id);

		$this->disableView();

		unset($data['id'], $data['invoiceid']);
		$data['title'] = $data['title'].' 2';
		$data['invoicedate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoiceid = $invoiceDb->addInvoice($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $invoiceid, 'sales', 'invoice', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');

		echo (int)$invoiceid;
	}

	public function generateAction()
	{
		$id = $this->_getParam('id', 0);
		$target = $this->_getParam('target', 0);

		$data = $this->requireRow($id);

		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		if($target == 'quote') {
			unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['deliverydate'], $data['prepayment'], $data['ebayorderid']);
			$module = 'sales';
		} elseif($target == 'salesorder') {
			unset($data['id'], $data['invoiceid'], $data['invoicedate'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['deliverydate'], $data['prepayment'], $data['ebayorderid']);
			$module = 'sales';
		} elseif($target == 'deliveryorder') {
			unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['prepayment'], $data['ebayorderid']);
			$module = 'sales';
		} elseif($target == 'creditnote') {
			unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['prepayment'], $data['ebayorderid']);
			$module = 'sales';
		} elseif($target == 'quoterequest') {
			$data['billingname1'] = '';
			$data['billingname2'] = '';
			$data['billingdepartment'] = '';
			$data['billingstreet'] = '';
			$data['billingpostcode'] = '';
			$data['billingcity'] = '';
			$data['billingcountry'] = '';
			if(!$data['shippingname1']) {
				$data['shippingname1'] = $data['billingname1'];
				$data['shippingname2'] = $data['billingname2'];
				$data['shippingdepartment'] = $data['billingdepartment'];
				$data['shippingstreet'] = $data['billingstreet'];
				$data['shippingpostcode'] = $data['billingpostcode'];
				$data['shippingcity'] = $data['billingcity'];
				$data['shippingcountry'] = $data['billingcountry'];
				$data['shippingphone'] = '';
			}
			unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['prepayment'], $data['ebayorderid']);
			$module = 'purchases';
		} elseif($target == 'purchaseorder') {
			$data['billingname1'] = '';
			$data['billingname2'] = '';
			$data['billingdepartment'] = '';
			$data['billingstreet'] = '';
			$data['billingpostcode'] = '';
			$data['billingcity'] = '';
			$data['billingcountry'] = '';
			if(!$data['shippingname1']) {
				$data['shippingname1'] = $data['billingname1'];
				$data['shippingname2'] = $data['billingname2'];
				$data['shippingdepartment'] = $data['billingdepartment'];
				$data['shippingstreet'] = $data['billingstreet'];
				$data['shippingpostcode'] = $data['billingpostcode'];
				$data['shippingcity'] = $data['billingcity'];
				$data['shippingcountry'] = $data['billingcountry'];
				$data['shippingphone'] = '';
			}
			unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['prepayment'], $data['ebayorderid']);
			$module = 'purchases';
		} elseif($target == 'process') {
			/*$form = new Processes_Form_Process();
			$elements = $form->getElements();
			foreach($elements as $key => $value) {
				if(isset($invoice[$key])) $data[$key] = $invoice[$key];
			}*/
			$data['prepaymenttotal'] = $data['prepayment'];
			$data['contactid'] = $data['contactid'];
			$data['deliverystatus'] = 'deliveryIsWaiting';
			$data['supplierorderstatus'] = 'supplierNotOrdered';
			$data['paymentstatus'] = 'waitingForPayment';
			unset($data['id'], $data['quotedate'], $data['orderdate'], $data['prepayment'], $data['ebayorderid'], $data['templateid'], $data['language'], $data['filename']);
			unset($data['pdfshowprices'], $data['pdfshowdiscounts'], $data['pdfshowoptions'], $data['pdfshowattributes'], $data['pdfshowcover']);
			$module = 'processes';
		}

		//Define belonging classes
		$parentClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target);

		//Create new dataset
		$parentDb = new $parentClass();
		$parentMethod = 'add'.ucfirst($target);
		$newid = $parentDb->$parentMethod($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $newid, array('sales', $module), array('invoice', $target), $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_DOCUMENT_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', $target, $module, array('id' => $newid));
	}

	public function saveAction()
	{
		$id = (int)$this->_getParam('id', 0);

		try {
			$this->generatePdfDocument($id, [
				'finalize' => true,
				'output' => 'file',
				'storage' => 'contact',
				'overwrite' => false,
			]);
		} catch (RuntimeException $e) {
			$this->_flashMessenger->addMessage('MESSAGES_INVOICE_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'invoice');
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');
		return $this->_helper->redirector->gotoSimple('view', 'invoice', null, ['id' => $id]);
	}

	public function cancelAction()
	{
		$this->disableView();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			$data = $this->requireRow($id);

			$invoice = new Sales_Model_DbTable_Invoice();
			$invoice->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}
}
