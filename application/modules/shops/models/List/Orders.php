<?php

class Shops_Model_List_Orders extends DEEC_List
{
	private const STATUS_CLASSES = ['new' => 'created', 'in_progress' => 'in-process', 'transferred' => 'info', 'completed' => 'completed', 'cancelled' => 'cancelled'];

	protected function buildColumns()
	{
		return [
			['name' => 'orderid', 'label' => 'SHOPS_ORDER_ID', 'type' => 'link', 'url' => ['action' => 'view']],
			['name' => 'created', 'label' => 'SHOPS_ORDER_RECEIVED_AT', 'type' => 'date', 'format' => 'd.m.Y H:i'],
			['name' => 'orderdate', 'label' => 'SHOPS_ORDER_DATE', 'type' => 'date', 'format' => 'd.m.Y'],
			['name' => 'customername', 'label' => 'SHOPS_ORDER_CUSTOMER', 'type' => 'callback', 'callback' => [$this, 'customer']],
			['name' => 'website_title', 'label' => 'SHOPS_ORDER_SHOP', 'type' => 'callback', 'callback' => [$this, 'shop']],
			['name' => 'total', 'label' => 'SHOPS_ORDER_NET', 'type' => 'callback', 'callback' => [$this, 'amount']],
			['name' => 'grosstotal', 'label' => 'SHOPS_ORDER_GROSS', 'type' => 'callback', 'callback' => [$this, 'gross']],
			['name' => 'status', 'label' => 'SHOPS_ORDER_STATUS', 'type' => 'callback', 'callback' => [$this, 'status']],
			['name' => 'salesorderid', 'label' => 'SHOPS_ORDER_ERP', 'type' => 'callback', 'callback' => [$this, 'salesorder']],
		];
	}

	public function customer($item): string
	{
		return $this->escape($item['customername'] ?: $this->translate('SHOPS_ORDER_UNKNOWN')) . '<br>' . $this->escape($item['email'] ?? '');
	}
	public function shop($item): string
	{
		$data = Shops_Service_OrderSnapshot::decode($item['snapshot'] ?? null);
		$title = $data['shop']['title'] ?? $item['website_title'] ?? $this->translate('SHOPS_ORDER_UNKNOWN');
		return $this->escape(is_scalar($title) ? $title : $this->translate('SHOPS_ORDER_UNKNOWN')) . ' (#' . (int)$item['shopid'] . ')';
	}
	public function amount($item): string
	{
		return $this->money($item['total'], $item['currency']);
	}
	public function gross($item): string { return $this->money($item['grosstotal'], $item['currency']); }

	private function money($value, $currency): string
	{
		if (!is_numeric($value) || !is_finite((float)$value)) return $this->escape($this->translate('SHOPS_ORDER_UNKNOWN'));
		if (!$currency) return $this->escape(number_format((float)$value, 2) . ' ' . $this->translate('SHOPS_ORDER_UNKNOWN'));
		return $this->renderCurrencyValue($value, $currency);
	}

	public function status($item): string
	{
		$class = self::STATUS_CLASSES[$item['status'] ?? ''] ?? 'default';
		return '<span class="dw-badge dw-badge--' . $class . '">' . $this->escape($this->translate(Shops_Model_DbTable_Order::STATUSES[$item['status'] ?? ''] ?? 'SHOPS_ORDER_UNKNOWN')) . '</span>';
	}

	public function getRowClass($item)
	{
		return parent::getRowClass($item) . ' dw-row--' . (self::STATUS_CLASSES[$item['status'] ?? ''] ?? 'default');
	}
	public function salesorder($item): string
	{
		$client = Zend_Registry::get('Client');
		$owner = (int)($client['modules']['sales'] ?? $client['id']);
		if (!$item['salesorderid'] || (int)$item['salesclientid'] !== $owner) return '';
		$url = $this->getView()->url(['module' => 'sales', 'controller' => 'salesorder', 'action' => 'edit', 'id' => (int)$item['salesorderid']], null, true);
		return '<a href="' . $this->escapeAttr($url) . '">#' . (int)$item['salesorderid'] . '</a>';
	}
}
