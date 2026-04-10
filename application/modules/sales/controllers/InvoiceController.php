<?php

class Sales_InvoiceController extends Zend_Controller_Action
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

	public function getAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		$elementName = (string)$this->_getParam('element', '');
		$form = new Sales_Form_Toolbar();

		$el = $form->getElement($elementName);

		if (!$el) {
			return $this->_helper->json([
				'ok' => false,
				'message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS'),
			]);
		}

		$options = $el['options'] ?? [];

		return $this->_helper->json($options);
	}

	protected function requireInvoice(int $id, bool $silent = false): ?array
	{
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoiceForEdit($id);

		if ($invoice) {
			return $invoice;
		}

		$request = $this->getRequest();

		// AJAX
		if ($request->isXmlHttpRequest()) {
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->layout->disableLayout();

			$this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);

			return null;
		}

		// Silent mode (PDF etc.)
		if ($silent) {
			$this->_helper->viewRenderer->setNoRender();
			return null;
		}

		// Default redirect
		$this->_flashMessenger->addMessage('MESSAGES_INVOICE_NOT_FOUND');
		$this->_helper->redirector->gotoSimple('index', 'invoice');

		return null;
	}

	public function indexAction()
	{
		if ($this->getRequest()->isPost()) {
			$this->_helper->getHelper('layout')->disableLayout();
		}

		$this->buildIndexView();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$this->buildIndexView();
	}

	protected function buildIndexView(): void
	{
		$toolbar = new Sales_Form_Toolbar();
		$toolbarInline = new Sales_Form_ToolbarInline();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Sales_Model_Get();
		$invoices = $get->invoices($params, $options, $this->_flashMessenger);

		$this->view->invoices = $invoices;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->toolbarInline = $toolbarInline;
		$this->view->messages = array_merge(
			$this->_flashMessenger->getMessages(),
			$this->_flashMessenger->getCurrentMessages()
		);
		$this->_flashMessenger->clearCurrentMessages();
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

		$invoice = $this->requireInvoice($id);
		if (!$invoice) return;

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
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				$ajaxSaveService = new Sales_Service_EditAjaxSaveService();

				return $this->_helper->json($ajaxSaveService->save([
					'form' => $form,
					'post' => (array)$request->getPost(),
					'id' => $id,
					'db' => $invoiceDb,
					'loadMethod' => 'getInvoiceForEdit',
					'updateMethod' => 'updateInvoice',
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

		$this->view->messages = array_merge(
			$this->_helper->flashMessenger->getMessages(),
			$this->_helper->flashMessenger->getCurrentMessages()
		);
		$this->_helper->flashMessenger->clearCurrentMessages();
	}

	public function viewAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$controller = $this->getRequest()->getControllerName();

		$invoice = $this->requireInvoice($id);
		if (!$invoice) return;

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

		$data = $this->requireInvoice($id);
		if (!$data) return;

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

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

		$data = $this->requireInvoice($id);
		if (!$data) return;

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

	public function previewAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$templateId = (int)$this->_getParam('templateid', 0);
		$isAjax = $this->getRequest()->isXmlHttpRequest();

		try {
			$result = $this->generatePdfDocument($id, [
				'output' => $isAjax ? 'file' : 'inline',
				'templateid' => $templateId ?: null,
				'storage' => 'cache',
				'overwrite' => true,
			]);
		} catch (RuntimeException $e) {
			if ($isAjax) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				return $this->_helper->json([
					'ok' => false,
					'message' => 'not_found',
				]);
			}

			$this->_flashMessenger->addMessage('MESSAGES_INVOICE_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'invoice');
		}

		if ($isAjax) {
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->layout->disableLayout();

			return $this->_helper->json([
				'ok' => true,
				'url' => $result['url'] ?? null,
				'filename' => $result['filename'] ?? null,
			]);
		}

		return $this->sendPdfResponse($result);
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

	public function downloadAction()
	{
		$id = (int)$this->_getParam('id', 0);

		try {
			$result = $this->generatePdfDocument($id, [
				'output' => 'download',
				'storage' => 'cache',
				'overwrite' => true,
			]);
		} catch (RuntimeException $e) {
			$this->_flashMessenger->addMessage('MESSAGES_INVOICE_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'invoice');
		}

		return $this->sendPdfResponse($result);
	}

	protected function generatePdfDocument(int $id, array $options = []): array
	{
		$invoice = $this->requireInvoice($id, true);
		if (!$invoice) {
			throw new RuntimeException('Invoice not found');
		}

		if (!empty($options['finalize'])) {
			$finalizeService = new Sales_Service_DocumentFinalizeService();
			$invoice = $finalizeService->finalize($invoice, 'invoice');
		}

		$pdf = new DEEC_Pdf();

		return $pdf->generate([
			'module' => 'sales',
			'controller' => 'invoice',
			'documentId' => (int)$invoice['id'],
			'output' => $options['output'] ?? 'file',
			'templateid' => $options['templateid'] ?? null,
			'storage' => $options['storage'] ?? 'cache',
			'overwrite' => !empty($options['overwrite']),
		]);
	}

	protected function ensurePdfDocumentExists(int $id): void
	{
		$invoice = $this->requireInvoice($id, true);
		if (!$invoice) {
			return;
		}

		if (empty($invoice['id']) || empty($invoice['contactid']) || empty($invoice['clientid'])) {
			return;
		}

		$docIdField = 'invoiceid';

		// Do not generate contact PDF before document is finalized
		if (empty($invoice[$docIdField]) || empty($invoice['filename'])) {
			return;
		}

		try {
			$this->generatePdfDocument($id, [
				'output' => 'file',
				'storage' => 'contact',
				'overwrite' => false,
			]);
		} catch (RuntimeException $e) {
			// Keep view page working even if PDF generation fails
		}
	}

	protected function sendPdfResponse(array $result)
	{
		if (empty($result['path']) || !is_file($result['path'])) {
			throw new RuntimeException('PDF file not found');
		}

		$mode = $result['output'] ?? 'inline';
		$filename = $result['filename'] ?? basename($result['path']);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		$response = $this->getResponse();
		$response->clearHeaders();
		$response->setHeader('Content-Type', 'application/pdf', true);
		$response->setHeader(
			'Content-Disposition',
			($mode === 'download' ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
			true
		);
		$response->setHeader('Content-Length', (string)filesize($result['path']), true);
		$response->sendHeaders();

		readfile($result['path']);
		exit;
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			$invoice = $this->requireInvoice($id);
			if (!$invoice) return;

			$invoice = new Sales_Model_DbTable_Invoice();
			$invoice->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}

	public function pinAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Pin->toggle($id);
	}

	public function lockAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$result = $this->_helper->Access->lock($id, $this->_user['id']);

		if (is_array($result)) {
			return $this->_helper->json($result);
		}
	}

	public function unlockAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$result = $this->_helper->Access->unlock($id);

		if (is_array($result)) {
			return $this->_helper->json($result);
		}
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
