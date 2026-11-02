<?php

class Shops_Model_DbTable_Item extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'item';
	public const PER_PAGE = 25;

	public function hasPublicDownload(string $filename): bool
	{
		return (bool)$this->fetchRow($this->getPublicSelect()->where("SUBSTRING_INDEX(downloads, '|', -1) = ?", $filename)->limit(1));
	}

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

	public function getItems(): Zend_Db_Table_Rowset_Abstract
	{
		return $this->fetchAll(
			$this->getPublicSelect()->order('id ASC')
		);
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
			->order($order . ' ' . $sort)
			->order('id ' . $sort);

		$limit = (int)($params['limit'] ?? self::PER_PAGE);
		$offset = max(0, (int)($params['offset'] ?? 0));

		if ($limit > 0) {
			$select->limit($limit, $offset);
		}

		return $this->fetchAll($select);
	}

	public function countItemsByCategory(int $categoryId): int
	{
		$select = $this->getPublicSelect()
			->from($this->_name, ['count' => new Zend_Db_Expr('COUNT(*)')])
			->where('shopcatid = ?', $categoryId);

		return (int)$this->getAdapter()->fetchOne($select);
	}

	public static function getPageCount(int $total): int
	{
		return max(1, (int)ceil(max(0, $total) / self::PER_PAGE));
	}

	public static function normalizePage($page, int $total): int
	{
		if (!is_scalar($page)) return 1;

		$page = filter_var($page, FILTER_VALIDATE_INT, [
			'options' => ['default' => 1, 'min_range' => 1],
		]);

		return min((int)$page, self::getPageCount($total));
	}
}
