<?php

class Sales_ReminderController extends DEEC_Controller_DocumentAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'reminders',
			'list' => 'Sales_Model_List_Reminders',
			'entity' => Sales_Model_Entity_Reminder::listConfig(),
		]);
	}

	public function addAction()
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Sales_Service_CreateDataFactory();
		$data = $factory->build($controller, $contactId);

		$reminderDb = new Sales_Model_DbTable_Reminder();
		$id = $reminderDb->addReminder($data);

		return $this->_helper->redirector->gotoSimple('edit', 'reminder', null, ['id' => $id]);
	}

	protected function beforeEdit(array $row)
	{
		if ($this->isReadonlyState($row)) {
			return $this->_helper->redirector->gotoSimple(
				'view',
				'reminder',
				null,
				['id' => (int)$row['id']]
			);
		}

		return null;
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		$id = (int)$row['id'];

		if (isset($values['currency'])) {
			$positionsDb = new Sales_Model_DbTable_Reminderpos();
			$positions = $positionsDb->getPositions($id);

			foreach ($positions as $position) {
				$positionsDb->updatePosition($position->id, [
					'currency' => $values['currency'],
				]);
			}
		}

		if (isset($values['taxfree'])) {
			$calculations = $this->_helper->Calculate(
				$id,
				$this->_date,
				$this->_user['id'],
				$values['taxfree']
			);

			$values['subtotal'] = $calculations['row']['subtotal'];
			$values['taxes'] = $calculations['row']['taxes']['total'];
			$values['total'] = $calculations['row']['total'];
		}

		return $values;
	}

	public function viewAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$controller = $this->getRequest()->getControllerName();

		$reminder = $this->requireRow($id);

		$this->ensurePdfDocumentExists($id);

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$reminder['contactid']);

		$emailFormFactory = new Sales_Service_EmailFormFactory();
		$attachmentService = new Sales_Service_AttachmentService();
		$readonlyFormFactory = new Sales_Service_ReadonlyFormFactory();

		$this->view->assign([
			'reminder' => $reminder,
			'contact' => $contact,
			'emailForm' => $emailFormFactory->build($reminder, $contact, $controller),
			'form' => $readonlyFormFactory->build('Sales_Form_Reminder', $reminder, Zend_Registry::get('Zend_Locale')),
			'toolbar' => new Sales_Form_Toolbar(),
		] + $attachmentService->sync($reminder, $contact, $controller));

		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);

		$data = $this->requireRow($id);

		$this->disableView();

		unset($data['id'], $data['reminderid']);
		$data['title'] = $data['title'].' 2';
		$data['reminderdate'] = NULL;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		$reminderDb = new Sales_Model_DbTable_Reminder();
		$reminderid = $reminderDb->addReminder($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Reminderpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $reminderid, 'sales', 'reminder', $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');

		echo (int)$reminderid;
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
			unset($data['id'], $data['reminderid'], $data['reminderdate']);
			$module = 'sales';
		} elseif($target == 'invoice') {
			unset($data['id'], $data['reminderid'], $data['reminderdate']);
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
			unset($data['id'], $data['reminderid'], $data['reminderdate']);
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
			unset($data['id'], $data['reminderid'], $data['reminderdate']);
			$module = 'purchases';
		} elseif($target == 'process') {
			/*$form = new Processes_Form_Process();
			$elements = $form->getElements();
			foreach($elements as $key => $value) {
				if(isset($invoice[$key])) $data[$key] = $invoice[$key];
			}*/
			//$data['prepaymenttotal'] = $data['prepayment'];
			$data['contactid'] = $data['contactid'];
			$data['deliverystatus'] = 'deliveryIsWaiting';
			$data['supplierorderstatus'] = 'supplierNotOrdered';
			$data['paymentstatus'] = 'waitingForPayment';
			unset($data['id']);
			unset($data['pdfshowprices'], $data['pdfshowdiscounts'], $data['pdfshowoptions'], $data['pdfshowattributes'], $data['pdfshowcover']);
			$module = 'processes';
		}

		//Define belonging classes
		$parentClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target);

		//Create new dataset
		$parentDb = new $parentClass();
		$parentMethod = 'add'.ucfirst($target);
		$newid = $parentDb->$parentMethod($data);

		//Copy positions
		$positionsDb = new Sales_Model_DbTable_Reminderpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $newid, array('sales', $module), array('reminder', $target), $this->_date);

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
			$this->_flashMessenger->addMessage('MESSAGES_REMINDER_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'reminder');
		}

		$this->_flashMessenger->addMessage('MESSAGES_SAVED');
		return $this->_helper->redirector->gotoSimple('view', 'reminder', null, ['id' => $id]);
	}

	public function cancelAction()
	{
		$this->disableView();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);

			$data = $this->requireRow($id);

			$reminder = new Sales_Model_DbTable_Reminder();
			$reminder->setState($id, 106);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_CANCELLED');
	}
}
