<?php

class Application_Model_DbTable_Documentrevision extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'documentrevision';
	protected ?string $orderingField = null;
	protected ?string $deletedField = null;

	public function getForDocument(int $id, string $type, int $clientId): ?array
	{
		$row = $this->fetchRow($this->select()->where('clientid = ?', $clientId)->where('controller = ?', $type)->where('documentid = ?', $id));
		return $row ? $row->toArray() : null;
	}
}
