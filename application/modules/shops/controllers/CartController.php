<?php

class Shops_CartController extends Shops_Controller_Action
{
	public function init()
	{
		parent::init();
		$this->initSiteCart();
	}

	public function indexAction()
	{
		$this->initSiteLayout();

		$currency = $this->_helper->Currency->getCurrency('EUR', 'USE_SYMBOL');

		$items = [];
		foreach ($this->cart->getItems() as $id => $item) {
			$items[$id] = [
				'title' => $item['title'],
				'sku' => $item['sku'],
				'quantity' => $item['quantity'],
				'total' => $currency->toCurrency($item['price'] * $item['quantity']),
				'price' => $currency->toCurrency($item['price']),
			];
		}

		$this->view->items = $items;
		$this->view->total = $currency->toCurrency($this->cart->getTotalPrice());

		$this->assignMessages();
	}

	public function addAction()
	{
		if (!$this->getRequest()->isPost()) {
			return $this->_helper->json(['success' => false, 'message' => 'Invalid request']);
		}

		$id = (int)$this->_getParam('id', 0);
		$quantity = $this->_getParam('quantity', 1);

		if (!$id || !is_numeric($quantity) || (float)$quantity <= 0) {
			return $this->_helper->json(['success' => false, 'message' => 'Invalid cart item']);
		}

		$itemDb = new Shops_Model_DbTable_Item();
		$item = $itemDb->getItem($id);

		if (!$item) {
			return $this->_helper->json(['success' => false, 'message' => 'Item not found']);
		}

		$price = !empty($item['specialprice']) ? $item['specialprice'] : $item['price'];

		$this->cart->addItem($id, $item['title'], $item['sku'], $price, (float)$quantity);

		return $this->_helper->json([
			'success' => true,
			'cart' => $this->cart->getItems(),
			'total' => $this->cart->getTotalPrice(),
			'cartItemCount' => $this->cart->getItemCount(),
		]);
	}

	public function updateAction()
	{
		if (!$this->getRequest()->isPost()) {
			return $this->_helper->json(['success' => false, 'message' => 'Invalid request']);
		}

		$id = (int)$this->_getParam('id');
		$quantity = $this->_getParam('quantity');

		if(!$id || !is_numeric($quantity)) {
			return $this->_helper->json(['success' => false, 'message' => 'Invalid cart item']);
		}

		if(!$this->cart->updateItem($id, (float)$quantity)) {
			return $this->_helper->json(['success' => false, 'message' => 'Cart item not found']);
		}

		return $this->_helper->json([
			'success' => true,
			'cart' => $this->cart->getItems(),
			'total' => $this->cart->getTotalPrice(),
			'cartItemCount' => $this->cart->getItemCount(),
		]);
	}

	public function countAction()
	{
		$count = $this->cart->getItemCount();
		$response = [
			'success' => true,
			'count' => $count,
		];
		$this->_helper->json($response);
	}

	public function removeAction()
	{
		if (!$this->getRequest()->isPost()) {
			return $this->_helper->json(['success' => false, 'message' => 'Invalid request']);
		}

		$id = (int)$this->_getParam('id');

		if(!$id || !$this->cart->removeItem($id)) {
			return $this->_helper->json(['success' => false, 'message' => 'Cart item not found']);
		}

		return $this->_helper->json([
			'success' => true,
			'cart' => $this->cart->getItems(),
			'total' => $this->cart->getTotalPrice(),
			'cartItemCount' => $this->cart->getItemCount(),
		]);
	}

	public function clearAction()
	{
		if (!$this->getRequest()->isPost()) {
			return $this->_helper->json(['success' => false, 'message' => 'Invalid request']);
		}

		$this->cart->clearCart();

		return $this->_helper->json([
			'success' => true,
			'cart' => [],
			'total' => 0,
			'cartItemCount' => 0,
		]);
	}
}
