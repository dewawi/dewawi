<?php

class Admin_Model_DbTable_Country extends Zend_Db_Table_Abstract
{

	protected $_name = 'country';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getCountry($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getCountries()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
		$data = $this->fetchAll($where);
		return $data;
	}

	public function addCountry($data)
	{
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateCountry($id, $data)
	{
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

	public function deleteCountry($id)
	{
		$data = array(
			'deleted' => 1
		);
		$this->update($data, 'id =' . (int)$id);
	}
}
