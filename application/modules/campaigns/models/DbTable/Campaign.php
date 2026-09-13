<?php

class Campaigns_Model_DbTable_Campaign extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'campaign';

	public function getCampaign($id)
	{
		$id = (int)$id;
		$row = $this->getById($id);

		if(!$row) {
			throw new RuntimeException("Could not find campaign $id");
		}

		return $row;
	}

	public function getLatestCampaigns()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		return $this->fetchAll($where, 'id DESC', 5);
	}
}
