<?php

class Purchases_QuoterequestController extends DEEC_Controller_DocumentAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'quoterequests',
			'list' => 'Purchases_Model_List_Quoterequests',
			'entity' => Purchases_Model_Entity_Quoterequest::listConfig(),
		]);
	}

	public function addAction()
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Purchases_Service_CreateDataFactory();
		$data = $factory->build($controller, $contactId);

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();
		$id = $quoterequestDb->addQuoterequest($data);

		return $this->_helper->redirector->gotoSimple('edit', 'quoterequest', null, ['id' => $id]);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$quoterequest = $this->requireRow($id);

		if ($this->isReadonlyState($quoterequest)) {
			return $this->_helper->redirector->gotoSimple('view', 'quoterequest', null, ['id' => $id]);
		}

		$quoterequestDb = new Purchases_Model_DbTable_Quoterequest();

		$this->_helper->Access->lock($id, $this->_user['id'], $quoterequest['locked'] ?? 0, $quoterequest['lockedtime'] ?? null);

		$formFactory = new Purchases_Service_EditFormFactory();
		$formData = $formFactory->create('Purchases_Form_Quoterequest');
		$form = $formData['form'];
		$options = $formData['options'];
		$toolbar = new Purchases_Form_Toolbar();

		if ($request->isPost()) {
			$this->_helper->Calculate($id, $this->_date, $this->_user['id'], $quoterequest['taxfree']);

			if ($isAjax) {
				$this->disableView();

				$ajaxSaveService = new Purchases_Service_EditAjaxSaveService();

				return $this->_helper->json($ajaxSaveService->save([
					'form' => $form,
					'post' => (array)$request->getPost(),
					'id' => $id,
					'db' => $quoterequestDb,
				]));
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				if (isset($values['currency'])) {
					$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
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

				$quoterequestDb->updateQuoterequest($id, $values);
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'quoterequest', null, ['id' => $id]);
			}
		} else {
			$formFactory->populate($form, $quoterequest, $id, 'quoterequests', 'quoterequest');
		}

		$vmService = new Purchases_Service_QuoterequestEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$quoterequest);

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

		$quoterequest = $this->requireRow($id);

		$this->ensurePdfDocumentExists($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$quoterequest['contactid']);

		$emailFormFactory = new Purchases_Service_EmailFormFactory();
		$attachmentService = new Purchases_Service_AttachmentService();
		$readonlyFormFactory = new Purchases_Service_ReadonlyFormFactory();

		$this->view->assign([
			'quoterequest' => $quoterequest,
			'contact' => $contact,
			'emailForm' => $emailFormFactory->build($quoterequest, $contact, $controller),
			'form' => $readonlyFormFactory->build('Purchases_Form_Quoterequest', $quoterequest, Zend_Registry::get('Zend_Locale')),
			'toolbar' => new Purchases_Form_Toolbar(),
		] + $attachmentService->sync($quoterequest, $contact, $controller));

		$this->assignMessages();
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
			unset($data['id'], $data['quoterequestid'], $data['quoterequestdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
		} elseif($target == 'invoice') {
			unset($data['id'], $data['quoterequestid'], $data['quoterequestdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
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
			unset($data['id']);
			$module = 'purchases';
		}

		//Define belonging classes
		$parentClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target);

		//Create new dataset
		$parentDb = new $parentClass();
		$parentMethod = 'add'.ucfirst($target);
		$newid = $parentDb->$parentMethod($data);

		//Copy positions
		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $newid, array('purchases', $module), array('quoterequest', $target), $this->_date);

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
			$this->_flashMessenger->addMessage($this->getNotFoundMessage());
			return $this->_helper->redirector->gotoSimple('index', 'quoterequest');
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');
		return $this->_helper->redirector->gotoSimple('view', 'quoterequest', null, ['id' => $id]);
	}

	public function cancelAction()
	{
		$this->disableView();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			$quoterequest = $this->requireRow($id);

			$quoterequest = new Purchases_Model_DbTable_Quoterequest();
			$quoterequest->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}
}
