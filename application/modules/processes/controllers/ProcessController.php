<?php

class Processes_ProcessController extends DEEC_Controller_Action
{
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

		$process = $this->requireRow($id);

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

		$process = $this->requireRow($id);

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

		$data = $this->requireRow($id);

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

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			$process = $this->requireRow($id);

			$process = new Processes_Model_DbTable_Process();
			$process->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}
}
