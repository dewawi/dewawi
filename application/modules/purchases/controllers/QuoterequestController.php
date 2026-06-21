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

	protected function beforeEditSave(array $values, array $row): array
	{
		$id = (int)$row['id'];

		if (isset($values['currency'])) {
			$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
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
			unset($data['id'], $data['quoterequestid'], $data['quoterequestdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
		} elseif($target == 'invoice') {
			unset($data['id'], $data['quoterequestid'], $data['quoterequestdate'], $data['quoteid'], $data['quotedate'], $data['salesorderid'], $data['salesorderdate'], $data['invoiceid'], $data['invoicedate']);
			$module = 'sales';
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
			unset($data['id']);
			$module = 'purchases';
		}

		//Define belonging classes
		$parentClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target);

		//Create new dataset
		$parentDb = new $parentClass();
		$parentMethod = 'add'.ucfirst($target);
		$newid = $parentDb->$parentMethod($data);

		//Copy positions
		$positionsDb = new Purchases_Model_DbTable_Quoterequestpos();
		$positions = $positionsDb->getPositions($id);
		$this->_helper->Position->copyPositions($positions, $newid, array('purchases', $module), array('quoterequest', $target), $this->_date);

		$this->_flashMessenger->addMessage('MESSAGES_DOCUMENT_SUCCESFULLY_GENERATED');
		$this->_helper->redirector->gotoSimple('edit', $target, $module, array('id' => $newid));
	}
}
