<?php

class Shops_Model_DbTable_Template extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'template';
	protected ?string $siteField = null;

	public function getPrimaryTemplate(): array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->order('ordering ASC')
				->limit(1)
		);

		if (!$row) {
			throw new RuntimeException('Could not find template');
		}

		return $row->toArray();
	}
}
