<?php

class Sales_Model_DbTable_Creditnote extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'creditnote';

	public function addCreditnote($data)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['clientid'] = $this->_client['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	protected function prepareCopyData(array $data): array
	{
		$data = parent::prepareCopyData($data);

		unset($data['creditnoteid']);

		$data['creditnotedate'] = null;
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['modified'] = null;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = null;

		return $data;
	}
}
