<?php

class Processes_ProcessController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'processes',
			'list' => 'Processes_Model_List_Processes',
			'entity' => Processes_Model_Entity_Process::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Processes_Service_CreateDataFactory();

		return $factory->build($controller, $contactId);
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		$id = (int)$row['id'];

		if (isset($values['currency'])) {
			$positionsDb = new Processes_Model_DbTable_Processpos();
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
}
