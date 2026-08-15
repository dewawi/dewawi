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

	protected function getCreateData(): array
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Purchases_Service_CreateDataFactory();

		return $factory->build($controller, $contactId);
	}

	protected function beforeEdit(array $row)
	{
		if ($this->isReadonlyState($row)) {
			return $this->_helper->redirector->gotoSimple(
				'view',
				'quoterequest',
				null,
				['id' => (int)$row['id']]
			);
		}

		return null;
	}
}
