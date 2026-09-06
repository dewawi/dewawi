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
		$id = (int)$id;
		$price = (float)$price;
		$quantity = (float)$quantity;

		if(!$id || $quantity <= 0) return false;

		if(isset($this->session->items[$id])) {
			$this->session->items[$id]['quantity'] += $quantity;
		} else {
			$this->session->items[$id] = [
				'title' => $title,
				'sku' => $sku,
				'price' => $price,
				'quantity' => $quantity,
			];
		}

		return true;
	}

	public function updateItem($id, $quantity)
	{
		$id = (int)$id;
		$quantity = (float)$quantity;

		if(!isset($this->session->items[$id]) || $quantity < 0) return false;

		if($quantity == 0) {
			unset($this->session->items[$id]);
			return true;
		}

		$this->session->items[$id]['quantity'] = $quantity;
		return true;
	}

	public function removeItem($id)
	{
		$id = (int)$id;

		if(!isset($this->session->items[$id])) return false;

		unset($this->session->items[$id]);
		return true;
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
		return true;
	}
}
