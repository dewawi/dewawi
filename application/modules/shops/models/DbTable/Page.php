<?php

class Shops_Model_DbTable_Page extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'page';
	protected ?string $publicField = 'activated';

	public function getPage(int $id): ?array
	{
		return $this->getPublicById($id);
	}

	public function getPageByType(string $type): ?array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('type = ?', $type)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	public function getPages(): array
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->order('ordering ASC')
		)->toArray();
	}
}
