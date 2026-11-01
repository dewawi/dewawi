<?php

class Shops_Model_DbTable_Order extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'shoporder';

	protected $_date = null;

	protected $_user = null;

	protected $_shop = null;

	public function init()
	{
		parent::init();
		$this->_shop = Zend_Registry::isRegistered('Shop') ? Zend_Registry::get('Shop') : null;
		if (!$this->hasClientContext() && $this->_shop) $this->setClientId((int)$this->_shop['clientid']);
	}

	public function addOrder($data)
	{
		$data['created'] = $this->_date;
		//$data['createdby'] = $this->_user['id'];
		return $this->create($data);
	}

	public function getByCheckoutToken(int $shopId, string $token): ?array
	{
		$row = $this->fetchRow(
			$this->select()
				->from($this->_name)
				->where('clientid = ?', $this->getClientId())
				->where('shopid = ?', $shopId)
				->where('token = ?', $token)
				->where('deleted = ?', 0)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	public function saveCheckoutOnce(int $shopId, string $token, array $data, array $positions): array
	{
		$token = strtolower(trim($token));
		if ($shopId <= 0 || !preg_match('/^[a-f0-9]{32,64}$/D', $token) || !$positions) {
			throw new InvalidArgumentException('Invalid checkout submission');
		}
		if (Zend_Registry::isRegistered('SiteContext')) {
			$context = Zend_Registry::get('SiteContext');
			if ((int)$context->getClientId() !== $this->getClientId() || (int)$context->getSiteId() !== $shopId) {
				throw new RuntimeException('Checkout site context mismatch');
			}
		}

		$existing = $this->getByCheckoutToken($shopId, $token);
		if ($existing) return ['inserted' => false, 'order' => $existing];

		$adapter = $this->getAdapter();
		$adapter->beginTransaction();

		try {
			$incrementDb = (new Shops_Model_DbTable_Increment())->setClientId($this->getClientId());
			$orderNumber = $incrementDb->reserveIncrement('shoporderid');

			$existing = $this->getByCheckoutToken($shopId, $token);
			if ($existing) {
				$adapter->rollBack();
				return ['inserted' => false, 'order' => $existing];
			}

			$orderData = array_merge($data, [
				'shopid' => $shopId,
				'orderid' => $orderNumber,
				'token' => $token,
			]);
			$orderId = $this->addOrder($orderData);
			$orderposDb = (new Shops_Model_DbTable_Orderpos())->setClientId($this->getClientId());

			foreach ($positions as $position) {
				$orderposDb->addOrderpos(array_merge($position, [
					'shopid' => $shopId,
					'orderid' => $orderNumber,
				]));
			}

			$adapter->commit();
			$order = $this->getByCheckoutToken($shopId, $token);
			if (!$order) $order = array_merge($orderData, ['id' => $orderId]);

			return ['inserted' => true, 'order' => $order];
		} catch (Throwable $e) {
			try {
				$adapter->rollBack();
			} catch (Throwable $rollbackError) {
				error_log('Shop checkout rollback failed: ' . $rollbackError->getMessage());
			}

			$existing = $this->getByCheckoutToken($shopId, $token);
			if ($existing) return ['inserted' => false, 'order' => $existing];
			throw $e;
		}
	}

	public function getOrder($itemid, $orderid)
	{
		$itemid = (int)$itemid;
		$where = $this->getAccessWhere();
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$where[] = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$where[] = $this->getAdapter()->quoteInto('orderid = ?', $orderid);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getOrders($ids)
	{
		$where = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function updateOrder($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$this->updateById($id, $data);
	}

	public function lock($id): void
	{
		$id = (int)$id;
		$data = array();
		$data['locked'] = $this->_user['id'];
		$data['lockedtime'] = $this->_date;
		$this->updateById($id, $data);
	}

	public function unlock($id): void
	{
		$id = (int)$id;
		$data = array('locked' => 0);
		$this->updateById($id, $data);
	}

	public function deleteOrder($itemid)
	{
		$itemid = (int)$itemid;
		$where = $this->getAccessWhere();
		$where[] = $this->getAdapter()->quoteInto('itemid = ?', $itemid);
		$this->delete($where);
	}
}
