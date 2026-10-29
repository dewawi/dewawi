<?php

abstract class DEEC_Controller_DocumentAction extends DEEC_Controller_Action
{
	protected function buildEditViewModel(int $id, array $row): array
	{
		$viewModel = parent::buildEditViewModel($id, $row);
		$viewModel['documentValidation'] = $this->getDocumentValidation($row);
		return $viewModel;
	}

	protected function beforeView(array $row): void
	{
		$this->ensurePdfDocumentExists((int)$row['id']);
	}

	protected function buildViewForm(array $row)
	{
		$factoryClass = $this->getReadonlyFormFactoryClass();

		if(!$factoryClass || !class_exists($factoryClass)) {
			$form = parent::buildViewForm($row);
		} else {
			$factory = new $factoryClass();
			$form = $factory->build($this->getFormClass(), $row, Zend_Registry::get('Zend_Locale'));
		}

		$request = $this->getRequest();
		$module = $request->getModuleName();
		$controller = $request->getControllerName();
		if ((new DEEC_Permission((array)$this->_user))->hasPermission($module, $controller, 'edit')) {
			$form->setReadonlyEditableFields(['notes']);
			$form->setElementData('notes', ['attribs' => [
				'data-document-note' => 1,
				'data-module' => $module,
				'data-controller' => $controller,
				'data-id' => (int)$row['id'],
			]]);
		}

		return $form;
	}

	public function editAction()
	{
		$request = $this->getRequest();
		if (!$request->isPost()) return parent::editAction();

		$id = (int)$this->_getParam('id', 0);
		$row = $this->loadRow($id);
		if (!$row || !$this->isReadonlyState($row)) return parent::editAction();

		$this->disableView();
		$post = (array)$request->getPost();
		$permission = new DEEC_Permission((array)$this->_user);
		if (!$permission->hasPermission($request->getModuleName(), $request->getControllerName(), 'edit')
			|| array_keys($post) !== ['notes'] || !is_string($post['notes'])) {
			$this->getResponse()->setHttpResponseCode(403);
			return $this->_helper->json(['ok' => false, 'message' => 'MESSAGES_DOCUMENT_READONLY']);
		}

		if ($this->_helper->Access->isLocked($row['locked'] ?? 0, $row['lockedtime'] ?? null, $this->_user['id'])) {
			return $this->_helper->json(['ok' => false, 'message' => 'locked']);
		}

		$formClass = $this->getFormClass();
		$form = new $formClass();
		if (!$form->isValidPartial($post)) {
			return $this->_helper->json(['ok' => false, 'errors' => $this->toErrorMessages($form->getErrors(), $form)]);
		}

		$values = $form->getFilteredValuesPartial($post);
		if ((string)($row['notes'] ?? '') !== $values['notes']) {
			try {
				$this->getDb()->updateById($id, $values);
			} catch (Exception $e) {
				error_log((string)$e);
				return $this->_helper->json(['ok' => false, 'message' => 'save_failed']);
			}
		}

		return $this->sendSaveResponse($id, $values, DEEC_Display::fromRow($form, $values, ['notes']));
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

		$client = Zend_Registry::get('Client');
		$contactClientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$client['id'];
		if ($contactClientId <= 0) return [];

		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($contactClientId);

		return $contactDb->getContactWithID((int)$row['contactid']) ?: [];
	}

	protected function beforeCreate(array $data): array
	{
		$data = parent::beforeCreate($data);
		$data += ['subtotal' => 0, 'taxes' => 0, 'total' => 0];

		$data['responsibleid'] = (int)$this->_user['id'];
		$data['responsible'] = (string)$this->_user['name'];

		return $data;
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		$current = $this->getDb()->getByIdForUpdate((int)$row['id']);
		if (!$current || $this->isReadonlyState($current)) {
			throw new RuntimeException('Document is not editable');
		}
		$row = $current;

		if(array_key_exists('responsibleid', $values)) {
			$responsibleId = (int)$values['responsibleid'];

			if($responsibleId) {
				$users = new Users_Model_DbTable_User();
				$user = $users->getUser($responsibleId);
				if ($responsibleId !== (int)$this->_user['id'] && ((int)$user['clientid'] !== (int)$row['clientid'] || empty($user['activated']) || !empty($user['deleted']))) throw new InvalidArgumentException('Invalid responsible user');

				$values['responsible'] = $user['name'];
			} else {
				$values['responsibleid'] = null;
				$values['responsible'] = null;
			}
		}

		if(array_key_exists('state', $values)) {
			$oldState = (int)($row['state'] ?? 0);
			$newState = (int)$values['state'];

			if($newState !== $oldState) {
				if($newState === 105) {
					$values['completed'] = 1;

					if(empty($row['completeddate'])) {
						$values['completeddate'] = $this->_date;
						$values['completedby'] = (int)$this->_user['id'];
					}
				}

				if($newState === 106) {
					$values['cancelled'] = 1;
					$values['cancelleddate'] = $this->_date;
					$values['cancelledby'] = (int)$this->_user['id'];
				}
			}
		}

		return parent::beforeEditSave($values, $row);
	}

	protected function validateDocumentContactperson(array $values, array $row): array
	{
		if (!array_key_exists('contactpersonid', $values) && !array_key_exists('contactid', $values)) return $values;

		$contactChanged = array_key_exists('contactid', $values)
			&& (string)$values['contactid'] !== (string)($row['contactid'] ?? '');
		$personExplicit = array_key_exists('contactpersonid', $values);
		$contactpersonId = (int)($personExplicit ? $values['contactpersonid'] : ($row['contactpersonid'] ?? 0));

		if ($contactpersonId <= 0) {
			if ($personExplicit) $values['contactpersonid'] = null;
			return $values;
		}

		$client = Zend_Registry::get('Client');
		$contactClientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$client['id'];
		if ($contactClientId <= 0) throw new InvalidArgumentException('Contact client context is missing.');

		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($contactClientId);
		$contact = $contactDb->getContactWithID((int)($values['contactid'] ?? $row['contactid'] ?? 0));

		$contactpersonDb = (new Contacts_Model_DbTable_Contactperson())->setClientId($contactClientId);
		if ($contact && $contactpersonDb->belongsToContact($contactpersonId, (int)$contact['id'])) {
			if ($personExplicit) $values['contactpersonid'] = $contactpersonId;
			return $values;
		}

		if ($contactChanged && !$personExplicit) {
			$values['contactpersonid'] = null;
			return $values;
		}

		throw new InvalidArgumentException('Contact person does not belong to the selected contact.');
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

		$row = $this->loadRow($id);
		if (!$row) {
			return $this->handleDocumentNotFound($isAjax);
		}

		$result = $this->generatePdfDocument($id, [
			'output' => $isAjax ? 'file' : 'inline',
			'templateid' => $templateId ?: null,
			'storage' => 'cache',
			'overwrite' => true,
		]);

		if ($isAjax) {
			$this->disableView();

			return $this->_helper->json([
				'ok' => true,
				'url' => $result['url'] ?? null,
				'filename' => $result['filename'] ?? null,
				'validation' => $this->localizeDocumentValidation($this->getDocumentValidation($row)),
			]);
		}

		return $this->sendPdfResponse($result);
	}

	public function downloadAction()
	{
		$id = (int)$this->_getParam('id', 0);

		if (!$this->loadRow($id)) {
			return $this->handleDocumentNotFound(false);
		}

		$result = $this->generatePdfDocument($id, [
			'output' => 'download',
			'storage' => 'cache',
			'overwrite' => true,
		]);

		return $this->sendPdfResponse($result);
	}

	protected function generatePdfDocument(int $id, array $options = []): array
	{
		$row = $this->loadRow($id);
		$createOriginal = false;

		if (!$row) {
			throw new RuntimeException('Document not found');
		}

		if (!empty($options['finalize'])) {
			if (empty($row[$this->getDocumentNumberField()])) {
				$db = $this->getDb();
				$adapter = $db->getAdapter();
				$adapter->beginTransaction();
				try {
					$row = $db->getByIdForUpdate($id);
					if (!$row) throw new RuntimeException('Document not found');
					if (empty($row[$this->getDocumentNumberField()])) {
						$this->assertDocumentCanFinalize($row);
						$createOriginal = true;
						$this->_helper->Calculate($id, $this->getRequest()->getModuleName(), $this->getRequest()->getControllerName());
						$row = $this->loadRow($id);
					}
					$row = $this->finalizeDocument($row);
					$adapter->commit();
				} catch (Throwable $exception) {
					$adapter->rollBack();
					throw $exception;
				}
			} else {
				$row = $this->finalizeDocument($row);
			}
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
			'createOriginal' => $createOriginal,
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
			error_log((string)$e);
		}
	}

	protected function sendPdfResponse(array $result)
	{
		if (empty($result['path']) || !is_file($result['path'])) {
			throw new RuntimeException('PDF file not found');
		}

		$mode = $result['output'] ?? 'inline';
		$filename = $result['filename'] ?? basename($result['path']);
		DEEC_Emailattachment::checkPath($result['path']);

		$this->disableView();

		$response = $this->getResponse();
		$response->clearHeaders();
		$response->setHeader('Content-Type', 'application/pdf', true);
		$response->setHeader(
			'Content-Disposition',
			($mode === 'download' ? 'attachment' : 'inline') . '; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
			true
		);
		$response->setHeader('Content-Length', (string)filesize($result['path']), true);
		$response->setHeader('X-Content-Type-Options', 'nosniff', true);
		$response->setHeader('Cache-Control', 'private, no-store', true);
		$response->setHeader('Content-Security-Policy', "sandbox; default-src 'none'", true);
		$response->sendHeaders();

		if ($this->getRequest()->getMethod() !== 'HEAD') readfile($result['path']);
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
		return DEEC_Permission::isDocumentReadonly($this->getRequest()->getModuleName(), $this->getRequest()->getControllerName(), $row);
	}

	protected function canDeleteRow(array $row): bool
	{
		return !$this->isReadonlyState($row) && parent::canDeleteRow($row);
	}

	public function cancelAction()
	{
		if (!$this->getRequest()->isPost()) return parent::cancelAction();

		$this->disableView();
		$adapter = $this->getDb()->getAdapter();
		$adapter->beginTransaction();
		try {
			$row = $this->getDb()->getByIdForUpdate((int)$this->_getParam('id', 0));
			if (!$row || $this->isReadonlyState($row)) {
				$adapter->rollBack();
				$this->getResponse()->setHttpResponseCode(403);
				return $this->_helper->json(['ok' => false, 'message' => 'MESSAGES_DOCUMENT_READONLY']);
			}
			$id = (int)$row['id'];
			$this->getDb()->setState($id, $this->getCancelledState());
			$adapter->commit();
		} catch (Throwable $exception) {
			$adapter->rollBack();
			throw $exception;
		}

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
			'state' => $this->getCancelledState(),
			'message' => 'MESSAGES_SUCCESFULLY_CANCELLED',
		]);
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

	protected function getDocumentValidation(array $row): array
	{
		return (new DEEC_Document_FinalizeValidator())->validate(
			$row,
			$this->getRequest()->getModuleName(),
			$this->getRequest()->getControllerName()
		);
	}

	protected function assertDocumentCanFinalize(array $row): array
	{
		return (new DEEC_Document_FinalizeValidator())->assertValid(
			$row,
			$this->getRequest()->getModuleName(),
			$this->getRequest()->getControllerName()
		);
	}

	protected function localizeDocumentValidation(array $validation): array
	{
		$titles = [
			'success' => 'DOCUMENT_VALIDATION_SUCCESS',
			'warning' => 'DOCUMENT_VALIDATION_WARNING',
			'error' => 'DOCUMENT_VALIDATION_ERROR',
		];
		$validation['title'] = $this->view->translate($titles[$validation['status']]);
		foreach (['errors', 'warnings'] as $type) {
			foreach ($validation[$type] as &$issue) {
				$issue['text'] = $this->view->translate($issue['message'], $issue['args'] ?? []);
			}
			unset($issue);
		}
		return $validation;
	}

	public function saveAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $this->getRequest()->isXmlHttpRequest();

		if (!$this->loadRow($id)) {
			return $this->handleDocumentNotFound($isAjax);
		}

		try {
			$this->generatePdfDocument($id, [
				'finalize' => true,
				'output' => 'file',
				'storage' => 'contact',
				'overwrite' => false,
			]);
		} catch (DEEC_Document_ValidationException $exception) {
			$validation = $this->localizeDocumentValidation($exception->getValidation());
			if ($isAjax) {
				$this->disableView();
				$this->getResponse()->setHttpResponseCode(422);
				return $this->_helper->json(['ok' => false, 'validation' => $validation]);
			}

			foreach ($validation['errors'] as $issue) $this->_flashMessenger->addMessage($issue['text']);
			return $this->_helper->redirector->gotoSimple('edit', $this->getRequest()->getControllerName(), null, ['id' => $id]);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');

		if ($isAjax) {
			$this->disableView();
			return $this->_helper->json([
				'ok' => true,
				'redirect' => $this->view->url([
					'module' => $this->getRequest()->getModuleName(),
					'controller' => $this->getRequest()->getControllerName(),
					'action' => 'view',
					'id' => $id,
				], null, true),
			]);
		}

		return $this->_helper->redirector->gotoSimple(
			'view',
			$this->getRequest()->getControllerName(),
			$this->getRequest()->getModuleName(),
			['id' => $id]
		);
	}

	protected function afterEditSave(int $id, array $values, array $row): void
	{
		$current = $this->getDb()->getByIdForUpdate($id);
		if (!$current || !empty($current[$this->getDocumentNumberField()])) {
			throw new RuntimeException('Document is not editable');
		}

		if (isset($values['currency'])) {
			$this->updatePositionCurrency($id, $values['currency']);
		}

		$calculation = $this->_helper->Calculate($id, $this->getRequest()->getModuleName(), $this->getRequest()->getControllerName());
		$this->setSaveCalculation($calculation['locale']);
		$this->reloadPositionsAfterSave();
	}

	protected function affectsDocumentCalculation(array $values): bool
	{
		return isset($values['taxfree']) || isset($values['currency']);
	}

	protected function updatePositionCurrency(int $documentId, string $currency): void
	{
		$className = $this->getPositionsDbTableClass();
		$positionsDb = new $className();

		foreach ($positionsDb->getPositions($documentId) as $position) {
			$positionsDb->updatePosition((int)$position->id, [
				'currency' => $currency,
			]);
		}
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
		$data['completeddate'] = null;
		$data['completedby'] = 0;
		$data['cancelled'] = 0;
		$data['cancelleddate'] = null;
		$data['cancelledby'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = null;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = null;

		foreach (($config['copy'] ?? []) as $targetField => $sourceField) {
			$data[$targetField] = $data[$sourceField] ?? null;
		}

		unset($data['id'], $data['filename'], $data[$target . 'id'], $data[$target . 'date']);

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
				$data['pdfshowcover'],
				$data['pdfshowimages'],
				$data['pdfshowpositionimages']
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

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$this->copyPositions($oldId, $newId);
	}

	protected function copyPositions(int $oldId, int $newId): void
	{
		$positionsDbClass = $this->getPositionsDbTableClass();

		if(!class_exists($positionsDbClass)) {
			return;
		}

		$positionsDb = new $positionsDbClass();

		if(!method_exists($positionsDb, 'getPositions')) {
			return;
		}

		$positions = $positionsDb->getPositions($oldId);

		$this->_helper->Position->copyPositions(
			$positions,
			$newId,
			$this->getRequest()->getModuleName(),
			$this->getRequest()->getControllerName(),
			$this->_date
		);
	}

	protected function getPositionsDbTableClass(): string
	{
		return $this->getModuleClassPrefix()
			. '_Model_DbTable_'
			. $this->getControllerClassName()
			. 'pos';
	}
}
