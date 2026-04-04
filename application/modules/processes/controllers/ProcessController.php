<?php

class Processes_ProcessController extends Zend_Controller_Action
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
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
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

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Processes_Form_Toolbar();
		$toolbarInline = new Sales_Form_ToolbarInline();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Processes_Model_Get();
		$processes = $get->processes($params, $options, $this->_flashMessenger);

		//Get positions
		$processIDs = array();
		foreach($processes as $process) {
			array_push($processIDs, $process['id']);

			if($process['deliverydate']) {
				$deliverydate = new Zend_Date($process['deliverydate']);
							$process['deliverydate'] = $deliverydate->get('dd.MM.yyyy');
						}
		}

		$this->view->processes = $processes;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->toolbarInline = $toolbarInline;
		$this->view->positions = $this->getPositions($processIDs);
		$this->view->messages = array_merge(
						$this->_flashMessenger->getMessages(),
						$this->_flashMessenger->getCurrentMessages()
						);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Processes_Form_Toolbar();
		$toolbarInline = new Sales_Form_ToolbarInline();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Processes_Model_Get();
		$processes = $get->processes($params, $options, $this->_flashMessenger);

		//Get positions
		$processIDs = array();
		foreach($processes as $process) {
			array_push($processIDs, $process['id']);
		}

		$this->view->processes = $processes;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->toolbarInline = $toolbarInline;
		$this->view->positions = $this->getPositions($processIDs);
		$this->view->messages = array_merge(
						$this->_flashMessenger->getMessages(),
						$this->_flashMessenger->getCurrentMessages()
						);
		$this->_flashMessenger->clearCurrentMessages();
	}

	public function addAction()
	{
		$contactid = $this->_getParam('contactid', 0);

		//Get primary currency
		$currencies = new Application_Model_DbTable_Currency();
		$currency = $currencies->getPrimaryCurrency();

		$data = array();
		$data['title'] = $this->view->translate('PROCESSES_NEW_PROCESS');
		$data['deliverystatus'] = 'deliveryIsWaiting';
		$data['paymentstatus'] = 'waitingForPayment';
		$data['currency'] = $currency['code'];
		$data['state'] = 100;

		//Get contact data
		if($contactid) {
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contact = $contactDb->getContact($contactid);

			//Get basic data
			$data['contactid'] = $contact['contactid'];
			$data['billingname1'] = $contact['name1'];
			$data['billingname2'] = $contact['name2'];
			$data['billingdepartment'] = $contact['department'];

			//Get addresses
			$addressDb = new Contacts_Model_DbTable_Address();
			$addresses = $addressDb->getAddress($contact['id']);
			if(count($addresses)) {
				$data['billingstreet'] = $addresses[0]['street'];
				$data['billingpostcode'] = $addresses[0]['postcode'];
				$data['billingcity'] = $addresses[0]['city'];
				$data['billingcountry'] = $addresses[0]['country'];
			}

			//Get additonal data
			if($contact['vatin']) $data['vatin'] = $contact['vatin'];
			if($contact['currency']) $data['currency'] = $contact['currency'];
			if($contact['taxfree']) $data['taxfree'] = $contact['taxfree'];
		}

		$processDb = new Processes_Model_DbTable_Process();
		$id = $processDb->addProcess($data);

		$this->_helper->redirector->gotoSimple('edit', 'process', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);

		$isAjax = $request->isXmlHttpRequest();

		$form = new Processes_Form_Process();
		$options = $this->_helper->Options->applyFormOptions($form);

		$toolbar = new Processes_Form_Toolbar();
		$processDb  = new Processes_Model_DbTable_Process();

		// Load process
		$process = $processDb->getProcessForEdit($id);

		// Not found / not usable
		if (!$process) {
			if ($isAjax) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				return $this->_helper->json([
					'ok' => false,
					'message' => 'not_found'
				]);
			}

			$this->_flashMessenger->addMessage('MESSAGES_QUOTE_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'process');
		}

		// LOCK
		$this->_helper->Access->lock($id, $this->_user['id'], $process['locked'] ?? 0, $process['lockedtime'] ?? null);

		// POST: ajax save single field
		if ($request->isPost()) {
			// Calculate
			$this->_helper->Calculate($id, $this->_date, $this->_user['id'], $process['taxfree']);
			// Edit via ajax -> JSON
			if ($isAjax) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				$post = (array)$request->getPost();

				// Validate only posted subset
				if (!$form->isValidPartial($post)) {
					return $this->_helper->json([
						'ok' => false,
						'errors' => $this->toErrorMessages($form->getErrors(), $form),
					]);
				}

				// Filter/normalize only posted subset for DB
				$values = $form->getFilteredValuesPartial($post);

				// Save
				try {
					$processDb->updateProcess($id, $values);
				} catch (Exception $e) {
					return $this->_helper->json([
						'ok' => false,
						'message' => 'save_failed'
					]);
				}

				// Reload for derived values
				$processNew = $processDb->getProcessForEdit($id);

				// Return only changed fields for display
				$changedFields = array_keys($values);

				$display = DEEC_Display::fromRow($form, $processNew, $changedFields);

				return $this->_helper->json([
					'ok' => true,
					'id' => $id,

					// Raw DB values for JS logic
					'values' => array_intersect_key($processNew, array_flip($changedFields)),

					// Formatted for UI
					'display' => $display,

					// Optional meta: if later derived values set server-side
					'meta' => [
						'recalc' => [],
					],
				]);
			}

			// NON-AJAX POST
			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				// Keep form with submitted values and errors
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				// special side effects
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
			// GET: populate form with display values from DB
			$locale = Zend_Registry::get('Zend_Locale'); // for now, later replaced
			$processDisplay = DEEC_Display::rowToFormValues($form, $process, $locale);

			$form->setValues($processDisplay);

			$this->_helper->MultiEntityLoader->populate($form, $id, 'processs', 'process');
		}

		// build view model once and assign in one shot
		$vmService = new Processes_Service_ProcessEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$process);

		$this->view->assign(array_merge($vm, [
			'id' => $id,
			'form' => $form,
			'toolbar' => $toolbar,
			'options' => $options,
			'activeTab' => $request->getCookie('tab', null),
		]));

		// Messages
		$this->view->messages = array_merge(
			$this->_helper->flashMessenger->getMessages(),
			$this->_helper->flashMessenger->getCurrentMessages()
		);
		$this->_helper->flashMessenger->clearCurrentMessages();
	}

	public function viewAction()
	{
		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID($process['contactid']);

		//Convert dates to the display format
		if($process['processdate']) $process['processdate'] = date('d.m.Y', strtotime($process['processdate']));

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($process['currency'], 'USE_SYMBOL');

		//Convert numbers to the display format
		$process['taxes'] = $currency->toCurrency($process['taxes']);
		$process['subtotal'] = $currency->toCurrency($process['subtotal']);
		$process['total'] = $currency->toCurrency($process['total']);

		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$position->price = $currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
		}

		$toolbar = new Processes_Form_Toolbar();
		$this->view->toolbar = $toolbar;

		//Get email
		$emailDb = new Contacts_Model_DbTable_Email();
		$contact['email'] = $emailDb->getByParentId($contact['id'], 'contacts', 'contact');

		//Get email form
		$emailForm = new Contacts_Form_Emailmessage();
		if($contact['email']) {
			foreach($contact['email'] as $option) {
				$emailForm->recipient->addMultiOption($option['id'], $option['email']);
			}
		}

		//Get email templates
		$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();
		if($emailtemplate = $emailtemplateDb->getEmailtemplate('processes', 'process')) {
			if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
			if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
			if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);
			$emailForm->subject->setValue($emailtemplate['subject']);
			$emailForm->body->setValue($emailtemplate['body']);
		}

		//Copy file to attachments
		$contactUrl = $this->_helper->Directory->getUrl($contact['id']);
		$documentUrl = $this->_helper->Directory->getUrl($process['id']);

		//Get email attachments
		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		if(isset($data)) $emailattachmentDb->addEmailattachment($data);
		$attachments = $emailattachmentDb->getEmailattachments($id, 'processes', 'process');

		$this->view->process = $process;
		$this->view->contact = $contact;
		$this->view->positions = $positions;
		$this->view->emailForm = $emailForm;
		$this->view->contactUrl = $contactUrl;
		$this->view->documentUrl = $documentUrl;
		$this->view->attachments = $attachments;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($id);

		$data = $process;
		unset($data['id'], $data['processid']);
		$data['title'] = $process['title'].' 2';
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;

		echo $newID = $processDb->addProcess($data);

		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$positionData = $position->toArray();
			unset($positionData['id']);
			$positionData['parentid'] = $newID;
			$positionData['modified'] = NULL;
			$positionData['modifiedby'] = 0;
			$positionsDb->addPosition($positionData);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function cancelAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$process = new Processes_Model_DbTable_Process();
			$process->setState($id, 7);
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

	protected function getPositions($processIDs)
	{
		$positions = array();
		if(empty($processIDs)) {
			return $positions;
		}

		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positionsObject = $positionsDb->getPositions($processIDs);
		$previous = array();

		foreach($positionsObject as $position) {
			$parentId = $position->parentid;
			$ordering = $position->ordering ? $position->ordering : 0;

			// Initialize the parent array if not already
			if (!isset($previous[$parentId])) {
				$previous[$parentId] = [
					'ordering' => 0,
					'quantity' => 1,
					'deliverystatus' => '',
					'deliverydate' => null,
					'supplierorderstatus' => '',
				];
			}

			// Determine if the current position should be grouped with the previous
			$shouldMerge = $previous[$parentId]['ordering'] &&
						   $previous[$parentId]['deliverystatus'] === $position->deliverystatus &&
						   $previous[$parentId]['deliverydate'] === $position->deliverydate &&
						   $previous[$parentId]['supplierorderstatus'] === $position->supplierorderstatus;

			if($shouldMerge) {
				$positions[$parentId][$ordering] = $positions[$parentId][$previous[$parentId]['ordering']];
				$positions[$parentId][$ordering]['quantity'] = ($previous[$parentId]['quantity'] + 1);
				unset($positions[$parentId][$previous[$parentId]['ordering']]);
				$previous[$parentId]['ordering'] = $ordering ? $ordering : 0;
				$previous[$parentId]['quantity'] = $positions[$parentId][$ordering]['quantity'];
				$previous[$parentId]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
				$previous[$parentId]['deliverydate'] = $position->deliverydate ? $position->deliverydate : NULL;
				$previous[$parentId]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
			} else {
				$positions[$parentId][$ordering]['deliverystatus'] = $position->deliverystatus;
				if($position->deliverydate)
					//$deliverydate = new Zend_Date($position->deliverydate);
					//if($position->deliverydate) $position->deliverydate = $deliverydate->get('dd.MM.yyyy');
					$positions[$parentId][$ordering]['deliverydate'] = $position->deliverydate;
				if($position->itemtype == 'deliveryItem')
					$positions[$parentId][$ordering]['supplierorderstatus'] = $position->supplierorderstatus;
			}

			// Update the previous information for the current parent
			$previous[$parentId] = array();
			$previous[$parentId]['ordering'] = $ordering;
			$previous[$parentId]['quantity'] = 1;
			$previous[$parentId]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
			$previous[$parentId]['deliverydate'] = $position->deliverydate ? $position->deliverydate : NULL;
			$previous[$parentId]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
		}

		return $positions;
	}
}
