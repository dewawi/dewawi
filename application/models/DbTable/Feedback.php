<?php

class Application_Model_DbTable_Feedback extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'feedback';
	protected ?string $orderingField = null;

	public function getByToken(string $token): ?array
	{
		$token = strtolower(trim($token));
		if(strlen($token) !== 64 || !ctype_xdigit($token)) return null;

		$row = $this->fetchRow(
			$this->select()
				->where('token = ?', $token)
				->where('deleted = ?', 0)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	public function getForEntity(int $parentId, string $module, string $controller, ?string $type = null): array
	{
		$select = $this->select()
			->where('parentid = ?', $parentId)
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('id DESC');

		if($type !== null && $type !== '') $select->where('type = ?', $type);

		return $this->fetchAll($select)->toArray();
	}
}
