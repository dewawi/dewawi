<?php

class Sales_DeliveryorderController extends DEEC_Controller_DocumentAction
{
	protected function buildIndexView(): void
	{
		$get = new Sales_Model_Get();

		$this->buildListView([
			'viewKey' => 'deliveryorders',
			'list' => 'Sales_Model_List_Deliveryorders',
			'items' => function ($params, $options) use ($get) {
				return $get->deliveryorders($params, $options, $this->_flashMessenger);
			},
		]);
	}

	public function addAction()
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Sales_Service_CreateDataFactory();
		$data = $factory->build($controller, $contactId);

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$id = $deliveryorderDb->addDeliveryorder($data);

		return $this->_helper->redirector->gotoSimple('edit', 'deliveryorder', null, ['id' => $id]);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$deliveryorder = $this->requireRow($id);

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();

		$this->_helper->Access->lock($id, $this->_user['id'], $deliveryorder['locked'] ?? 0, $deliveryorder['lockedtime'] ?? null);

		$formFactory = new Sales_Service_EditFormFactory();
		$formData = $formFactory->create('Sales_Form_Deliveryorder');
		$form = $formData['form'];
		$options = $formData['options'];
		$toolbar = new Sales_Form_Toolbar();

		if ($request->isPost()) {
			$this->_helper->Calculate($id, $this->_date, $this->_user['id'], $deliveryorder['taxfree']);

			if ($isAjax) {
				$this->disableView();

				$ajaxSaveService = new Sales_Service_EditAjaxSaveService();

				return $this->_helper->json($ajaxSaveService->save([
					'form' => $form,
					'post' => (array)$request->getPost(),
					'id' => $id,
					'db' => $deliveryorderDb,
				]));
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				if (isset($values['currency'])) {
					$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
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

				$deliveryorderDb->updateDeliveryorder($id, $values);
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'deliveryorder', null, ['id' => $id]);
			}
		} else {
			$formFactory->populate($form, $deliveryorder, $id, 'deliveryorders', 'deliveryorder');
		}

		$vmService = new Sales_Service_DeliveryorderEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$deliveryorder);

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

		$deliveryorder = $this->requireRow($id);

		$this->ensurePdfDocumentExists($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$deliveryorder['contactid']);

		$emailFormFactory = new Sales_Service_EmailFormFactory();
		$attachmentService = new Sales_Service_AttachmentService();
		$readonlyFormFactory = new Sales_Service_ReadonlyFormFactory();

		$this->view->assign([
			'deliveryorder' => $deliveryorder,
			'contact' => $contact,
			'emailForm' => $emailFormFactory->build($deliveryorder, $contact, $controller),
			'form' => $readonlyFormFactory->build('Sales_Form_Deliveryorder', $deliveryorder, Zend_Registry::get('Zend_Locale')),
			'toolbar' => new Sales_Form_Toolbar(),
		] + $attachmentService->sync($deliveryorder, $contact, $controller));

		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);

		$data = $this->requireRow($id);

		$this->disableView();

		unset($data['id'], $data['deliveryorderid']);
		$data['title'] = $data['title'].' 2';
		$data['deliveryorderdate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorderid = $deliveryorderDb->addDeliveryorder($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $deliveryorderid, 'sales', 'deliveryorder', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');

		echo (int)$deliveryorderid;
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
			unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
		} elseif($target == 'invoice') {
			unset($data['id']);
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
			unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate']);
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
			unset($data['id'], $data['deliveryorderid'], $data['deliveryorderdate']);
			$module = 'purchases';
		}

		//Define belonging classes
		$parentClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target);

		//Create new dataset
		$parentDb = new $parentClass();
		$parentMethod = 'add'.ucfirst($target);
		$newid = $parentDb->$parentMethod($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $newid, array('sales', $module), array('deliveryorder', $target), $this->_date);

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
			$this->_flashMessenger->addMessage('MESSAGES_DELIVERY_ORDER_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'deliveryorder');
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');
		return $this->_helper->redirector->gotoSimple('view', 'deliveryorder', null, ['id' => $id]);
	}

	public function cancelAction()
	{
		$this->disableView();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			$data = $this->requireRow($id);

			$deliveryorder = new Sales_Model_DbTable_Deliveryorder();
			$deliveryorder->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}
}
