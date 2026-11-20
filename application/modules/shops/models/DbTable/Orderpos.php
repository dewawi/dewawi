<?php

class Shops_Model_DbTable_Orderpos extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'shoporderpos';
	protected ?string $orderingField = null;

	public function addOrderpos(array $data): int
	{
		return $this->create($data);
	}

	public function getForOrder(array $order): array
	{
		if ((int)$order['clientid'] !== $this->getClientId()) throw new RuntimeException('SHOPS_ORDER_NOT_FOUND');
		$select = $this->select()->where('clientid = ?', $this->getClientId())->where('shopid = ?', $order['shopid'])->where('deleted = ?', 0);
		if (!empty($order['snapshot'])) $select->where('shoporderid = ?', (int)$order['id']);
		else {
			$count = $this->getAdapter()->fetchOne($this->getAdapter()->select()->from('shoporder', ['COUNT(*)'])
				->where('clientid = ?', $this->getClientId())->where('shopid = ?', $order['shopid'])->where('orderid = ?', $order['orderid']));
			if ((int)$count !== 1) return [];
			$select->where('orderid = ?', $order['orderid'])->where('shoporderid IS NULL');
		}
		$rows = $this->fetchAll($select->order('id ASC'))->toArray();
		foreach ($rows as &$row) $row['snapshot_data'] = Shops_Service_OrderSnapshot::decode($row['snapshot'] ?? null);
		return $rows;
	}

	protected function prepareUpdateData(array $data): array
	{
		throw new DomainException('SHOPS_ORDER_SNAPSHOT_IMMUTABLE');
	}
}
