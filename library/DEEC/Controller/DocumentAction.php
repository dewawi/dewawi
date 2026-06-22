<?php

abstract class DEEC_Controller_DocumentAction extends DEEC_Controller_PositionAction
{
	protected function beforeView(array $row): void
	{
		$this->ensurePdfDocumentExists((int)$row['id']);
	}

	protected function buildViewForm(array $row)
	{
		$factoryClass = $this->getReadonlyFormFactoryClass();

		if(!$factoryClass || !class_exists($factoryClass)) {
			return parent::buildViewForm($row);
		}

		$factory = new $factoryClass();

		return $factory->build(
			$this->getFormClass(),
			$row,
			Zend_Registry::get('Zend_Locale')
		);
	}

	protected function getViewAssigns(array $row, $form): array
	{
		$controller = $this->getRequest()->getControllerName();
		$contact = $this->getViewContact($row);

		$assign = [
			'contact' => $contact,
		];

		$emailFactory = $this->getEmailFormFactory();
		if($emailFactory) {
			$assign['emailForm'] = $emailFactory->build($row, $contact, $controller);
		}

		$attachmentService = $this->getAttachmentService();
		if($attachmentService) {
			$assign = array_merge(
				$assign,
				$attachmentService->sync($row, $contact, $controller)
			);
		}

		return $assign;
	}

	protected function getViewContact(array $row): array
	{
		if(empty($row['contactid'])) {
			return [];
		}

		$contactDb = new Contacts_Model_DbTable_Contact();

		return $contactDb->getContactWithID((int)$row['contactid']);
	}

	protected function getReadonlyFormFactoryClass(): ?string
	{
		$module = ucfirst($this->getRequest()->getModuleName());

		return $module . '_Service_ReadonlyFormFactory';
	}

	protected function getEmailFormFactory()
	{
		$className = ucfirst($this->getRequest()->getModuleName()) . '_Service_EmailFormFactory';

		if(!class_exists($className)) {
			return null;
		}

		return new $className();
	}

	protected function getAttachmentService()
	{
		$className = ucfirst($this->getRequest()->getModuleName()) . '_Service_AttachmentService';

		if(!class_exists($className)) {
			return null;
		}

		return new $className();
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
			return $this->handleDocumentNotFound($isAjax);
		}

		if ($isAjax) {
			$this->disableView();

			return $this->_helper->json([
				'ok' => true,
				'url' => $result['url'] ?? null,
				'filename' => $result['filename'] ?? null,
			]);
		}

		return $this->sendPdfResponse($result);
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
			return $this->handleDocumentNotFound(false);
		}

		return $this->sendPdfResponse($result);
	}

	protected function generatePdfDocument(int $id, array $options = []): array
	{
		$row = $this->loadRow($id);

		if (!$row) {
			throw new RuntimeException('Document not found');
		}

		if (!empty($options['finalize'])) {
			$row = $this->finalizeDocument($row);
		}

		$pdf = new DEEC_Pdf();

		return $pdf->generate([
			'module' => $this->getRequest()->getModuleName(),
			'controller' => $this->getRequest()->getControllerName(),
			'documentId' => (int)$row['id'],
			'output' => $options['output'] ?? 'file',
			'templateid' => $options['templateid'] ?? null,
			'storage' => $options['storage'] ?? 'cache',
			'overwrite' => !empty($options['overwrite']),
		]);
	}

	protected function ensurePdfDocumentExists(int $id): void
	{
		$row = $this->loadRow($id);

		if (!$row) {
			return;
		}

		if (empty($row['id']) || empty($row['contactid']) || empty($row['clientid'])) {
			return;
		}

		$docIdField = $this->getDocumentNumberField();

		// Do not generate contact PDF before document is finalized
		if (empty($row[$docIdField]) || empty($row['filename'])) {
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

		$this->disableView();

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

	protected function handleDocumentNotFound(bool $isAjax)
	{
		if ($isAjax) {
			$this->disableView();

			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		$this->_flashMessenger->addMessage($this->getNotFoundMessage());

		return $this->_helper->redirector->gotoSimple(
			'index',
			$this->getRequest()->getControllerName(),
			$this->getRequest()->getModuleName()
		);
	}

	protected function getDocumentNumberField(): string
	{
		return $this->getRequest()->getControllerName() . 'id';
	}

	protected function isReadonlyState(array $row): bool
	{
		return in_array((int)($row['state'] ?? 0), [105, 106], true);
	}

	protected function finalizeDocument(array $row): array
	{
		$service = $this->getDocumentFinalizeService();

		if (!$service) {
			throw new RuntimeException('Document finalize service not available');
		}

		return $service->finalize(
			$row,
			$this->getRequest()->getControllerName()
		);
	}

	protected function getDocumentFinalizeService()
	{
		$className = $this->getDocumentFinalizeServiceClass();

		if (!$className) {
			$module = ucfirst($this->getRequest()->getModuleName());
			$className = $module . '_Service_DocumentFinalizeService';
		}

		if (!class_exists($className)) {
			return null;
		}

		$service = new $className();

		if (!method_exists($service, 'finalize')) {
			throw new RuntimeException($className . ' must provide finalize()');
		}

		return $service;
	}

	protected function getDocumentFinalizeServiceClass(): ?string
	{
		return null;
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
			$this->_flashMessenger->addMessage($this->getNotFoundMessage());

			return $this->_helper->redirector->gotoSimple(
				'index',
				$this->getRequest()->getControllerName(),
				$this->getRequest()->getModuleName()
			);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');

		return $this->_helper->redirector->gotoSimple(
			'view',
			$this->getRequest()->getControllerName(),
			$this->getRequest()->getModuleName(),
			['id' => $id]
		);
	}

	public function generateAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$target = (string)$this->_getParam('target', '');

		$sourceModule = $this->getRequest()->getModuleName();
		$sourceController = $this->getRequest()->getControllerName();

		$row = $this->requireRow($id);
		$config = $this->getDocumentGenerateConfig($sourceController, $target);

		if (!$config) {
			throw new RuntimeException('Invalid generate target');
		}

		$data = $this->prepareGeneratedDocumentData($row, $sourceController, $target, $config);

		$targetDbClass = ucfirst($config['module']) . '_Model_DbTable_' . ucfirst($target);
		$targetDb = new $targetDbClass();

		$newId = $targetDb->create($data);

		$positionsDbClass = $this->getPositionsDbTableClass();
		$positionsDb = new $positionsDbClass();
		$positions = $positionsDb->getPositions($id);

		$this->_helper->Position->copyPositions(
			$positions,
			$newId,
			[$sourceModule, $config['module']],
			[$sourceController, $target],
			$this->_date
		);

		$this->_flashMessenger->addMessage('MESSAGES_DOCUMENT_SUCCESFULLY_GENERATED');

		return $this->_helper->redirector->gotoSimple(
			'edit',
			$target,
			$config['module'],
			['id' => $newId]
		);
	}

	protected function getDocumentGenerateConfig(string $source, string $target): ?array
	{
		$fullSalesReset = [
			'invoiceid', 'invoicedate',
			'deliveryorderid', 'deliveryorderdate',
			'quoteid', 'quotedate',
			'salesorderid', 'salesorderdate',
			'deliverydate',
			'prepayment',
			'ebayorderid',
		];

		$purchaseToSalesReset = [
			'purchaseorderid', 'purchaseorderdate',
			'quoterequestid', 'quoterequestdate',
			'quoteid', 'quotedate',
			'salesorderid', 'salesorderdate',
			'invoiceid', 'invoicedate',
		];

		$map = [
			'quote' => [
				'salesorder' => ['module' => 'sales'],
				'invoice' => ['module' => 'sales'],
				'deliveryorder' => ['module' => 'sales'],
				'quoterequest' => ['module' => 'purchases', 'clearBilling' => true],
				'purchaseorder' => ['module' => 'purchases', 'clearBilling' => true],
				'process' => ['module' => 'processes', 'processDefaults' => true],
			],

			'salesorder' => [
				'quote' => ['module' => 'sales', 'unset' => ['salesorderid', 'salesorderdate', 'quoteid', 'quotedate']],
				'invoice' => ['module' => 'sales'],
				'deliveryorder' => ['module' => 'sales'],
				'quoterequest' => ['module' => 'purchases', 'clearBilling' => true],
				'purchaseorder' => ['module' => 'purchases', 'clearBilling' => true],
				'process' => [
					'module' => 'processes',
					'processDefaults' => true,
					'unset' => ['quotedate', 'orderdate', 'templateid', 'language', 'filename'],
				],
			],

			'invoice' => [
				'quote' => ['module' => 'sales', 'unset' => $fullSalesReset],
				'salesorder' => ['module' => 'sales', 'unset' => $fullSalesReset],
				'deliveryorder' => ['module' => 'sales', 'unset' => ['deliveryorderid', 'deliveryorderdate', 'prepayment', 'ebayorderid']],
				'creditnote' => ['module' => 'sales', 'unset' => ['deliveryorderid', 'deliveryorderdate', 'prepayment', 'ebayorderid']],
				'quoterequest' => [
					'module' => 'purchases',
					'clearBilling' => true,
					'unset' => ['deliveryorderid', 'deliveryorderdate', 'prepayment', 'ebayorderid'],
				],
				'purchaseorder' => [
					'module' => 'purchases',
					'clearBilling' => true,
					'unset' => ['deliveryorderid', 'deliveryorderdate', 'prepayment', 'ebayorderid'],
				],
				'process' => [
					'module' => 'processes',
					'processDefaults' => true,
					'copy' => ['prepaymenttotal' => 'prepayment'],
					'unset' => ['quotedate', 'orderdate', 'prepayment', 'ebayorderid', 'templateid', 'language', 'filename'],
				],
			],

			'deliveryorder' => [
				'salesorder' => [
					'module' => 'sales',
					'unset' => [
						'deliveryorderid', 'deliveryorderdate',
						'quoteid', 'quotedate',
						'salesorderid', 'salesorderdate',
						'invoiceid', 'invoicedate',
					],
				],
				'invoice' => ['module' => 'sales'],
				'quoterequest' => ['module' => 'purchases', 'clearBilling' => true, 'unset' => ['deliveryorderid', 'deliveryorderdate']],
				'purchaseorder' => ['module' => 'purchases', 'clearBilling' => true, 'unset' => ['deliveryorderid', 'deliveryorderdate']],
			],

			'creditnote' => [
				'salesorder' => [
					'module' => 'sales',
					'unset' => [
						'creditnoteid', 'creditnotedate',
						'quoteid', 'quotedate',
						'salesorderid', 'salesorderdate',
						'invoiceid', 'invoicedate',
					],
				],
				'invoice' => ['module' => 'sales', 'unset' => ['invoiceid', 'invoicedate']],
				'quoterequest' => [
					'module' => 'purchases',
					'clearBilling' => true,
					'unset' => [
						'creditnoteid', 'creditnotedate',
						'quoteid', 'quotedate',
						'salesorderid', 'salesorderdate',
						'invoiceid', 'invoicedate',
					],
				],
				'purchaseorder' => [
					'module' => 'purchases',
					'clearBilling' => true,
					'unset' => [
						'creditnoteid', 'creditnotedate',
						'quoteid', 'quotedate',
						'salesorderid', 'salesorderdate',
						'invoiceid', 'invoicedate',
					],
				],
			],

			'reminder' => [
				'salesorder' => ['module' => 'sales', 'unset' => ['reminderid', 'reminderdate']],
				'invoice' => ['module' => 'sales', 'unset' => ['reminderid', 'reminderdate']],
				'quoterequest' => ['module' => 'purchases', 'clearBilling' => true, 'unset' => ['reminderid', 'reminderdate']],
				'purchaseorder' => ['module' => 'purchases', 'clearBilling' => true, 'unset' => ['reminderid', 'reminderdate']],
				'process' => ['module' => 'processes', 'processDefaults' => true],
			],

			'quoterequest' => [
				'salesorder' => ['module' => 'sales', 'unset' => ['quoterequestid', 'quoterequestdate', 'quoteid', 'quotedate', 'salesorderid', 'salesorderdate', 'invoiceid', 'invoicedate']],
				'invoice' => ['module' => 'sales', 'unset' => ['quoterequestid', 'quoterequestdate', 'quoteid', 'quotedate', 'salesorderid', 'salesorderdate', 'invoiceid', 'invoicedate']],
				'purchaseorder' => ['module' => 'purchases', 'clearBilling' => true],
			],

			'purchaseorder' => [
				'salesorder' => ['module' => 'sales', 'unset' => $purchaseToSalesReset],
				'invoice' => ['module' => 'sales', 'unset' => $purchaseToSalesReset],
				'quoterequest' => [
					'module' => 'purchases',
					'clearBilling' => true,
					'unset' => [
						'purchaseorderid', 'purchaseorderdate',
						'quoteid', 'quotedate',
						'salesorderid', 'salesorderdate',
						'invoiceid', 'invoicedate',
					],
				],
			],
		];

		return $map[$source][$target] ?? null;
	}

	protected function prepareGeneratedDocumentData(array $data, string $source, string $target, array $config): array
	{
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = null;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = null;

		foreach (($config['copy'] ?? []) as $targetField => $sourceField) {
			$data[$targetField] = $data[$sourceField] ?? null;
		}

		unset($data['id']);

		foreach (($config['unset'] ?? []) as $field) {
			unset($data[$field]);
		}

		if (!empty($config['clearBilling'])) {
			$data = $this->clearBillingDataForPurchaseDocument($data);
		}

		if (!empty($config['processDefaults'])) {
			$data['deliverystatus'] = 'deliveryIsWaiting';
			$data['supplierorderstatus'] = 'supplierNotOrdered';
			$data['paymentstatus'] = 'waitingForPayment';

			unset(
				$data['pdfshowprices'],
				$data['pdfshowdiscounts'],
				$data['pdfshowoptions'],
				$data['pdfshowattributes'],
				$data['pdfshowcover']
			);
		}

		return $data;
	}

	protected function clearBillingDataForPurchaseDocument(array $data): array
	{
		$data['billingname1'] = '';
		$data['billingname2'] = '';
		$data['billingdepartment'] = '';
		$data['billingstreet'] = '';
		$data['billingpostcode'] = '';
		$data['billingcity'] = '';
		$data['billingcountry'] = '';

		if (empty($data['shippingname1'])) {
			$data['shippingname1'] = $data['billingname1'];
			$data['shippingname2'] = $data['billingname2'];
			$data['shippingdepartment'] = $data['billingdepartment'];
			$data['shippingstreet'] = $data['billingstreet'];
			$data['shippingpostcode'] = $data['billingpostcode'];
			$data['shippingcity'] = $data['billingcity'];
			$data['shippingcountry'] = $data['billingcountry'];
			$data['shippingphone'] = '';
		}

		return $data;
	}
}
