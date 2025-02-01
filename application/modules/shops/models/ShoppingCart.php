<?php
class Shops_Model_ShoppingCart
{
	private $session;

	public function __construct()
	{
		$this->session = new Zend_Session_Namespace('shopping_cart');
		if (!isset($this->session->items)) {
			$this->session->items = [];
		}
	}

	public function addItem($id, $title, $sku, $price, $quantity = 1)
	{
		if (isset($this->session->items[$id])) {
			$this->session->items[$id]['quantity'] += $quantity;
		} else {
			$this->session->items[$id] = [
				'title' => $title,
				'sku' => $sku,
				'price' => $price,
				'quantity' => $quantity
			];
		}
	}

	public function updateItem($id, $quantity)
	{
		if (isset($this->session->items[$id])) {
			$this->session->items[$id]['quantity'] = $quantity;
			if ($this->session->items[$id]['quantity'] <= 0) {
				unset($this->session->items[$id]);
			}
		}
	}

	public function removeItem($id)
	{
		if (isset($this->session->items[$id])) {
			unset($this->session->items[$id]);
		}
	}

	public function getItems()
	{
		return $this->session->items;
	}

	public function getItemCount()
	{
		$count = 0;
		foreach ($this->session->items as $item) {
			$count += $item['quantity'];
		}
		return $count;
	}

	public function getTotalPrice()
	{
		$total = 0;
		foreach ($this->session->items as $item) {
			$total += $item['price'] * $item['quantity'];
		}
		return $total;
	}

	public function clearCart()
	{
		$this->session->items = [];
	}
}
