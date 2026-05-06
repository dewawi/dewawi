<?php

class Purchases_PurchaseorderController extends DEEC_Controller_DocumentAction
{
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

		$purchaseorder = $this->requireRow($id);

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

		$purchaseorder = $this->requireRow($id);

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

		$data = $this->requireRow($id);

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

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			$purchaseorder = $this->requireRow($id);
			if (!$purchaseorder) return;

			$purchaseorder = new Purchases_Model_DbTable_Purchaseorder();
			$purchaseorder->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}
}
