<?php

class Sales_Model_DbTable_Invoice extends Zend_Db_Table_Abstract
{

	protected $_name = 'invoice';

	public function getInvoice($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if(!$row) return false;
		return $row->toArray();
	}

	public function addInvoice($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateInvoice($id, $data)
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

	public function saveInvoice($id, $invoiceid, $invoicedate, $state, $modified, $modifiedby)
	{
		$data = array(
			'invoiceid' => $invoiceid,
			'invoicedate' => $invoicedate,
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

	public function deleteInvoice($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
