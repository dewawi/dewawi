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

	public function reserveIncrement(string $type): int
	{
		if (!in_array($type, ['contactid', 'quoteid', 'shoporderid'], true)) throw new InvalidArgumentException('Unsupported increment type');

		$adapter = $this->getAdapter();
		$select = $adapter->select()
			->from($this->_name, [$type])
			->where('clientid = ?', $this->getClientId())
			->forUpdate(true);
		$row = $adapter->fetchRow($select);
		if (!$row) throw new RuntimeException("Could not find increment $type");

		$increment = (int)$row[$type];
		$updated = $adapter->update($this->_name, [$type => $increment + 1], $adapter->quoteInto('clientid = ?', $this->getClientId()));
		if ($updated !== 1) throw new RuntimeException("Could not reserve increment $type");

		return $increment;
	}
}
