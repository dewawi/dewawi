<?php

// Raw DbTable and Row writes must never bypass the controlled technical service.
abstract class Items_Model_DbTable_Technicalrevision extends DEEC_Model_DbTable_Entity
{
	protected ?string $deletedField = null;
	protected ?string $orderingField = null;

	final public function insert(array $data) { throw new LogicException('Technical revisions are written by the technical service only'); }
	final public function update(array $data, $where) { throw new LogicException('Technical revisions are immutable'); }
	final public function delete($where) { throw new LogicException('Technical revisions cannot be deleted'); }
	final public function canDelete(array $row): bool { return false; }

	public function getClientId(): int
	{
		$owner = (new Items_Service_TechnicalDataset())->ownerForRead();
		if($this->_clientId !== null && $this->_clientId !== $owner) throw new RuntimeException('Technical owner override denied');
		return $owner;
	}

	protected function _fetch(Zend_Db_Table_Select $select)
	{
		$select->where($this->_name.'.clientid = ?', $this->getClientId());
		return parent::_fetch($select);
	}

	public function getById(int $id): ?array
	{
		$this->getClientId();
		return (new Items_Service_TechnicalDataset())->getById($this->_name, $id);
	}

	public function getByIdForUpdate(int $id): ?array
	{
		throw new LogicException('Use the controlled technical lifecycle service');
	}
}
