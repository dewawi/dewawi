<?php

class Processes_ProcessController extends DEEC_Controller_Action
{
	protected function shouldPrepareAttachmentDirectory(): bool
	{
		return $this->getRequest()->getActionName() !== 'view' && parent::shouldPrepareAttachmentDirectory();
	}

	protected function getViewAssigns(array $row, $form): array
	{
		$positionsDb = (new Processes_Model_DbTable_Processpos())->setClientId((int)$row['clientid']);
		$contact = [];
		if (!empty($row['contactid']) && (new DEEC_Permission((array)$this->_user))->hasPermission('contacts', 'contact', 'view')) {
			$client = Zend_Registry::get('Client');
			$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId((int)($client['modules']['contacts'] ?? $client['id']));
			$contact = $contactDb->getContactWithID($row['contactid']) ?: [];
		}

		$activeTab = $this->getRequest()->getCookie('tab', '#tabOverview');
		return [
			'positions' => $positionsDb->getPositions((int)$row['id'])->toArray(),
			'contact' => $contact,
			'activeTab' => in_array($activeTab, ['#tabOverview', '#tabFiles', '#tabMessages'], true) ? $activeTab : '#tabOverview',
		];
	}

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
}
