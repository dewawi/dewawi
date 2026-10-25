<?php

class Tasks_TaskController extends DEEC_Controller_Action
{
	public function addAction()
	{
		if($this->_getParam('sourcequote') === null) return parent::addAction();

		$id = filter_var($this->_getParam('sourcequote'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		$permission = new DEEC_Permission((array)$this->_user);
		if(empty($this->_user['id']) || !$permission->hasActionPermission('sales', 'quote', 'view')
			|| ($permission->hasModule('tasks') && !$permission->hasActionPermission('tasks', 'task', 'add'))) {
			throw new Zend_Controller_Action_Exception('Forbidden', 403);
		}

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quoteDb->setClientId((int)$this->_user['clientid']);
		$quote = $id ? $quoteDb->getById($id) : null;
		if(!$quote) throw new Zend_Controller_Action_Exception('Quote not found', 404);
		$quote = (new Sales_Service_QuoteMonitoring())->enrich([$quote])[0];
		if(empty($quote['monitoring_followup_date'])) throw new Zend_Controller_Action_Exception('Quote follow-up unavailable', 409);

		$client = Zend_Registry::get('Client');
		$contactClient = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$this->_user['clientid'];
		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->fetchRow($contactDb->select()->where('contactid = ?', (int)$quote['contactid'])
			->where('clientid = ?', $contactClient)->where('deleted = ?', 0));
		if(!$contact || $contactClient <= 0) throw new Zend_Controller_Action_Exception('Contact not found', 404);

		$formData = $this->getEditForm();
		$form = $formData['form'];
		foreach($form->getElements() as $name => $element) {
			if(($element['format']['type'] ?? '') === 'date') $form->setElementData($name, ['attribs' => ['data-format' => 'yyyy-mm-dd']]);
		}
		$defaults = array_intersect_key($quote, $form->getElements());
		unset($defaults['id']);
		$defaults['title'] = sprintf($this->view->translate('FEEDBACK_QUOTE_TASK_TITLE'), (int)$quote['quoteid']);
		$defaults['quoteid'] = (int)$quote['quoteid'];
		$defaults['contactid'] = (int)$contact->contactid;
		$defaults['duedate'] = max(date('Y-m-d'), $quote['monitoring_followup_date']);
		$defaults['responsible'] = (int)$this->_user['id'];
		$defaults['state'] = 100;
		$defaults['deliverystatus'] = 'deliveryIsWaiting';
		$defaults['paymentstatus'] = 'waitingForPayment';
		$form->setValues($defaults);

		$request = $this->getRequest();
		if($request->isPost() && $request->getPost('create_followup') === '1' && $form->isValid((array)$request->getPost())) {
			$data = $form->getFilteredValues();
			unset($data['id']);
			$data['quoteid'] = $defaults['quoteid'];
			$data['contactid'] = $defaults['contactid'];
			$data['state'] = 100;
			$db = $this->getDb()->setClientId((int)$this->_user['clientid']);
			$adapter = $db->getAdapter();
			try {
				$adapter->beginTransaction();
				$id = $db->create($this->beforeCreate($data));
				$this->afterCreate($id, $data);
				$adapter->commit();
			} catch (Exception $e) {
				$adapter->rollBack();
				throw $e;
			}
			return $this->_helper->redirector->gotoSimple('edit', 'task', 'tasks', ['id' => $id]);
		}

		$this->view->assign(['form' => $form, 'quoteFollowup' => true, 'options' => $formData['options']]);
		$this->_helper->viewRenderer->setRender('edit');
	}

	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'tasks',
			'list' => 'Tasks_Model_List_Tasks',
			'entity' => Tasks_Model_Entity_Task::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$quoteId = (int)$this->_getParam('quoteid', 0);

		$currencies = new Application_Model_DbTable_Currency();
		$currency = $currencies->getPrimaryCurrency();

		$data = [
			'title' => $this->view->translate('TASKS_NEW_TASK'),
			'deliverystatus' => 'deliveryIsWaiting',
			'paymentstatus' => 'waitingForPayment',
			'currency' => $currency['code'],
			'state' => 100,
		];

		if($quoteId > 0) $data['quoteid'] = $quoteId;

		if($contactId > 0) {
			$contactDataFactory = new Contacts_Service_ContactDataFactory();
			$data = array_merge($data, $contactDataFactory->getContactData($contactId));
		}

		return $data;
	}

	public function getAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$element = $this->_getParam('element', null);

		$form = new Tasks_Form_Toolbar();

		if(isset($form->$element)) {
			$this->_helper->Options->getOptions($form);
			echo Zend_Json::encode($form->$element->getMultiOptions());
		} else {
			echo Zend_Json::encode([
				'message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS')
			]);
		}
	}

	protected function getPositions($taskIDs)
	{
		$positions = [];

		if(!empty($taskIDs)) {
			$positionsDb = new Tasks_Model_DbTable_Taskpos();
			$positionsObject = $positionsDb->getPositions($taskIDs);

			$previous = [];

			foreach($positionsObject as $position) {
				if(!isset($previous[$position->parentid])) {
					$previous[$position->parentid] = [
						'ordering' => 0,
						'quantity' => 1,
						'deliverystatus' => '',
						'deliverydate' => null,
						'supplierorderstatus' => '',
					];
				}

				if(
					$previous[$position->parentid]['ordering']
					&& $previous[$position->parentid]['deliverystatus'] == $position->deliverystatus
					&& $previous[$position->parentid]['deliverydate'] == $position->deliverydate
					&& $previous[$position->parentid]['supplierorderstatus'] == $position->supplierorderstatus
				) {
					$positions[$position->parentid][$position->ordering] =
						$positions[$position->parentid][$previous[$position->parentid]['ordering']];

					$positions[$position->parentid][$position->ordering]['quantity'] =
						$previous[$position->parentid]['quantity'] + 1;

					unset($positions[$position->parentid][$previous[$position->parentid]['ordering']]);
				} else {
					$positions[$position->parentid][$position->ordering]['deliverystatus'] =
						$position->deliverystatus;

					if($position->deliverydate) {
						$positions[$position->parentid][$position->ordering]['deliverydate'] =
							$position->deliverydate;
					}

					if($position->itemtype == 'deliveryItem') {
						$positions[$position->parentid][$position->ordering]['supplierorderstatus'] =
							$position->supplierorderstatus;
					}
				}

				$previous[$position->parentid] = [
					'ordering' => $position->ordering ? $position->ordering : 0,
					'quantity' => isset($positions[$position->parentid][$position->ordering]['quantity'])
						? $positions[$position->parentid][$position->ordering]['quantity']
						: 1,
					'deliverystatus' => $position->deliverystatus ? $position->deliverystatus : '',
					'deliverydate' => $position->deliverydate ? $position->deliverydate : null,
					'supplierorderstatus' => $position->supplierorderstatus ? $position->supplierorderstatus : '',
				];
			}
		}

		return $positions;
	}
}
