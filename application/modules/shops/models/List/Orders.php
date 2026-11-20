<?php

class Shops_Model_List_Orders extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			['name' => 'orderid', 'label' => 'SHOPS_ORDER_ID', 'type' => 'link', 'url' => ['action' => 'view']],
			['name' => 'orderdate', 'label' => 'SHOPS_ORDER_DATE', 'type' => 'date', 'format' => 'd.m.Y'],
			['name' => 'customername', 'label' => 'SHOPS_ORDER_CUSTOMER', 'type' => 'callback', 'callback' => [$this, 'customer']],
			['name' => 'website_title', 'label' => 'SHOPS_ORDER_SHOP', 'type' => 'callback', 'callback' => [$this, 'shop']],
			['name' => 'total', 'label' => 'SHOPS_ORDER_NET', 'type' => 'callback', 'callback' => [$this, 'amount']],
			['name' => 'status', 'label' => 'SHOPS_ORDER_STATUS', 'type' => 'callback', 'callback' => [$this, 'status']],
			['name' => 'salesorderid', 'label' => 'SHOPS_ORDER_ERP', 'type' => 'callback', 'callback' => [$this, 'salesorder']],
		];
	}

	public function customer($item): string { return $this->escape($item['customername'] ?: $this->translate('SHOPS_ORDER_UNKNOWN')); }
	public function shop($item): string
	{
		$data = Shops_Service_OrderSnapshot::decode($item['snapshot'] ?? null);
		return $this->escape($data['shop']['title'] ?? $item['website_title'] ?? $this->translate('SHOPS_ORDER_UNKNOWN'));
	}
	public function amount($item): string
	{
		return $this->escape(number_format((float)$item['total'], 2, ',', '.') . ' ' . ($item['currency'] ?: $this->translate('SHOPS_ORDER_UNKNOWN')));
	}
	public function status($item): string { return $this->escape($this->translate(Shops_Model_DbTable_Order::STATUSES[$item['status'] ?? ''] ?? 'SHOPS_ORDER_UNKNOWN')); }
	public function salesorder($item): string
	{
		$client = Zend_Registry::get('Client');
		$owner = (int)($client['modules']['sales'] ?? $client['id']);
		if (!$item['salesorderid'] || (int)$item['salesclientid'] !== $owner) return '';
		$url = $this->getView()->url(['module' => 'sales', 'controller' => 'salesorder', 'action' => 'edit', 'id' => (int)$item['salesorderid']], null, true);
		return '<a href="' . $this->escapeAttr($url) . '">#' . (int)$item['salesorderid'] . '</a>';
	}
}
