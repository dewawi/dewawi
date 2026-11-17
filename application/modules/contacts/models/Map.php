<?php

class Contacts_Model_Map extends DEEC_List_Query
{
	public const FILTERS = ['keyword' => '', 'catid' => 'all', 'tagid' => '0', 'country' => '0', 'role' => 'all'];
	private const VALID = 'latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180';

	public function authorize(): void
	{
		if (!Zend_Auth::getInstance()->hasIdentity() || !Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) throw new RuntimeException('Contact access denied', 403);
		$user = Zend_Registry::get('User');
		if (empty($user['id']) || empty($user['clientid']) || !(new DEEC_Permission($user))->hasPermission('contacts', 'contact', 'view') || $this->getClientId(['clientModule' => 'contacts']) <= 0) throw new RuntimeException('Contact access denied', 403);
	}

	public function filters(array $input, array $options): array
	{
		$filters = self::FILTERS;
		foreach ($filters as $name => $default) {
			$value = $input[$name] ?? $default;
			if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException('Invalid map filter');
			$value = trim((string)$value);
			if ($name === 'keyword') {
				if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 200) throw new InvalidArgumentException('Invalid map search');
			} elseif (!array_key_exists($value, $options[$name] ?? [])) {
				throw new InvalidArgumentException('Invalid map filter');
			}
			$filters[$name] = $value;
		}
		return $filters;
	}

	public function viewport(array $input): array
	{
		$bounds = [];
		foreach (['south' => 90, 'west' => 180, 'north' => 90, 'east' => 180] as $name => $limit) {
			$value = $input[$name] ?? null;
			if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > $limit) throw new InvalidArgumentException('Invalid map bounds');
			$bounds[$name] = (float)$value;
		}
		if ($bounds['south'] > $bounds['north']) throw new InvalidArgumentException('Invalid map bounds');
		return $bounds;
	}

	protected function locations(array $params, array $options): Zend_Db_Select
	{
		$this->authorize();
		$params = $this->filters($params, $options);
		$config = Contacts_Model_Entity_Contact::listConfig();
		$config['columns'] = ['id', 'contactid', 'name1'];
		$config['group'] = ['c.id', 'c.contactid', 'c.name1', 'a.latitude', 'a.longitude'];
		foreach ($config['joins'] as &$join) $join['columns'] = $join['alias'] === 'a' ? ['latitude', 'longitude'] : [];
		unset($join);
		if ($params['keyword'] === '') $config['joins'] = [$config['joins'][0]];
		$table = new Contacts_Model_DbTable_Contact();
		$select = $this->buildBaseSelect($table, $config, false);
		$this->applyFilters($select, $params, $options, $config);
		$this->applyGrouping($select, $config);
		return $table->getAdapter()->select()->from(['locations' => $select]);
	}

	public function summary(array $params, array $options): array
	{
		$select = $this->locations($params, $options);
		$select->reset(Zend_Db_Select::COLUMNS)->columns([
			'total' => new Zend_Db_Expr('COUNT(*)'),
			'positioned' => new Zend_Db_Expr('COALESCE(SUM('.self::VALID.'), 0)'),
			'missing' => new Zend_Db_Expr('COALESCE(SUM(latitude IS NULL OR longitude IS NULL), 0)'),
			'south' => new Zend_Db_Expr('MIN(CASE WHEN '.self::VALID.' THEN latitude END)'),
			'north' => new Zend_Db_Expr('MAX(CASE WHEN '.self::VALID.' THEN latitude END)'),
			'west' => new Zend_Db_Expr('MIN(CASE WHEN '.self::VALID.' THEN longitude END)'),
			'east' => new Zend_Db_Expr('MAX(CASE WHEN '.self::VALID.' THEN longitude END)'),
		]);
		$row = $select->getAdapter()->fetchRow($select);
		$result = ['total' => (int)$row['total'], 'positioned' => (int)$row['positioned'], 'missing' => (int)$row['missing']];
		$result['invalid'] = $result['total'] - $result['positioned'] - $result['missing'];
		$result['bounds'] = $result['positioned'] ? array_map('floatval', array_intersect_key($row, array_flip(['south', 'north', 'west', 'east']))) : null;
		return $result;
	}

	private function inViewport(array $params, array $options, array $input): Zend_Db_Select
	{
		$bounds = $this->viewport($input);
		$select = $this->locations($params, $options)->where(self::VALID)
			->where('latitude >= ?', $bounds['south'])->where('latitude <= ?', $bounds['north']);
		$db = $select->getAdapter();
		$longitude = $db->quoteInto('longitude >= ?', $bounds['west']);
		$longitude .= ($bounds['west'] > $bounds['east'] ? ' OR ' : ' AND ').$db->quoteInto('longitude <= ?', $bounds['east']);
		return $select->where('('.$longitude.')');
	}

	public function markers(array $params, array $options, array $input): array
	{
		$bounds = $this->viewport($input);
		$zoom = filter_var($input['zoom'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 19]]);
		if ($zoom === false) throw new InvalidArgumentException('Invalid map zoom');
		$width = $bounds['east'] - $bounds['west'];
		if ($width < 0) $width += 360;
		// Bound the number of returned cells even for a large viewport at a high zoom.
		$step = max(360 / pow(2, $zoom + 4), $width / 40, ($bounds['north'] - $bounds['south']) / 30, 0.000000001);
		$select = $this->inViewport($params, $options, $bounds);
		$cell = $select->getAdapter()->quote($step);
		$select->reset(Zend_Db_Select::COLUMNS)->columns([
			'count' => new Zend_Db_Expr('COUNT(*)'),
			'latitude' => new Zend_Db_Expr('AVG(latitude)'),
			'longitude' => new Zend_Db_Expr('AVG(longitude)'),
			'south' => new Zend_Db_Expr('MIN(latitude)'), 'north' => new Zend_Db_Expr('MAX(latitude)'),
			'west' => new Zend_Db_Expr('MIN(longitude)'), 'east' => new Zend_Db_Expr('MAX(longitude)'),
			'id' => new Zend_Db_Expr('CASE WHEN COUNT(*) = 1 THEN MIN(id) END'),
			'name1' => new Zend_Db_Expr('CASE WHEN COUNT(*) = 1 THEN MIN(name1) END'),
			'contactid' => new Zend_Db_Expr('CASE WHEN COUNT(*) = 1 THEN MIN(contactid) END'),
		])->group(new Zend_Db_Expr('FLOOR((latitude + 90) / '.$cell.')'))
			->group(new Zend_Db_Expr('FLOOR((longitude + 180) / '.$cell.')'));
		$groups = $select->getAdapter()->fetchAll($select);
		$visible = 0;
		foreach ($groups as &$group) {
			$group['count'] = (int)$group['count'];
			$visible += $group['count'];
			foreach (['latitude', 'longitude', 'south', 'north', 'west', 'east'] as $field) $group[$field] = (float)$group[$field];
		}
		unset($group);
		return ['groups' => $groups, 'visible' => $visible];
	}

	public function contacts(array $params, array $options, array $input): array
	{
		$after = filter_var($input['after'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		if ($after === false) throw new InvalidArgumentException('Invalid map cursor');
		$select = $this->inViewport($params, $options, $input)->where('id > ?', $after)->order('id ASC')->limit(51);
		$rows = $select->getAdapter()->fetchAll($select);
		$more = count($rows) > 50;
		$rows = array_slice($rows, 0, 50);
		return ['contacts' => $rows, 'after' => $more ? (int)end($rows)['id'] : null];
	}
}
