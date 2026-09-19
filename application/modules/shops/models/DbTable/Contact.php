<?php

class Shops_Model_DbTable_Contact extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'contact';
	protected ?string $siteField = null;
	protected ?string $orderingField = null;

	public function getContact(int $id): ?array
	{
		return $this->getById($id);
	}

	public function getContactWithID(int $contactId): ?array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('contactid = ?', $contactId)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	public function addContact(array $data): int
	{
		return $this->create($data);
	}
}
