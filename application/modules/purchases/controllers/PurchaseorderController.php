<?php

class Purchases_PurchaseorderController extends Zend_Controller_Action
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
		$form = new Purchases_Form_Toolbar();

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

	protected function requirePurchaseorder(int $id, bool $silent = false): ?array
	{
		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorder = $purchaseorderDb->getPurchaseorderForEdit($id);

		if ($purchaseorder) {
			return $purchaseorder;
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
		$this->_flashMessenger->addMessage('MESSAGES_PURCHASE_ORDER_NOT_FOUND');
		$this->_helper->redirector->gotoSimple('index', 'purchaseorder');

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
		$toolbar = new Purchases_Form_Toolbar();
		$toolbarInline = new Purchases_Form_ToolbarInline();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Purchases_Model_Get();
		$purchaseorders = $get->purchaseorders($params, $options, $this->_flashMessenger);

		$this->view->purchaseorders = $purchaseorders;
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

		$factory = new Purchases_Service_CreateDataFactory();
		$data = $factory->build($controller, $contactId);

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$id = $purchaseorderDb->addPurchaseorder($data);

		return $this->_helper->redirector->gotoSimple('edit', 'purchaseorder', null, ['id' => $id]);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$purchaseorder = $this->requirePurchaseorder($id);
		if (!$purchaseorder) return;

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();

		$this->_helper->Access->lock($id, $this->_user['id'], $purchaseorder['locked'] ?? 0, $purchaseorder['lockedtime'] ?? null);

		$formFactory = new Purchases_Service_EditFormFactory();
		$formData = $formFactory->create('Purchases_Form_Purchaseorder');
		$form = $formData['form'];
		$options = $formData['options'];
		$toolbar = new Purchases_Form_Toolbar();

		if ($request->isPost()) {
			$this->_helper->Calculate($id, $this->_date, $this->_user['id'], $purchaseorder['taxfree']);

			if ($isAjax) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				$ajaxSaveService = new Purchases_Service_EditAjaxSaveService();

				return $this->_helper->json($ajaxSaveService->save([
					'form' => $form,
					'post' => (array)$request->getPost(),
					'id' => $id,
					'db' => $purchaseorderDb,
					'loadMethod' => 'getPurchaseorderForEdit',
					'updateMethod' => 'updatePurchaseorder',
				]));
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				if (isset($values['currency'])) {
					$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
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

				$purchaseorderDb->updatePurchaseorder($id, $values);
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'purchaseorder', null, ['id' => $id]);
			}
		} else {
			$formFactory->populate($form, $purchaseorder, $id, 'purchaseorders', 'purchaseorder');
		}

		$vmService = new Purchases_Service_PurchaseorderEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$purchaseorder);

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

		$purchaseorder = $this->requirePurchaseorder($id);
		if (!$purchaseorder) return;

		$this->ensurePdfDocumentExists($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$purchaseorder['contactid']);

		$emailFormFactory = new Purchases_Service_EmailFormFactory();
		$attachmentService = new Purchases_Service_AttachmentService();
		$readonlyFormFactory = new Purchases_Service_ReadonlyFormFactory();

		$this->view->assign([
			'purchaseorder' => $purchaseorder,
			'contact' => $contact,
			'emailForm' => $emailFormFactory->build($purchaseorder, $contact, $controller),
			'form' => $readonlyFormFactory->build('Purchases_Form_Purchaseorder', $purchaseorder, Zend_Registry::get('Zend_Locale')),
			'toolbar' => new Purchases_Form_Toolbar(),
		] + $attachmentService->sync($purchaseorder, $contact, $controller));

		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);

		$data = $this->requirePurchaseorder($id);
		if (!$data) return;

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['purchaseorderid']);
		$data['title'] = $data['title'].' 2';
		$data['purchaseorderdate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorderid = $purchaseorderDb->addPurchaseorder($data);

		//Copy positions
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $purchaseorderid, 'purchases', 'purchaseorder', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');

		echo (int)$purchaseorderid;
	}

	public function generateAction()
	{
		$id = $this->_getParam('id', 0);
		$target = $this->_getParam('target', 0);
		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$data = $purchaseorderDb->getPurchaseorder($id);

		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		if($target == 'salesorder') {
			unset($data['id'], $data['purchaseorderid'], $data['purchaseorderdate'], $data['quoterequestid'], $data['quoterequestdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
		} elseif($target == 'invoice') {
			unset($data['id'], $data['purchaseorderid'], $data['purchaseorderdate'], $data['quoterequestid'], $data['quoterequestdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
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
			unset($data['id'], $data['purchaseorderid'], $data['purchaseorderdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'purchases';
		}

		//Define belonging classes
		$parentClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target);

		//Create new dataset
		$parentDb = new $parentClass();
		$parentMethod = 'add'.ucfirst($target);
		$newid = $parentDb->$parentMethod($data);

		//Copy positions
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $newid, array('purchases', $module), array('purchaseorder', $target), $this->_date);

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

			$this->_flashMessenger->addMessage('MESSAGES_PURCHASE_ORDER_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'purchaseorder');
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
			$this->_flashMessenger->addMessage('MESSAGES_PURCHASE_ORDER_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'purchaseorder');
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');
		return $this->_helper->redirector->gotoSimple('view', 'purchaseorder', null, ['id' => $id]);
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
			$this->_flashMessenger->addMessage('MESSAGES_PURCHASE_ORDER_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'purchaseorder');
		}

		return $this->sendPdfResponse($result);
	}

	protected function generatePdfDocument(int $id, array $options = []): array
	{
		$purchaseorder = $this->requirePurchaseorder($id, true);
		if (!$purchaseorder) {
			throw new RuntimeException('Purchaseorder not found');
		}

		if (!empty($options['finalize'])) {
			$finalizeService = new Purchases_Service_DocumentFinalizeService();
			$purchaseorder = $finalizeService->finalize($purchaseorder, 'purchaseorder');
		}

		$pdf = new DEEC_Pdf();

		return $pdf->generate([
			'module' => 'purchases',
			'controller' => 'purchaseorder',
			'documentId' => (int)$purchaseorder['id'],
			'output' => $options['output'] ?? 'file',
			'templateid' => $options['templateid'] ?? null,
			'storage' => $options['storage'] ?? 'cache',
			'overwrite' => !empty($options['overwrite']),
		]);
	}

	protected function ensurePdfDocumentExists(int $id): void
	{
		$purchaseorder = $this->requirePurchaseorder($id, true);
		if (!$purchaseorder) {
			return;
		}

		if (empty($purchaseorder['id']) || empty($purchaseorder['contactid']) || empty($purchaseorder['clientid'])) {
			return;
		}

		$docIdField = 'purchaseorderid';

		// Do not generate contact PDF before document is finalized
		if (empty($purchaseorder[$docIdField]) || empty($purchaseorder['filename'])) {
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

			$purchaseorder = $this->requirePurchaseorder($id);
			if (!$purchaseorder) return;

			$purchaseorder = new Purchases_Model_DbTable_Purchaseorder();
			$purchaseorder->setState($id, 106);
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
