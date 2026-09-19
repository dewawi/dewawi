<?php

class Shops_Model_DbTable_Emailmessage extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'emailmessage';
	protected ?string $siteField = null;
	protected ?string $orderingField = null;

	public function getEmailmessage(int $id): ?array
	{
		return $this->getById($id);
	}

	public function addEmailmessage(array $data): int
	{
		$data['clientid'] = $this->getClientId();
		$data['messagesent'] = $this->_date;
		$data['messagesentby'] = 0;

		$this->insert($data);

		return (int)$this->getAdapter()->lastInsertId();
	}

	public function updateEmailmessage(int $id, array $data): void
	{
		$this->update($data, $this->getEntityWhere($id));
	}
}
