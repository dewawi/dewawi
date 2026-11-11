<?php

class Items_Model_DbTable_Itematr extends DEEC_Model_DbTable_Position
{
	protected $_name = 'itematr';

	protected string $setField = 'atrsetid';

	public function importCsv($stream, string $separator = ',', string $enclosure = '"'): array
	{
		if(!is_resource($stream) || !stream_get_meta_data($stream)['seekable'] || !in_array($separator, [',', ';'], true) || !in_array($enclosure, ['"', "'"], true)) {
			throw new InvalidArgumentException('Invalid CSV input or delimiters');
		}

		$header = $this->readImportRecord($stream, $separator, $enclosure, 1);
		if(!$header) throw new InvalidArgumentException('CSV header is missing');
		$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
		$map = [];
		foreach($header as $index => $column) {
			$column = trim($column);
			if($column === '') continue;
			if(isset($map[$column])) throw new InvalidArgumentException('Duplicate CSV column: '.$column);
			$map[$column] = $index;
		}
		foreach(['sku', 'title'] as $column) {
			if(!isset($map[$column])) throw new InvalidArgumentException('Missing CSV column: '.$column);
		}

		// IDs, tenant ownership, hierarchy and audit fields are never imported.
		$fields = ['sku', 'title', 'value', 'image', 'description', 'price', 'taxrate', 'priceruleamount', 'priceruleaction', 'quantity', 'total', 'currency', 'uom', 'manufacturerid', 'manufacturersku', 'set'];
		$rows = [];
		$line = 1;
		while(($record = $this->readImportRecord($stream, $separator, $enclosure, ++$line)) !== false) {
			if($record === [null]) continue;
			if(count($record) !== count($header)) throw new InvalidArgumentException('CSV row '.$line.': incorrect column count');
			$row = [];
			foreach($fields as $field) {
				if(isset($map[$field])) $row[$field] = $record[$map[$field]];
			}
			if(($row['sku'] ?? '') === 'FORM_CALC') {
				foreach(['value', 'description'] as $column) {
					if(!isset($map[$column])) throw new InvalidArgumentException('Missing FORM_CALC column: '.$column);
				}
			}
			$row['_line'] = $line;
			if(isset($map['dewawidiscount'])) $row['dewawidiscount'] = $record[$map['dewawidiscount']];
			$rows[] = $row;
		}
		if(!$rows) throw new InvalidArgumentException('CSV contains no attributes');

		$adapter = $this->getAdapter();
		$clientId = $this->getClientId();
		if($adapter->getConnection()->inTransaction()) throw new RuntimeException('Attribute import requires its own transaction');
		$engines = $adapter->fetchPairs('SELECT t.TABLE_NAME, e.TRANSACTIONS FROM information_schema.TABLES t JOIN information_schema.ENGINES e ON e.ENGINE = t.ENGINE WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME IN (?, ?)', ['itematr', 'itematrset']);
		if(count($engines) !== 2 || in_array(false, array_map(static fn($value) => $value === 'YES', $engines), true)) {
			throw new RuntimeException('Attribute import requires transactional itematr and itematrset tables');
		}

		// The lock also covers an empty first import, for which no attribute row can be locked.
		$lock = 'itematr-import:'.sha1($adapter->fetchOne('SELECT DATABASE()').':'.$clientId);
		if((int)$adapter->fetchOne('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) throw new RuntimeException('Another attribute import is running; please retry');
		try {
			$adapter->beginTransaction();
			try {
				$itemDb = new Items_Model_DbTable_Item(['db' => $adapter]);
				$itemDb->setClientId($clientId);
				$setDb = new Items_Model_DbTable_Itematrset(['db' => $adapter]);
				$setDb->setClientId($clientId);
				$columns = $adapter->fetchAssoc("SELECT COLUMN_NAME, CHARACTER_SET_NAME, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itematr'");
				$columns['set'] = $adapter->fetchRow("SELECT CHARACTER_SET_NAME, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itematrset' AND COLUMN_NAME = 'title'");
				$attributes = [];
				$items = [];
				$current = [];
				$seen = [];
				foreach($rows as $row) {
					$line = $row['_line'];
					unset($row['_line']);
					$sku = $row['sku'];
					$itemId = 0;
					if($sku !== 'FORM_CALC') {
						if(!array_key_exists($sku, $items)) $items[$sku] = $itemDb->getItemBySKU($sku);
						if(!$items[$sku]) throw new InvalidArgumentException('CSV row '.$line.': unknown item SKU');
						$itemId = (int)$items[$sku]['id'];
						if($itemId < 1) throw new InvalidArgumentException('CSV row '.$line.': invalid item parent ID');
						if(!isset($current[$itemId])) $current[$itemId] = $this->getPositions($itemId)->toArray();
						foreach($current[$itemId] as $attribute) {
							if($attribute['title'] && $attribute['description']) {
								foreach($row as $key => $value) $row[$key] = str_replace('#'.$attribute['title'].'#', $attribute['description'], $value);
							}
						}
					}
					$row = $this->validateImportAttribute($row, $line, $columns);
					$key = $sku === 'FORM_CALC'
						? [$sku, $row['title'], trim($row['description'])]
						: [$sku, $row['set'] ?? '', $row['title'], $row['value'] ?? '', $row['description'] ?? ''];
					$key = json_encode($key);
					if(isset($seen[$key])) throw new InvalidArgumentException('CSV row '.$line.': duplicate attribute');
					$seen[$key] = true;
					$row['parentid'] = $itemId;
					$row['itemid'] = $itemId;
					$attributes[$itemId][] = $row;
				}

				// All rows and item references have been checked before the first write.
				foreach($attributes as $itemId => $itemRows) {
					$where = [$adapter->quoteInto('clientid = ?', $clientId), $adapter->quoteInto('parentid = ?', $itemId)];
					if($itemId === 0) {
						$where[] = $adapter->quoteInto('sku = ?', 'FORM_CALC');
						$this->delete($where);
					} else {
						$this->update(['deleted' => 1], $where);
						$setDb->deletePositionSetsByItemID($itemId);
					}
					$sets = [];
					$orderings = [];
					foreach($itemRows as $row) {
						$setId = 0;
						if($itemId > 0 && isset($row['set']) && $row['set'] !== '') {
							$title = $row['set'];
							if(!isset($sets[$title])) $sets[$title] = $setDb->addPositionSet(['parentid' => $itemId, 'title' => $title, 'ordering' => count($sets) + 1]);
							$setId = (int)$sets[$title];
						}
						unset($row['set']);
						$row['atrsetid'] = $setId;
						$row['masterid'] = null;
						$row['ordering'] = $orderings[$setId] = ($orderings[$setId] ?? 0) + 1;
						// Only this validated import path creates historical parentless FORM_CALC rows.
						if($itemId > 0) $row = $this->preparePositionData($row);
						$this->create($row);
					}
				}
				$adapter->commit();
			} catch(Throwable $e) {
				$adapter->rollBack();
				throw $e;
			}
		} finally {
			$adapter->fetchOne('SELECT RELEASE_LOCK(?)', [$lock]);
		}
		return ['created' => count($rows)];
	}

	protected function readImportRecord($stream, string $separator, string $enclosure, int $line)
	{
		if($line === 1) {
			$start = ftell($stream);
			if(fread($stream, 3) !== "\xEF\xBB\xBF") fseek($stream, $start);
		}
		$start = ftell($stream);
		$record = fgetcsv($stream, 0, $separator, $enclosure);
		if($record === false) {
			if(!feof($stream)) throw new InvalidArgumentException('CSV read failed at row '.$line);
			return false;
		}
		$end = ftell($stream);
		fseek($stream, $start);
		$raw = fread($stream, $end - $start);
		$quote = preg_quote($enclosure, '~');
		$sep = preg_quote($separator, '~');
		$field = '(?:'.$quote.'(?:[^'.$quote.'\\\\]++|\\\\.|'.$quote.$quote.')*'.$quote.'|[^'.$quote.$sep.'\r\n]*)';
		if(!preg_match('~\A'.$field.'(?:'.$sep.$field.')*(?:\r\n|\n|\r)?\z~sD', $raw)) {
			throw new InvalidArgumentException('Malformed CSV at row '.$line);
		}
		return $record;
	}

	protected function validateImportAttribute(array $row, int $line, array $columns): array
	{
		foreach($row as $field => $value) {
			$column = $columns[$field] ?? [];
			if(!mb_check_encoding($value, 'UTF-8') || strpos($value, "\0") !== false
				|| mb_strlen($value, 'UTF-8') > ($column['CHARACTER_MAXIMUM_LENGTH'] ?? 255)
				|| strlen($value) > ($column['CHARACTER_OCTET_LENGTH'] ?? 1020)
				|| (in_array($column['CHARACTER_SET_NAME'] ?? '', ['utf8', 'utf8mb3'], true) && preg_match('/[\x{10000}-\x{10FFFF}]/u', $value))) {
				throw new InvalidArgumentException('CSV row '.$line.': invalid or oversized '.$field);
			}
		}
		foreach(['sku', 'title'] as $field) {
			if(trim($row[$field]) === '') throw new InvalidArgumentException('CSV row '.$line.': missing '.$field);
		}
		if($row['sku'] === 'FORM_CALC') {
			$value = trim($row['value']);
			if(!preg_match('/^[+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+)$/D', $value) || !is_finite((float)str_replace(',', '.', $value))) {
				throw new InvalidArgumentException('CSV row '.$line.': invalid FORM_CALC value');
			}
			if($row['title'] === 'referenzleistung' && trim($row['description']) === '') {
				throw new InvalidArgumentException('CSV row '.$line.': missing reference item description');
			}
		}
		foreach(['price', 'taxrate', 'priceruleamount', 'quantity', 'total', 'dewawidiscount'] as $field) {
			if(!isset($row[$field])) continue;
			$value = str_replace(',', '.', trim($row[$field]));
			if($value === '') {
				$row[$field] = null;
				continue;
			}
			if(!preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/D', $value) || abs((float)$value) >= 100000000
				|| (strpos($value, '.') !== false && strlen(rtrim(substr(strrchr($value, '.'), 1), '0')) > 4)) {
				throw new InvalidArgumentException('CSV row '.$line.': invalid decimal '.$field);
			}
			$row[$field] = $value;
		}
		if(isset($row['manufacturerid'])) {
			if($row['manufacturerid'] === '') $row['manufacturerid'] = null;
			elseif(!ctype_digit($row['manufacturerid']) || (float)$row['manufacturerid'] > 2147483647) throw new InvalidArgumentException('CSV row '.$line.': invalid manufacturerid');
		}
		if(isset($row['dewawidiscount']) && $row['dewawidiscount'] !== '0') {
			if(!isset($row['price']) || $row['dewawidiscount'] < 0 || $row['dewawidiscount'] > 100) throw new InvalidArgumentException('CSV row '.$line.': invalid price discount');
			$row['price'] = number_format($row['price'] * (100 - $row['dewawidiscount']) / 100, 4, '.', '');
		}
		unset($row['dewawidiscount']);
		return $row;
	}

	public function getPositionsBySku(
		string $sku,
		?int $setId = null
	) {
		$select = $this->select()
			->where('sku = ?', $sku)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('ordering ASC');

		if($setId !== null) {
			$select->where(
				'atrsetid = ?',
				$setId
			);
		}

		return $this->fetchAll($select);
	}
}
