<?php

class Application_Model_DbTable_Tagentity extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'tagentity';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getTagEntities($module, $controller, $id)
	{
		$tags = $this->fetchAll(
			$this->select()
				->setIntegrityCheck(false)
				->from(array('t' => 'tagentity'))
				->joinLeft(
					array('tag' => 'tag'),
					't.tagid = tag.id AND tag.clientid = t.clientid AND tag.module = t.module AND tag.controller = t.controller AND tag.shopid = 0 AND tag.deleted = 0',
					array('title as tag')
				)
				->group('t.id')
				->where('t.entityid = ?', $id)
				->where('t.module = ?', $module)
				->where('t.controller = ?', $controller)
				->where('t.clientid = ?', $this->getClientId())
				->where('t.deleted = ?', 0)
				->order(array('t.ordering ASC', 't.id ASC'))
		);
		return $tags->toArray();
	}

	public function getTagEntitiesForParents(string $module, string $controller, array $ids): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function(int $id): bool {
			return $id > 0;
		})));

		if (!$ids) {
			return [];
		}

		$rows = $this->fetchAll(
			$this->select()
				->setIntegrityCheck(false)
				->from(['t' => 'tagentity'])
				->join(
					['tag' => 'tag'],
					't.tagid = tag.id AND tag.clientid = t.clientid AND tag.module = t.module AND tag.controller = t.controller AND tag.shopid = 0 AND tag.deleted = 0',
					['title AS tag']
				)
				->where('t.entityid IN (?)', $ids)
				->where('t.module = ?', $module)
				->where('t.controller = ?', $controller)
				->where('t.clientid = ?', $this->getClientId())
				->where('t.deleted = ?', 0)
				->order(['t.entityid ASC', 't.ordering ASC', 't.id ASC'])
		);

		$tags = [];

		foreach($rows as $row) {
			$tags[(int)$row->entityid][] = $row->toArray();
		}

		return $tags;
	}

	public function existsForParent(int $parentId, string $module, string $controller, int $tagId, int $excludeId = 0): bool
	{
		$select = $this->select()
			->from($this->_name, ['id'])
			->where('entityid = ?', $parentId)
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('tagid = ?', $tagId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->limit(1);

		if ($excludeId > 0) {
			$select->where('id != ?', $excludeId);
		}

		return (bool)$this->fetchRow($select);
	}

	public function getAssignedParentIds(array $parentIds, string $module, string $controller, int $tagId, bool $forUpdate = false): array
	{
		$parentIds = $this->normalizeParentIds($parentIds);

		if (!$parentIds || $tagId <= 0) {
			return [];
		}

		$rows = $this->fetchAll(
			$this->select()
				->from($this->_name, ['entityid'])
				->where('entityid IN (?)', $parentIds)
				->where('module = ?', $module)
				->where('controller = ?', $controller)
				->where('tagid = ?', $tagId)
				->where('clientid = ?', $this->getClientId())
				->where('deleted = ?', 0)
				->forUpdate($forUpdate)
		);

		$assigned = [];

		foreach($rows as $row) {
			$assigned[(int)$row->entityid] = true;
		}

		return array_keys($assigned);
	}

	public function deleteForParents(array $parentIds, string $module, string $controller, int $tagId): int
	{
		$parentIds = $this->normalizeParentIds($parentIds);

		if (!$parentIds || $tagId <= 0) {
			return 0;
		}

		return $this->update(
			$this->prepareUpdateData(['deleted' => 1]),
			[
				$this->getAdapter()->quoteInto('entityid IN (?)', $parentIds),
				$this->getAdapter()->quoteInto('module = ?', $module),
				$this->getAdapter()->quoteInto('controller = ?', $controller),
				$this->getAdapter()->quoteInto('tagid = ?', $tagId),
				$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
				$this->getAdapter()->quoteInto('deleted = ?', 0),
			]
		);
	}

	private function normalizeParentIds(array $parentIds): array
	{
		return array_values(array_unique(array_filter(array_map('intval', $parentIds), static function(int $id): bool {
			return $id > 0;
		})));
	}

	public function addTagEntity($data)
	{
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateTagEntity($id, $data)
	{
		$data = array_intersect_key($data, array('tagid' => true));
		if($data) $this->updateById((int)$id, $data);
	}

	public function deleteTagEntity($id)
	{
		$this->updateById((int)$id, array('deleted' => 1));
	}
}
