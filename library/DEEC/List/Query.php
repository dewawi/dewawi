<?php

class DEEC_List_Query
{
	public function fetch(array $params, array $options, array $config): array
	{
		$params = $this->normalizeParams($params);

		$tableClass = $config['tableClass'];
		$dbTable = new $tableClass();
		$db = $dbTable->getAdapter();

		$select = $this->buildBaseSelect($dbTable, $config, false);
		$countSelect = $this->buildBaseSelect($dbTable, $config, true);

		$this->applyFilters($select, $params, $options, $config);
		$this->applyFilters($countSelect, $params, $options, $config);

		$this->applyGrouping($select, $config);
		$this->applyGrouping($countSelect, $config);

		$records = $this->count($db, $countSelect, $config);

		$this->applyOrdering($select, $params, $config);
		$this->applyLimit($select, $params);

		$items = $dbTable->fetchAll($select);
		$items = $this->applyNormalizers($items, $config, $params);

		return [$items, $records];
	}

	protected function normalizeParams(array $params): array
	{
		$params['_export'] = !empty($params['_export']);

		$params['page'] = isset($params['page']) ? (int)$params['page'] : 1;
		$params['limit'] = isset($params['limit']) ? (int)$params['limit'] : 25;
		$params['tagid'] = isset($params['tagid']) ? (int)$params['tagid'] : 0;

		if ($params['page'] <= 0) {
			$params['page'] = 1;
		}

		if ($params['_export']) {
			$params['limit'] = 0;
			$params['offset'] = 0;
		} else {
			if ($params['limit'] <= 0) {
				$params['limit'] = 25;
			}

			if ($params['limit'] > 100) {
				$params['limit'] = 100;
			}

			$params['offset'] = isset($params['offset'])
				? (int)$params['offset']
				: (($params['page'] - 1) * $params['limit']);
		}

		$params['keyword'] = $params['keyword'] ?? '';
		$params['catid'] = $params['catid'] ?? 'all';
		$params['country'] = $params['country'] ?? '0';
		$params['states'] = $params['states'] ?? [];
		$params['daterange'] = $params['daterange'] ?? 'all';
		$params['from'] = $params['from'] ?? date('Y-m-d', strtotime('-1 month'));
		$params['to'] = $params['to'] ?? date('Y-m-d');
		$params['order'] = $params['order'] ?? 'modified';
		$params['sort'] = strtoupper($params['sort'] ?? 'DESC');

		if (!in_array($params['sort'], ['ASC', 'DESC'], true)) {
			$params['sort'] = 'DESC';
		}

		return $params;
	}

	protected function buildBaseSelect($dbTable, array $config, bool $countOnly)
	{
		$alias = $config['alias'];
		$table = $dbTable->info('name');

		$columns = $countOnly
			? [new Zend_Db_Expr('COUNT(DISTINCT '.$alias.'.id)')]
			: ($config['columns'] ?? ['*']);

		$select = $dbTable->select()
			->setIntegrityCheck(false)
			->from([$alias => $table], $columns);

		$this->applyJoins($select, $config, $countOnly);

		return $select;
	}

	protected function applyJoins($select, array $config, bool $countOnly): void
	{
		foreach (($config['joins'] ?? []) as $join) {
			$type = $join['type'] ?? 'inner';
			$columns = $countOnly ? [] : ($join['columns'] ?? []);

			if ($type === 'left') {
				$select->joinLeft(
					[$join['alias'] => $join['table']],
					$join['on'],
					$columns
				);
				continue;
			}

			$select->join(
				[$join['alias'] => $join['table']],
				$join['on'],
				$columns
			);
		}
	}

	protected function applyFilters($select, array $params, array $options, array $config): void
	{
		$db = $select->getAdapter();
		$alias = $config['alias'];

		$this->applyKeywordFilter($select, $params, $config);
		$this->applyConfiguredFilters($select, $params, $options, $config);
		$this->applyTagFilter($select, $params, $config);

		$clientId = $this->getClientId($config);
		$select->where($alias.'.clientid = ?', $clientId);
		$select->where($alias.'.deleted = ?', 0);
	}

	protected function applyKeywordFilter($select, array $params, array $config): void
	{
		$keyword = trim((string)($params['keyword'] ?? ''));

		if ($keyword === '' || empty($config['search'])) {
			return;
		}

		$words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keyword));
		$words = array_values(array_filter($words));

		if (!$words) {
			return;
		}

		$db = $select->getAdapter();

		foreach ($words as $word) {
			$parts = [];

			foreach ($config['search'] as $field) {
				$column = $this->column($field, $config['alias']);
				$parts[] = $db->quoteIdentifier($column) . ' LIKE ' . $db->quote('%'.$word.'%');
			}

			if ($parts) {
				$select->where('('.implode(' OR ', $parts).')');
			}
		}
	}

	protected function applyConfiguredFilters($select, array $params, array $options, array $config): void
	{
		foreach (($config['filters'] ?? []) as $name => $filter) {
			if (!array_key_exists($name, $params)) {
				continue;
			}

			$value = $params[$name];

			if ($value === '' || $value === null || $value === 'all' || $value === '0') {
				continue;
			}

			$type = $filter['type'] ?? $name;

			switch ($type) {
				case 'category':
					$this->applyCategoryFilter($select, $value, $options[$name] ?? [], $filter, $config);
					break;

				case 'quantity':
					$this->applyQuantityFilter($select, $value, $filter, $config);
					break;

				case 'states':
					$this->applyStatesFilter($select, $value, $filter, $config);
					break;

				case 'country':
					$this->applyCountryFilter($select, $value, $options[$name] ?? [], $filter, $config);
					break;

				case 'daterange':
					$this->applyDateRangeFilter($select, $params, $filter, $config);
					break;

				case 'equals':
					$this->applyEqualsFilter($select, $value, $filter, $config);
					break;
			}
		}
	}

	protected function applyTagFilter($select, array $params, array $config): void
	{
		$tagid = (int)($params['tagid'] ?? 0);

		if ($tagid <= 0) {
			return;
		}

		if (isset($config['tags']) && $config['tags'] === false) {
			return;
		}

		$request = Zend_Controller_Front::getInstance()->getRequest();

		$module = $config['module'] ?? $request->getModuleName();
		$controller = $config['controller'] ?? $request->getControllerName();

		$alias = $config['alias'];
		$entityIdColumn = $config['entityIdColumn'] ?? 'id';
		$clientId = $this->getClientId($config);

		$db = $select->getAdapter();

		$select->join(
			['tag_filter' => 'tagentity'],
			$alias.'.'.$entityIdColumn.' = tag_filter.entityid'
			. ' AND tag_filter.tagid = '.(int)$tagid
			. ' AND tag_filter.module = '.$db->quote($module)
			. ' AND tag_filter.controller = '.$db->quote($controller)
			. ' AND tag_filter.clientid = '.(int)$clientId
			. ' AND tag_filter.deleted = 0',
			[]
		);
	}

	protected function applyCategoryFilter($select, $catid, array $categories, array $filter, array $config): void
	{
		$alias = $filter['alias'] ?? $config['alias'];
		$column = $alias.'.'.($filter['column'] ?? 'catid');

		if ((string)$catid === '0') {
			$select->where($column.' = ?', 0);
			return;
		}

		if (!isset($categories[$catid])) {
			return;
		}

		$ids = array_merge([(int)$catid], $this->getChildCategoryIds($catid, $categories));

		if ($ids) {
			$select->where($column.' IN (?)', $ids);
		}
	}

	protected function applyQuantityFilter($select, $value, array $filter, array $config): void
	{
		if (!$value) {
			return;
		}

		$alias = $filter['alias'] ?? $config['alias'];
		$column = $filter['column'] ?? 'quantity';

		$select->where($alias . '.' . $column . ' > ?', 0);
	}

	protected function applyStatesFilter($select, $states, array $filter, array $config): void
	{
		if (!is_array($states)) {
			return;
		}

		$states = array_values(array_filter(array_map('intval', $states)));

		if (!$states) {
			return;
		}

		$alias = $filter['alias'] ?? $config['alias'];
		$column = $alias.'.'.($filter['column'] ?? 'state');

		$select->where($column.' IN (?)', $states);
	}

	protected function applyCountryFilter($select, $country, array $countries, array $filter, array $config): void
	{
		if (!isset($countries[$country])) {
			return;
		}

		$alias = $filter['alias'] ?? $config['alias'];
		$columns = $filter['columns'] ?? ['country'];

		$parts = [];

		foreach ($columns as $column) {
			$parts[] = $alias.'.'.$column.' = ?';
		}

		if (!$parts) {
			return;
		}

		if (count($parts) === 1) {
			$select->where($parts[0], $country);
			return;
		}

		$quoted = $select->getAdapter()->quote($country);
		$where = [];

		foreach ($columns as $column) {
			$where[] = $alias.'.'.$column.' = '.$quoted;
		}

		$select->where('('.implode(' OR ', $where).')');
	}

	protected function applyDateRangeFilter($select, array $params, array $filter, array $config): void
	{
		if (($params['daterange'] ?? '') === 'all') {
			return;
		}

		$alias = $filter['alias'] ?? $config['alias'];
		$columns = $filter['columns'] ?? ['created', 'modified'];

		$from = date('Y-m-d', strtotime($params['from']));
		$to = date('Y-m-d', strtotime($params['to']));

		$fromValue = $from.' 00:00:00';
		$toValue = $to.' 23:59:59';

		$parts = [];

		foreach ($columns as $column) {
			$parts[] = $alias.'.'.$column.' BETWEEN '.$select->getAdapter()->quote($fromValue).' AND '.$select->getAdapter()->quote($toValue);
		}

		if ($parts) {
			$select->where('('.implode(' OR ', $parts).')');
		}
	}

	protected function applyEqualsFilter($select, $value, array $filter, array $config): void
	{
		$alias = $filter['alias'] ?? $config['alias'];
		$column = $alias.'.'.$filter['column'];

		$select->where($column.' = ?', $value);
	}

	protected function applyGrouping($select, array $config): void
	{
		$group = $config['group'] ?? ($config['alias'].'.id');
		$select->group($group);
	}

	protected function count($db, $select, array $config): int
	{
		$alias = $config['alias'];

		$countSelect = clone $select;
		$countSelect->reset(Zend_Db_Select::COLUMNS);
		$countSelect->reset(Zend_Db_Select::ORDER);
		$countSelect->reset(Zend_Db_Select::LIMIT_COUNT);
		$countSelect->reset(Zend_Db_Select::LIMIT_OFFSET);
		$countSelect->reset(Zend_Db_Select::GROUP);

		$countSelect->columns([new Zend_Db_Expr('COUNT(DISTINCT '.$alias.'.id)')]);

		return (int)$db->fetchOne($countSelect);
	}

	protected function applyOrdering($select, array $params, array $config): void
	{
		$alias = $config['alias'];
		$order = $params['order'] ?? 'modified';
		$sort = $params['sort'] ?? 'DESC';

		$orders = $config['orders'] ?? [];

		if (isset($orders[$order])) {
			$orderColumn = $orders[$order];
		} elseif (in_array($order, $orders, true)) {
			$orderColumn = $order;
		} else {
			$orderColumn = $order;
		}

		$orderColumn = $this->column($orderColumn, $alias);

		$ordering = [];

		if (!empty($config['pinned'])) {
			$ordering[] = $alias . '.pinned DESC';
		}

		$ordering[] = $orderColumn . ' ' . $sort;

		$select->order($ordering);
	}

	protected function applyLimit($select, array $params): void
	{
		$limit = (int)($params['limit'] ?? 25);

		if ($limit <= 0) {
			return;
		}

		$select->limit($limit, (int)($params['offset'] ?? 0));
	}

	protected function applyNormalizers($items, array $config, array $params = [])
	{
		foreach (($config['normalizers'] ?? []) as $field => $normalizer) {
			if ($normalizer === 'csv') {
				foreach ($items as $item) {
					$item->{$field} = $this->splitCsv($item->{$field} ?? '');
				}
				continue;
			}

			if (is_array($normalizer) && ($normalizer['type'] ?? '') === 'truncate') {
				if (!empty($params['_export'])) {
					continue;
				}

				$length = (int)($normalizer['length'] ?? 43);

				foreach ($items as $item) {
					$value = (string)($item->{$field} ?? '');

					if (strlen($value) > $length) {
						$item->{$field} = substr($value, 0, max(0, $length - 3)) . '...';
					}
				}
			}
		}

		return $items;
	}

	protected function splitCsv($value): array
	{
		if (is_array($value)) {
			return $value;
		}

		$value = trim((string)$value);

		if ($value === '') {
			return [];
		}

		return array_values(array_filter(array_map('trim', explode(',', $value))));
	}

	protected function column(string $field, string $defaultAlias): string
	{
		if (strpos($field, '.') !== false || strpos($field, '(') !== false) {
			return $field;
		}

		return $defaultAlias.'.'.$field;
	}

	protected function getClientId(array $config): int
	{
		$client = Zend_Registry::get('Client');
		$clientId = (int)$client['id'];

		if (!empty($config['clientModule']) && !empty($client['parentid']) && isset($client['modules'][$config['clientModule']])) {
			$clientId = (int)$client['modules'][$config['clientModule']];
		}

		return $clientId;
	}

	protected function getChildCategoryIds($category, array $categories): array
	{
		$ids = [];

		if (!isset($categories[$category]['childs'])) {
			return $ids;
		}

		foreach ($categories[$category]['childs'] as $childCategory) {
			$ids[] = (int)$childCategory;
			$ids = array_merge($ids, $this->getChildCategoryIds($childCategory, $categories));
		}

		return $ids;
	}
}
