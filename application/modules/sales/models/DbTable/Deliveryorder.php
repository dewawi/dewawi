<?php

class Sales_Model_DbTable_Deliveryorder extends Zend_Db_Table_Abstract
{

	protected $_name = 'deliveryorder';

	public function getDeliveryorder($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addDeliveryorder($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateDeliveryorder($id, $data)
	{
		$this->update($data, 'id = '.(int)$id);
	}

	public function updateTotal($id, $subtotal, $taxes, $total, $modified, $modifiedby)
	{
		$data = array(
			'subtotal' => $subtotal,
			'taxes' => $taxes,
			'total' => $total
			//'modified' => $modified,
			//'modifiedby' => $modifiedby
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function saveDeliveryorder($id, $deliveryorderid, $deliveryorderdate, $state, $modified, $modifiedby)
	{
		$data = array(
			'deliveryorderid' => $deliveryorderid,
			'deliveryorderdate' => $deliveryorderdate,
			'state' => $state,
			'modified' => $modified,
			'modifiedby' => $modifiedby
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function setState($id, $state, $modified, $modifiedby)
	{
		$data = array(
			'state' => $state,
			'modified' => $modified,
			'modifiedby' => $modifiedby
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function lock($id, $locked, $lockedtime)
	{
		$data = array(
			'locked' => $locked,
			'lockedtime' => $lockedtime
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function unlock($id)
	{
		$data = array(
			'locked' => 0
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteDeliveryorder($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
