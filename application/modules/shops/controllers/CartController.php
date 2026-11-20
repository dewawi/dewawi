<?php

class Shops_CartController extends Shops_Controller_Action
{
	public function init()
	{
		parent::init();
		if (!$this->_siteContext->hasFeature('cart')) throw new Zend_Controller_Action_Exception('Not found', 404);
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
				'uom' => $item['uom'],
				'total' => $currency->toCurrency(round($item['price'] * $item['quantity'], 2)),
				'price' => $currency->toCurrency($item['price']),
			];
		}

		$this->view->items = $items;
		$this->view->total = $currency->toCurrency($this->cart->getTotalPrice());

		$this->assignMessages();
	}

	public function addAction()
	{
		if (!$this->validateMutation()) return;
		$id = $this->postedId();
		$quantity = $this->getRequest()->getPost('quantity', '1');
		if (!$id || !Shops_Model_ShoppingCart::validQuantity($quantity)) return $this->cartResponse(false, 'SHOPS_CART_INVALID', 400);

		$item = (new Shops_Model_DbTable_Item())->getCartItem($id);
		$price = $item ? Shops_Model_ShoppingCart::itemPrice($item) : null;
		if ($price === null) return $this->cartResponse(false, 'SHOPS_CART_UNAVAILABLE', 400);
		if (!$this->cart->addItem($id, $item['title'], $item['sku'], $price, $quantity, $item['uom'])) return $this->cartResponse(false, 'SHOPS_CART_INVALID', 400);
		$this->_flashMessenger->addMessage(['type' => 'success', 'message' => 'SHOPS_CART_ADDED']);
		return $this->cartResponse(true, 'SHOPS_CART_ADDED');
	}

	public function updateAction()
	{
		if (!$this->validateMutation()) return;
		$id = $this->postedId();
		$quantity = $this->getRequest()->getPost('quantity');
		if (!$id || !Shops_Model_ShoppingCart::validQuantity($quantity, true)
			|| !$this->cart->updateItem($id, $quantity)) return $this->cartResponse(false, 'SHOPS_CART_INVALID', 400);
		return $this->cartResponse(true, 'SHOPS_CART_UPDATED');
	}

	public function countAction()
	{
		$this->cart->revalidate();
		return $this->cartResponse(true);
	}

	public function removeAction()
	{
		if (!$this->validateMutation()) return;
		$id = $this->postedId();
		if (!$id || !$this->cart->removeItem($id)) return $this->cartResponse(false, 'SHOPS_CART_UNAVAILABLE', 400);
		return $this->cartResponse(true, 'SHOPS_CART_ITEM_REMOVED');
	}

	public function clearAction()
	{
		if (!$this->validateMutation()) return;
		$this->cart->clearCart();
		return $this->cartResponse(true, 'SHOPS_CART_CLEARED');
	}

	private function postedId(): int
	{
		return (int)filter_var($this->getRequest()->getPost('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
	}

	private function validateMutation(): bool
	{
		$request = $this->getRequest();
		if (!$request->isPost()) {
			$this->getResponse()->setHeader('Allow', 'POST', true);
			$this->cartResponse(false, 'SHOPS_CART_REQUEST_INVALID', 405);
			return false;
		}
		$token = $request->getPost('csrf_token');
		if (!is_string($token) || !hash_equals($this->cart->getCsrfToken(), $token)) {
			$this->cartResponse(false, 'SHOPS_FORM_EXPIRED', 403);
			return false;
		}
		if (!$this->isReasonableSubmission((array)$request->getPost(), 8, 2048, 2)) {
			$this->cartResponse(false, 'SHOPS_SUBMISSION_TOO_LARGE', 413);
			return false;
		}
		$revision = filter_var($request->getPost('revision'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		$changed = $this->cart->revalidate();
		if ($revision === false || $revision !== $this->cart->getRevision() || $changed) {
			$this->cartResponse(false, 'SHOPS_CART_CHANGED', 409);
			return false;
		}
		return true;
	}

	private function cartResponse(bool $success, string $message = '', int $status = 200)
	{
		$this->getResponse()->setHttpResponseCode($status)->setHeader('Cache-Control', 'private, no-store', true);
		$items = $this->cart->getItems();
		foreach ($items as &$item) $item['total'] = round($item['price'] * $item['quantity'], 2);
		unset($item);
		return $this->_helper->json([
			'success' => $success,
			'message' => $message === '' ? '' : $this->view->translate($message),
			'notices' => array_map(fn($key) => $this->view->translate($key), $this->cart->consumeNotices()),
			'cart' => $items,
			'total' => $this->cart->getTotalPrice(),
			'count' => $this->cart->getItemCount(),
			'cartItemCount' => $this->cart->getItemCount(),
			'revision' => $this->cart->getRevision(),
			'checkoutEnabled' => $this->_siteContext->hasFeature('checkout'),
		]);
	}
}
