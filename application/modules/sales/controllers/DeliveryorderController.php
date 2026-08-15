<?php

class Sales_DeliveryorderController extends DEEC_Controller_DocumentAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'deliveryorders',
			'list' => 'Sales_Model_List_Deliveryorders',
			'entity' => Sales_Model_Entity_Deliveryorder::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Sales_Service_CreateDataFactory();

		return $factory->build($controller, $contactId);
	}

	protected function beforeEdit(array $row)
	{
		if ($this->isReadonlyState($row)) {
			return $this->_helper->redirector->gotoSimple(
				'view',
				'deliveryorder',
				null,
				['id' => (int)$row['id']]
			);
		}

		return null;
	}
}
