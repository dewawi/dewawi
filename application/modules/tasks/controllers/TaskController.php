<?php

class Tasks_TaskController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'tasks',
			'list' => 'Tasks_Model_List_Tasks',
			'entity' => Tasks_Model_Entity_Task::listConfig(),
		]);

		$tasks = $this->view->tasks ?? [];

		$taskIDs = [];
		foreach($tasks as $key => $task) {
			$taskIDs[] = $task['id'];

			if(!empty($task['deliverydate'])) {
				$deliverydate = new Zend_Date($task['deliverydate']);
				$tasks[$key]['deliverydate'] = $deliverydate->get('dd.MM.yyyy');
			}
		}

		$this->view->tasks = $tasks;
		$this->view->positions = $this->getPositions($taskIDs);
	}

	protected function getCreateData(): array
	{
		$contactId = (int)$this->_getParam('contactid', 0);

		$currencies = new Application_Model_DbTable_Currency();
		$currency = $currencies->getPrimaryCurrency();

		$data = [
			'title' => $this->view->translate('TASKS_NEW_TASK'),
			'deliverystatus' => 'deliveryIsWaiting',
			'paymentstatus' => 'waitingForPayment',
			'currency' => $currency['code'],
			'state' => 100,
		];

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
