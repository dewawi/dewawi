<?php

class Sales_Model_DbTable_Quote extends Zend_Db_Table_Abstract
{

	protected $_name = 'quote';

	public function getQuote($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addQuote($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateQuote($id, $data)
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

	public function saveQuote($id, $quoteid, $quotedate, $state, $modified, $modifiedby)
	{
		$data = array(
			'quoteid' => $quoteid,
			'quotedate' => $quotedate,
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

	public function deleteQuote($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
