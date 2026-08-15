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
}
