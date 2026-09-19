<?php

class Shops_Model_DbTable_Address extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'address';
	protected ?string $siteField = null;

	public function getAddresses(int $contactId): array
	{
		return $this->getByParentId($contactId, 'contacts', 'contact');
	}

	public function addAddress(int $contactId, array $data): int
	{
		return $this->createForParent($contactId, 'contacts', 'contact', $data);
	}
}
