<?php

abstract class Statistics_Model_Abstract
{
	protected array $chartImages = [];

	public function getChartImages(): array
	{
		return $this->chartImages;
	}

	protected function fetchData($db, $type, $year, $month, $ym, $client, array $params, array $options)
	{
		if(!in_array($type, ['invoice', 'creditnote', 'quote'], true)) throw new InvalidArgumentException('Invalid statistics document type');

		$from = $year.'-'.$ym.'-01';
		$to = date('Y-m-t', strtotime($from));

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

		if(is_string($country) && $country !== '' && isset($countries[$country])) {
			$adapter = $db->getAdapter();
			$billing = $adapter->quoteInto('i.billingcountry = ?', $country);
			$shipping = $adapter->quoteInto('i.shippingcountry = ?', $country);
			$select->where("($billing OR $shipping)");
		}

		return $db->fetchAll($select);
	}
}
