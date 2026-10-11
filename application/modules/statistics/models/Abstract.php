<?php

abstract class Statistics_Model_Abstract
{
	protected array $chartImages = [];

	public function getChartImages(): array
	{
		return $this->chartImages;
	}

	protected function getInvoiceNetSubtotal($invoice): float
	{
		$subtotal = (float)$invoice->subtotal;
		$prepayment = (float)$invoice->prepayment;

		if($prepayment <= 0) return $subtotal;

		$total = (float)$invoice->total;
		if($total <= 0) throw new UnexpectedValueException('Invalid invoice total for prepayment');

		return $subtotal - ($prepayment * $subtotal / $total);
	}

	protected function prepareRanking(array $rows, int $nameLength = 20): array
	{
		usort($rows, static fn($a, $b) => $b['subtotal'] <=> $a['subtotal']);

		$total = array_sum(array_column($rows, 'subtotal'));
		$currency = Zend_Registry::get('Zend_Currency');
		$names = [];
		$values = [];

		foreach($rows as $i => &$row) {
			if($i < 10) {
				$names[] = mb_substr($row['name1'], 0, $nameLength, 'UTF-8');
				$values[] = round($row['subtotal']);
			}

			$row['share'] = $total > 0 ? round($row['subtotal'] / $total * 100, 2) : 0;
			$row['subtotal'] = $currency->toCurrency($row['subtotal']);
			$row['total'] = $currency->toCurrency($total);
		}
		unset($row);

		return [$rows, $names, $values];
	}

	protected function getPeriod(array $params): array
	{
		if(($params['daterange'] ?? 'all') === 'all') return ['from' => null, 'to' => null];

		$from = $this->normalizeDate($params['from'] ?? '');
		$to = $this->normalizeDate($params['to'] ?? '');

		if($from > $to) throw new InvalidArgumentException('Statistics start date must not exceed end date');

		return ['from' => $from, 'to' => $to];
	}

	protected function resolvePeriod(array $period, array ...$groups): array
	{
		$keys = [];
		foreach($groups as $group) $keys = array_merge($keys, array_keys($group));

		$first = $keys ? (string)min($keys) : date('Ym');
		$last = $keys ? (string)max($keys) : date('Ym');

		$period['from'] ??= substr($first, 0, 4).'-'.substr($first, 4, 2).'-01';
		$period['to'] ??= date('Y-m-t', strtotime(substr($last, 0, 4).'-'.substr($last, 4, 2).'-01'));

		return $period;
	}

	protected function normalizeDate($value): string
	{
		if(!is_string($value)) throw new InvalidArgumentException('Invalid statistics date');

		$locale = (string)Zend_Registry::get('Zend_Locale');
		$formats = ['!Y-m-d', '!d.m.Y', strpos($locale, 'en_US') === 0 ? '!m/d/Y' : '!d/m/Y'];

		foreach($formats as $format) {
			$date = DateTimeImmutable::createFromFormat($format, $value);
			if($date && $date->format(substr($format, 1)) === $value) return $date->format('Y-m-d');
		}

		throw new InvalidArgumentException('Invalid statistics date');
	}

	protected function fetchData($db, string $type, array $client, array $params, array $options, array $period): array
	{
		if(!in_array($type, ['invoice', 'creditnote', 'quote'], true)) throw new InvalidArgumentException('Invalid statistics document type');

		$dateField = $type.'date';
		$columns = ['id', 'contactid', $type.'id', $dateField, 'subtotal'];
		if($type === 'invoice') array_push($columns, 'total', 'prepayment');

		$select = $db->select()
			->from(['i' => $type], $columns)
			->join(['c' => 'contact'], 'i.contactid = c.contactid', ['cid' => 'id', 'catid', 'name1'])
			->where('i.state = ?', 105)
			->where('i.deleted = ?', 0)
			->where('i.cancelled = ?', 0)
			->where('i.clientid = ?', (int)$client['id'])
			->where('c.clientid = ?', (int)$client['id'])
			->setIntegrityCheck(false);

		if($period['from'] !== null) $select->where("i.{$dateField} >= ?", $period['from']);
		if($period['to'] !== null) $select->where("i.{$dateField} <= ?", $period['to']);

		$categories = $options['categories'] ?? [];
		$catid = $params['catid'] ?? 'all';

		if($catid === '0' || $catid === 0) {
			$select->where('c.catid = ?', 0);
		} elseif(is_scalar($catid) && ctype_digit((string)$catid) && isset($categories[(int)$catid])) {
			$ids = [(int)$catid];
			$byParent = Application_Model_DbTable_Category::groupByParent($categories);

			for($i = 0; $i < count($ids); $i++) {
				foreach($byParent[$ids[$i]] ?? [] as $id => $category) {
					if(!in_array((int)$id, $ids, true)) $ids[] = (int)$id;
				}
			}

			$select->where('c.catid IN (?)', $ids);
		}

		$country = $params['country'] ?? null;
		$countries = $options['country'] ?? [];

		if(is_string($country) && $country !== '' && $country !== '0' && isset($countries[$country])) {
			$adapter = $db->getAdapter();
			$billing = $adapter->quoteInto('i.billingcountry = ?', $country);
			$shipping = $adapter->quoteInto('i.shippingcountry = ?', $country);
			$select->where("($billing OR $shipping)");
		}

		$months = [];
		foreach($db->fetchAll($select) as $document) {
			$month = str_replace('-', '', substr((string)$document->$dateField, 0, 7));
			$months[$month][] = $document;
		}

		return $months;
	}
}
