<?php

class Shops_Model_DbTable_Increment extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'increment';
	protected ?string $siteField = null;
	protected ?string $deletedField = null;
	protected ?string $orderingField = null;

	public function getIncrement(string $type): int
	{
		$row = $this->fetchRow($this->getPublicSelect()->limit(1));

		if (!$row) {
			throw new RuntimeException("Could not find increment $type");
		}

		return (int)$row->$type;
	}

	public function setIncrement(int $increment, string $type): void
	{
		$this->update([
			$type => $increment + 1,
		], $this->getAccessWhere());
	}
}
