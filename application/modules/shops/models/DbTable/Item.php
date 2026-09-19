<?php

class Shops_Model_DbTable_Item extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'item';

	public function getItem(int $id): ?array
	{
		return $this->getPublicById($id);
	}

	public function getItemBySku(string $sku): ?array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('sku = ?', $sku)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	public function getItemsByCategory(int $categoryId, array $params = []): Zend_Db_Table_Rowset_Abstract
	{
		$order = $params['order'] ?? 'modified';
		$sort = strtoupper((string)($params['sort'] ?? 'DESC'));

		if (!in_array($order, ['modified', 'created', 'title'], true)) {
			$order = 'modified';
		}

		if (!in_array($sort, ['ASC', 'DESC'], true)) {
			$sort = 'DESC';
		}

		$select = $this->getPublicSelect()
			->where('shopcatid = ?', $categoryId)
			->order($order . ' ' . $sort);

		$limit = (int)($params['limit'] ?? 25);
		$offset = max(0, (int)($params['offset'] ?? 0));

		if ($limit > 0) {
			$select->limit($limit, $offset);
		}

		return $this->fetchAll($select);
	}
}
