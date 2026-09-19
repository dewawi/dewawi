<?php

class Shops_Model_DbTable_Slide extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'slide';
	protected ?string $publicField = 'activated';

	public function getByPosition(string $position): ?array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('position = ?', $position)
				->order('ordering ASC')
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}
}
