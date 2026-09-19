<?php

class Shops_Model_DbTable_Tag extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'tag';

	public function getTag(int $id): ?array
	{
		return $this->getPublicById($id);
	}

	public function getTags(string $module, string $controller): array
	{
		$rows = $this->fetchAll(
			$this->getPublicSelect()
				->where('module = ?', $module)
				->where('controller = ?', $controller)
				->order('ordering ASC')
		);

		$tags = [];

		foreach ($rows as $tag) {
			$tags[$tag->id] = $tag;
		}

		return $tags;
	}
}
