<?php

class Admin_Model_DbTable_Category extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'category';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getCategory($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getCategories($type, $parentid = null, $shopid = 0)
	{
		// Prepare the where conditions
		$where = [];
		if ($parentid !== null) {
			$where[] = $this->getAdapter()->quoteInto('parentid = ?', $parentid);
		}
		if ($shopid !== null) {
			$where[] = $this->getAdapter()->quoteInto('shopid = ?', (int)$shopid);
		}
		$where[] = $this->getAdapter()->quoteInto('type = ?', $type);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		// Fetch the data
		$data = $this->fetchAll($where, 'ordering');

		// Initialize categories array
		$categories = [];

		// Iterate through the data
		foreach ($data as $category) {
			// Prepare the category array
			$categories[$category->id] = [
				'id' => $category->id,
				'type' => $category->type,
				'title' => $category->title,
				'subtitle' => $category->subtitle,
				'image' => $category->image,
				'description' => $category->description,
				'footer' => $category->footer,
				'parentid' => $category->parentid,
				'ordering' => $category->ordering,
				'activated' => $category->activated,
				'shopid' => isset($category->shopid) ? $category->shopid : null,
				//'shopcatid' => isset($category->shopcatid) ? $category->shopcatid : null
			];
		}
		// If the category has a parent, add it to the parent's 'childs' array
		foreach ($data as $category) {
			if ($category->parentid && isset($categories[$category->parentid])) {
				$categories[$category->parentid]['childs'][] = $category->id;
			}
		}

		return $categories;
	}

	public function addCategory($data, $clientid = 0)
	{
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		if($clientid) {
			$data['clientid'] = $clientid;
		} else {
			$data['clientid'] = $this->_client['id'];
		}
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateCategory($id, $data)
	{
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->update($data, 'id = '. (int)$id);
	}

	public function sortCategory($id, $ordering)
	{
		$data = array();
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$data['ordering'] = $ordering;
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteCategory($id)
	{
		$data = array();
		$data['deleted'] = 1;
		$this->update($data, 'id =' . (int)$id);
	}

	public function getSelectOptions(
		$source = 0
	): array {
		if (is_string($source)) {
			return $this->getTypeSelectOptions($source);
		}

		return $this->getShopSelectOptions((int)$source);
	}

	protected function getShopSelectOptions(int $shopId = 0): array
	{
		if ($shopId > 0) return $this->getParentSelectOptions('shop', $shopId);

		$select = $this->select()
			->where('clientid = ?', $this->getClientId())
			->where('type = ?', 'shop')
			->where('deleted = ?', 0)
			->order('title ASC');

		$options = [];

		foreach ($this->fetchAll($select)->toArray() as $row) {
			$options[(string)$row['id']] = $row['shopid'].':'.(string)$row['title'];
		}

		return $options;
	}

	protected function getTypeSelectOptions(string $type): array
	{
		$categories = $this->getCategories(
			$type,
			null,
			null
		);

		$options = [];

		foreach ($categories as $id => $category) {
			$options[(string)$id] = $category['title'];
		}

		return $options;
	}

	public function getParentSelectOptions(string $type, int $shopId = 0, int $excludeId = 0): array
	{
		$categories = $this->getCategories($type, null, $shopId);
		$excluded = [];

		if ($excludeId > 0) {
			$excluded[$excludeId] = true;

			foreach ($this->getDescendantIds($categories, $excludeId) as $id) {
				$excluded[$id] = true;
			}
		}

		return $this->buildCategorySelectOptions($categories, 0, 0, $excluded);
	}

	protected function buildCategorySelectOptions(array $categories, int $parentId = 0, int $depth = 0, array $excluded = []): array
	{
		$options = [];

		foreach ($categories as $id => $category) {
			if ((int)$category['parentid'] !== $parentId || isset($excluded[$id])) continue;

			$options[(string)$id] = str_repeat('— ', $depth).(string)$category['title'];
			$options += $this->buildCategorySelectOptions($categories, (int)$id, $depth + 1, $excluded);
		}

		return $options;
	}

	protected function getDescendantIds(array $categories, int $parentId): array
	{
		$ids = [];

		foreach ($categories as $id => $category) {
			if ((int)$category['parentid'] !== $parentId) continue;

			$ids[] = (int)$id;
			$ids = array_merge($ids, $this->getDescendantIds($categories, (int)$id));
		}

		return $ids;
	}
}
