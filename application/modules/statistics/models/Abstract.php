<?php

abstract class Statistics_Model_Abstract
{
	protected array $chartImages = [];

	public function getChartImages(): array
	{
		return $this->chartImages;
	}

	protected function getPeriod(int $lenght, array $params): array
	{
		$start = new DateTimeImmutable('first day of this month');
		$from = $start->modify('-'.($lenght-1).' months')->format('Y-m-d');
		$to = $start->format('Y-m-t');

		if(($params['daterange'] ?? 'all') !== 'all') {
			$from = $this->normalizeDate($params['from'] ?? '');
			$to = $this->normalizeDate($params['to'] ?? '');
		}

		if($from > $to) throw new InvalidArgumentException('Statistics start date must not exceed end date');

		$startMonth = new DateTimeImmutable(substr($from, 0, 7).'-01');
		$endMonth = new DateTimeImmutable(substr($to, 0, 7).'-01');
		$interval = $startMonth->diff($endMonth);
		$months = $interval->y * 12 + $interval->m + 1;

		if($months > 60) throw new InvalidArgumentException('Statistics period exceeds 60 months');

		return ['from' => $from, 'to' => $to];
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

	protected function fetchData($db, $type, $year, $month, $ym, $client, array $params, array $options, array $period)
	{
		if(!in_array($type, ['invoice', 'creditnote', 'quote'], true)) throw new InvalidArgumentException('Invalid statistics document type');

		$monthFrom = $year.'-'.$ym.'-01';
		$monthTo = date('Y-m-t', strtotime($monthFrom));

		$from = max($monthFrom, $period['from']);
		$to = min($monthTo, $period['to']);

		$select = $db->select()
			->from(['i' => $type])
			->join(['c' => 'contact'], 'i.contactid = c.contactid', ['id AS cid', 'catid', 'name1'])
			->where('i.state = ?', 105)
			->where('i.deleted = ?', 0)
			->where('i.cancelled = ?', 0)
			->where("i.{$type}date >= ?", $from)
			->where("i.{$type}date <= ?", $to)
			->where('i.clientid = ?', (int)$client['id'])
			->where('c.clientid = ?', (int)$client['id'])
			->setIntegrityCheck(false);

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

		return $db->fetchAll($select);
	}
}
