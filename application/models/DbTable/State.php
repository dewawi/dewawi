<?php

class Application_Model_DbTable_State extends Zend_Db_Table_Abstract
{

	protected $_name = 'state';

	public function getState($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addState($opportunityid, $contactid, $title, $orderdate, $deliverydate, $clientid, $created)
	{
		$data = array(
			'opportunityid' => $opportunityid,
			'contactid' => $contactid,
			'title' => $title,
			'orderdate' => $orderdate,
			'deliverydate' => $deliverydate,
			'clientid' => $clientid,
			'created' => $created
		);
		$this->insert($data);
		return $this->lastInsertId();
	}

	public function updateState($id, $uomid, $opportunityid)
	{
		$data = array(
			'uomid' => $uomid,
			'opportunityid' => $opportunityid,
			'contactid' => $contactid,
			'title' => $title,
			'info' => $info,
			'footer' => $footer,
			'vatin' => $vatin,
			'uomdate' => $uomdate,
			'orderdate' => $orderdate,
			'deliverydate' => $deliverydate,
			'billingname1' => $billingname1,
			'billingname2' => $billingname2,
			'billingdepartment' => $billingdepartment,
			'billingstreet' => $billingstreet,
			'billingpostcode' => $billingpostcode,
			'billingcity' => $billingcity,
			'billingcountry' => $billingcountry,
			'shippingname1' => $shippingname1,
			'shippingname2' => $shippingname2,
			'shippingdepartment' => $shippingdepartment,
			'shippingstreet' => $shippingstreet,
			'shippingpostcode' => $shippingpostcode,
			'shippingcity' => $shippingcity,
			'shippingcountry' => $shippingcountry,
			'shippingphone' => $shippingphone,
			'modified' => $modified
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteState($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
