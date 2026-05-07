<?php

class Sales_CreditnoteController extends DEEC_Controller_DocumentAction
{
	protected function buildIndexView(): void
	{
		$get = new Sales_Model_Get();

		$this->buildListView([
			'viewKey' => 'creditnotes',
			'list' => 'Sales_Model_List_Creditnotes',
			'items' => function ($params, $options) use ($get) {
				return $get->creditnotes($params, $options, $this->_flashMessenger);
			},
		]);
	}

	public function addAction()
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Sales_Service_CreateDataFactory();
		$data = $factory->build($controller, $contactId);

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$id = $creditnoteDb->addCreditnote($data);

		return $this->_helper->redirector->gotoSimple('edit', 'creditnote', null, ['id' => $id]);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$creditnote = $this->requireRow($id);

		if ($this->isReadonlyState($creditnote)) {
			return $this->_helper->redirector->gotoSimple('view', 'creditnote', null, ['id' => $id]);
		}

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();

		$this->_helper->Access->lock($id, $this->_user['id'], $creditnote['locked'] ?? 0, $creditnote['lockedtime'] ?? null);

		$formFactory = new Sales_Service_EditFormFactory();
		$formData = $formFactory->create('Sales_Form_Creditnote');
		$form = $formData['form'];
		$options = $formData['options'];
		$toolbar = new Sales_Form_Toolbar();

		if ($request->isPost()) {
			$this->_helper->Calculate($id, $this->_date, $this->_user['id'], $creditnote['taxfree']);

			if ($isAjax) {
				$this->disableView();

				$ajaxSaveService = new Sales_Service_EditAjaxSaveService();

				return $this->_helper->json($ajaxSaveService->save([
					'form' => $form,
					'post' => (array)$request->getPost(),
					'id' => $id,
					'db' => $creditnoteDb,
				]));
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				if (isset($values['currency'])) {
					$positionsDb = new Sales_Model_DbTable_Creditnotepos();
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

				$creditnoteDb->updateCreditnote($id, $values);
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'creditnote', null, ['id' => $id]);
			}
		} else {
			$formFactory->populate($form, $creditnote, $id, 'creditnotes', 'creditnote');
		}

		$vmService = new Sales_Service_CreditnoteEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$creditnote);

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

		$creditnote = $this->requireRow($id);

		$this->ensurePdfDocumentExists($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$creditnote['contactid']);

		$emailFormFactory = new Sales_Service_EmailFormFactory();
		$attachmentService = new Sales_Service_AttachmentService();
		$readonlyFormFactory = new Sales_Service_ReadonlyFormFactory();

		$this->view->assign([
			'creditnote' => $creditnote,
			'contact' => $contact,
			'emailForm' => $emailFormFactory->build($creditnote, $contact, $controller),
			'form' => $readonlyFormFactory->build('Sales_Form_Creditnote', $creditnote, Zend_Registry::get('Zend_Locale')),
			'toolbar' => new Sales_Form_Toolbar(),
		] + $attachmentService->sync($creditnote, $contact, $controller));

		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);

		$data = $this->requireRow($id);

		$this->disableView();

		unset($data['id'], $data['creditnoteid']);
		$data['title'] = $data['title'].' 2';
		$data['creditnotedate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$creditnoteDb = new Sales_Model_DbTable_Creditnote();
		$creditnoteid = $creditnoteDb->addCreditnote($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $creditnoteid, 'sales', 'creditnote', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');

		echo (int)$creditnoteid;
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

		if($target == 'salesorder') {
			unset($data['id'], $data['creditnoteid'], $data['creditnotedate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
		} elseif($target == 'invoice') {
			unset($data['id'], $data['invoiceid'], $data['invoicedate']);
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
			unset($data['id'], $data['creditnoteid'], $data['creditnotedate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
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
			unset($data['id'], $data['creditnoteid'], $data['creditnotedate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'purchases';
		}

		//Define belonging classes
		$parentClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target);

		//Create new dataset
		$parentDb = new $parentClass();
		$parentMethod = 'add'.ucfirst($target);
		$newid = $parentDb->$parentMethod($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $newid, array('sales', $module), array('creditnote', $target), $this->_date);

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
			$this->_flashMessenger->addMessage('MESSAGES_CREDIT_NOTE_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'creditnote');
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');
		return $this->_helper->redirector->gotoSimple('view', 'creditnote', null, ['id' => $id]);
	}

	public function cancelAction()
	{
		$this->disableView();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			$data = $this->requireRow($id);

			$creditnote = new Sales_Model_DbTable_Creditnote();
			$creditnote->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}
}
