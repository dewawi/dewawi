<?php

class Admin_Model_DbTable_Client extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'client';

	protected function prepareCreateData(array $data): array
	{
		if (empty($this->_user['admin'])) {
			$data['parentid'] = (int)$this->_user['clientid'];
		}

		$data['created'] = $this->_date;
		$data['createdby'] = $this->getUserId();

		return $data;
	}

	protected function prepareUpdateData(array $data): array
	{
		if (empty($this->_user['admin'])) {
			unset($data['parentid'], $data['activated']);
		}

		return parent::prepareUpdateData($data);
	}

	protected function getAccessWhere(): array
	{
		if (!empty($this->_user['admin'])) {
			return [];
		}

		$clientId = (int)$this->_user['clientid'];

		return [
			'('
				. $this->getAdapter()->quoteInto('id = ?', $clientId)
				. ' OR '
				. $this->getAdapter()->quoteInto('parentid = ?', $clientId)
			. ')',
		];
	}
}
