<?php

class Application_Model_DbTable_Invoicecorrection extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'invoicecorrection';
	protected ?string $orderingField = null;
	protected ?string $deletedField = null;

	public function getForInvoice(int $id, int $clientId): array
	{
		return $this->fetchAll($this->select()->where('clientid = ?', $clientId)->where('invoiceid = ?', $id)->order('sequence ASC'))->toArray();
	}
}
