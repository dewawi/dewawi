<?php

class Shops_Model_DbTable_Category extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'category';
	protected ?string $publicField = 'activated';

	public function getCategory(int $id): ?array
	{
		$row = $this->fetchRow(
			$this->getCategorySelect()
				->where('id = ?', $id)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	public function getCategories(?int $parentid = null): array
	{
		$select = $this->getCategorySelect();

		if ($parentid !== null) {
			$select->where('parentid = ?', $parentid);
		}

		$rows = $this->fetchAll(
			$select->order('ordering ASC')
		)->toArray();

		$categories = [];

		foreach ($rows as $category) {
			$categories[$category['id']] = [
				'id' => $category['id'],
				'type' => $category['type'],
				'title' => $category['title'],
				'subtitle' => $category['subtitle'],
				'image' => $category['image'],
				'description' => $category['description'],
				'minidescription' => $category['minidescription'],
				'shortdescription' => $category['shortdescription'],
				'footer' => $category['footer'],
				'parentid' => $category['parentid'],
				'ordering' => $category['ordering'],
				'activated' => $category['activated'],
				'shopid' => $category['shopid'],
			];
		}

		foreach ($rows as $category) {
			if ($category['parentid'] && isset($categories[$category['parentid']])) {
				$categories[$category['parentid']]['childs'][] = $category['id'];
			}
		}

		return $categories;
	}

	protected function getCategorySelect(): Zend_Db_Table_Select
	{
		return $this->getPublicSelect()
			->where('type = ?', 'shop');
	}
}
