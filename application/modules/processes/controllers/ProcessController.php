<?php

class Processes_ProcessController extends DEEC_Controller_Action
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
		$form = new Processes_Form_Toolbar();

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

	protected function requireProcess(int $id, bool $silent = false): ?array
	{
		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcessForEdit($id);

		if ($process) {
			return $process;
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
		$this->_flashMessenger->addMessage('MESSAGES_PROCESS_NOT_FOUND');
		$this->_helper->redirector->gotoSimple('index', 'process');

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
		$toolbar = new Processes_Form_Toolbar();
		$toolbarInline = new Processes_Form_ToolbarInline();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Processes_Model_Get();
		$processes = $get->processes($params, $options, $this->_flashMessenger);

		$this->view->processes = $processes;
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

		$factory = new Processes_Service_CreateDataFactory();
		$data = $factory->build($controller, $contactId);

		$processDb = new Processes_Model_DbTable_Process();
		$id = $processDb->addProcess($data);

		return $this->_helper->redirector->gotoSimple('edit', 'process', null, ['id' => $id]);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$process = $this->requireProcess($id);
		if (!$process) return;

		$processDb = new Processes_Model_DbTable_Process();

		$this->_helper->Access->lock($id, $this->_user['id'], $process['locked'] ?? 0, $process['lockedtime'] ?? null);

		$formFactory = new Processes_Service_EditFormFactory();
		$formData = $formFactory->create('Processes_Form_Process');
		$form = $formData['form'];
		$options = $formData['options'];
		$toolbar = new Processes_Form_Toolbar();

		if ($request->isPost()) {
			$this->_helper->Calculate($id, $this->_date, $this->_user['id'], $process['taxfree']);

			if ($isAjax) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				$ajaxSaveService = new Processes_Service_EditAjaxSaveService();

				return $this->_helper->json($ajaxSaveService->save([
					'form' => $form,
					'post' => (array)$request->getPost(),
					'id' => $id,
					'db' => $processDb,
					'loadMethod' => 'getProcessForEdit',
					'updateMethod' => 'updateProcess',
				]));
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				if (isset($values['currency'])) {
					$positionsDb = new Processes_Model_DbTable_Processpos();
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

				$processDb->updateProcess($id, $values);
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'process', null, ['id' => $id]);
			}
		} else {
			$formFactory->populate($form, $process, $id, 'processes', 'process');
		}

		$vmService = new Processes_Service_ProcessEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$process);

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

		$process = $this->requireProcess($id);
		if (!$process) return;

		$this->ensurePdfDocumentExists($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$process['contactid']);

		$emailFormFactory = new Processes_Service_EmailFormFactory();
		$attachmentService = new Processes_Service_AttachmentService();
		$readonlyFormFactory = new Processes_Service_ReadonlyFormFactory();

		$this->view->assign([
			'process' => $process,
			'contact' => $contact,
			'emailForm' => $emailFormFactory->build($process, $contact, $controller),
			'form' => $readonlyFormFactory->build('Processes_Form_Process', $process, Zend_Registry::get('Zend_Locale')),
			'toolbar' => new Processes_Form_Toolbar(),
		] + $attachmentService->sync($process, $contact, $controller));

		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);

		$data = $this->requireProcess($id);
		if (!$data) return;

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		unset($data['id'], $data['processid']);
		$data['title'] = $data['title'].' 2';
		$data['processdate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$processDb = new Processes_Model_DbTable_Process();
		$processid = $processDb->addProcess($data);

		//Copy positions
		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $processid, 'processes', 'process', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');

		echo (int)$processid;
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

			$this->_flashMessenger->addMessage('MESSAGES_PROCESS_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'process');
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
			$this->_flashMessenger->addMessage('MESSAGES_PROCESS_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'process');
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');
		return $this->_helper->redirector->gotoSimple('view', 'process', null, ['id' => $id]);
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
			$this->_flashMessenger->addMessage('MESSAGES_PROCESS_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'process');
		}

		return $this->sendPdfResponse($result);
	}

	protected function generatePdfDocument(int $id, array $options = []): array
	{
		$process = $this->requireProcess($id, true);
		if (!$process) {
			throw new RuntimeException('Process not found');
		}

		if (!empty($options['finalize'])) {
			$finalizeService = new Processes_Service_DocumentFinalizeService();
			$process = $finalizeService->finalize($process, 'process');
		}

		$pdf = new DEEC_Pdf();

		return $pdf->generate([
			'module' => 'processes',
			'controller' => 'process',
			'documentId' => (int)$process['id'],
			'output' => $options['output'] ?? 'file',
			'templateid' => $options['templateid'] ?? null,
			'storage' => $options['storage'] ?? 'cache',
			'overwrite' => !empty($options['overwrite']),
		]);
	}

	protected function ensurePdfDocumentExists(int $id): void
	{
		$process = $this->requireProcess($id, true);
		if (!$process) {
			return;
		}

		if (empty($process['id']) || empty($process['contactid']) || empty($process['clientid'])) {
			return;
		}

		$docIdField = 'processid';

		// Do not generate contact PDF before document is finalized
		if (empty($process[$docIdField]) || empty($process['filename'])) {
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

			$process = $this->requireProcess($id);
			if (!$process) return;

			$process = new Processes_Model_DbTable_Process();
			$process->setState($id, 106);
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
