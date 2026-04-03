<?php

class Application_Model_DbTable_Category extends DEEC_Model_DbTable_Entity
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

	public function getCategories(string $type): array
	{
		$where = [];
		$where[] = $this->getAdapter()->quoteInto('type = ?', $type);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$rows = $this->fetchAll($where, 'ordering')->toArray();

		$categories = [];
		foreach ($rows as $row) {
			$categories[(int)$row['id']] = $row;
		}

		$this->addBreadcrumbField($categories, ' > ', 'breadcrumb');

		return $categories;
	}

	public function getSelectOptions(string $type = 'item'): array
	{
		return self::toSelectOptions($this->getCategories($type));
	}

	public static function toSelectOptions(array $categories, int $root = 0, int $level = 0, array $byParent = null): array
	{
		if ($byParent === null) $byParent = self::groupByParent($categories);

		$out = [];

		if (empty($byParent[$root])) return $out;

		foreach ($byParent[$root] as $id => $cat) {
			$out[(string)$id] = str_repeat(' -- ', $level) . ($cat['title'] ?? '');
			$child = self::toSelectOptions($categories, (int)$id, $level + 1, $byParent);
			foreach ($child as $cid => $lbl) {
				$out[(string)$cid] = $lbl;
			}
		}

		return $out;
	}

	public static function groupByParent(array $categories): array
	{
		$byParent = [];
		foreach ($categories as $id => $cat) {
			$pid = (int)($cat['parentid'] ?? 0);
			if (!isset($byParent[$pid])) $byParent[$pid] = [];
			$byParent[$pid][$id] = $cat;
		}
		return $byParent;
	}

	protected function addBreadcrumbField(array &$categories, string $sep = ' > ', string $field = 'breadcrumb'): void
	{
		// cache pro id, damit jeder Pfad nur einmal berechnet wird
		$cache = [];

		foreach ($categories as $id => &$cat) {
			$cat[$field] = $this->buildBreadcrumb((int)$id, $categories, $cache, $sep);
		}
		unset($cat);
	}

	/**
	 * Rekursiv den Pfad aufbauen (mit Cache).
	 */
	protected function buildBreadcrumb(int $id, array $byId, array &$cache, string $sep): string
	{
		if (isset($cache[$id])) return $cache[$id];
		if (!isset($byId[$id])) return $cache[$id] = '';

		$cat = $byId[$id];
		$title = (string)($cat['title'] ?? '');
		$pid = (int)($cat['parentid'] ?? 0);

		if ($pid <= 0 || !isset($byId[$pid])) {
			return $cache[$id] = $title;
		}

		$parentPath = $this->buildBreadcrumb($pid, $byId, $cache, $sep);
		return $cache[$id] = ($parentPath !== '' ? ($parentPath . $sep) : '') . $title;
	}
}
