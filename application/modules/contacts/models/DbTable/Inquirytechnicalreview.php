<?php

class Contacts_Model_DbTable_Inquirytechnicalreview extends Zend_Db_Table_Abstract
{
	protected $_name = 'inquirytechnicalreview';

	public function findReview(int $owner, int $inquiryId, int $itemsOwner, int $id): ?array
	{
		$row = $this->fetchRow($this->select()->where('clientid = ?', $owner)->where('inquiryid = ?', $inquiryId)->where('itemsclientid = ?', $itemsOwner)->where('id = ?', $id));
		return $row ? $row->toArray() : null;
	}

	public function history(int $owner, int $inquiryId, int $itemsOwner, int $before = 0): array
	{
		$select = $this->select()->where('clientid = ?', $owner)->where('inquiryid = ?', $inquiryId)->where('itemsclientid = ?', $itemsOwner)->order('version DESC')->limit(50);
		if($before > 0) $select->where('version < ?', $before);
		return $this->fetchAll($select)->toArray();
	}

	public function findRequest(int $owner, int $actor, string $key): ?array
	{
		$row = $this->fetchRow($this->select()->where('clientid = ?', $owner)->where('createdby = ?', $actor)->where('request_key = ?', $key));
		return $row ? $row->toArray() : null;
	}

	public function append(array $row): int
	{
		if(!$this->getAdapter()->getConnection()->inTransaction()) throw new RuntimeException('Review append requires a transaction');
		return (int)parent::insert($row);
	}

	public function nextVersion(int $owner, int $inquiryId): int
	{
		return 1 + (int)$this->getAdapter()->fetchOne('SELECT MAX(version) FROM inquirytechnicalreview WHERE clientid = ? AND inquiryid = ?', [$owner, $inquiryId]);
	}

	public function insert(array $data) { throw new RuntimeException('Technical reviews are append-only'); }
	public function update(array $data, $where) { throw new RuntimeException('Technical reviews are immutable'); }
	public function delete($where) { throw new RuntimeException('Technical reviews are immutable'); }
}
