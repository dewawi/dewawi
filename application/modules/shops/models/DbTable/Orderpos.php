<?php

class Shops_Model_DbTable_Orderpos extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'shoporderpos';
	protected ?string $orderingField = null;

	public function addOrderpos(array $data): int
	{
		return $this->create($data);
	}
}
