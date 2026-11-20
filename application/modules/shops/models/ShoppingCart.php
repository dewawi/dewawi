<?php
class Shops_Model_ShoppingCart
{
	public const MAX_QUANTITY = 100000;
	public const MAX_TOTAL = 99999999;
	private $session;

	public function __construct()
	{
		$context = Zend_Registry::get('SiteContext');
		if ($context->getClientId() <= 0 || $context->getSiteId() <= 0) throw new RuntimeException('Cart context is missing');

		$this->session = new Zend_Session_Namespace('shopping_cart_' . $context->getClientId() . '_' . $context->getSiteId());
		if (!isset($this->session->items)) $this->session->items = [];
		if (!isset($this->session->revision)) $this->session->revision = 0;
		if (!isset($this->session->csrfToken)) $this->session->csrfToken = bin2hex(random_bytes(32));

		// Legacy carts have no trustworthy shop assignment and must never be imported.
		$legacy = new Zend_Session_Namespace('shopping_cart');
		if (!empty($legacy->items)) $this->notice('SHOPS_CART_RESET');
		unset($legacy->items);
	}

	public static function validQuantity($quantity, bool $allowZero = false): bool
	{
		if (!is_int($quantity) && !is_float($quantity) && !is_string($quantity)) return false;
		if (!preg_match('/^[0-9]+(?:\.[0-9]{1,4})?$/D', (string)$quantity)) return false;
		$quantity = (float)$quantity;
		return is_finite($quantity) && ($allowZero ? $quantity >= 0 : $quantity > 0) && $quantity <= self::MAX_QUANTITY;
	}

	public static function itemPrice(array $item): ?float
	{
		$special = $item['specialprice'] ?? null;
		if ($special !== null && $special !== '' && (!is_numeric($special) || !is_finite((float)$special) || (float)$special < 0)) return null;
		$price = (float)$special > 0 ? $special : ($item['price'] ?? null);
		if (strtoupper((string)($item['currency'] ?? '')) !== 'EUR' || !is_numeric($price)
			|| !is_finite((float)$price) || (float)$price < 0 || (float)$price > self::MAX_TOTAL) return null;
		return (float)$price;
	}

	public function getCsrfToken(): string
	{
		return $this->session->csrfToken;
	}

	public function getRevision(): int
	{
		return $this->session->revision;
	}

	public function consumeNotices(): array
	{
		$notices = $this->session->notices ?? [];
		unset($this->session->notices);
		return $notices;
	}

	private function notice(string $key): void
	{
		$this->session->notices = array_values(array_unique(array_merge($this->session->notices ?? [], [$key])));
	}

	public function revalidate(): bool
	{
		if (!$this->session->items) return false;
		$itemDb = new Shops_Model_DbTable_Item();
		$items = [];
		$total = 0;
		foreach ($this->session->items as $id => $row) {
			$item = $itemDb->getCartItem((int)$id);
			$price = $item ? self::itemPrice($item) : null;
			if (!is_array($row) || !self::validQuantity($row['quantity'] ?? null) || $price === null
				|| $total + round($price * (float)$row['quantity'], 2) > self::MAX_TOTAL) {
				$this->notice('SHOPS_CART_REMOVED');
				continue;
			}
			$items[$id] = ['title' => (string)$item['title'], 'sku' => (string)$item['sku'], 'price' => $price,
				'quantity' => (float)$row['quantity'], 'uom' => $item['uom'] ?? null];
			$total += round($price * (float)$row['quantity'], 2);
			if ($items[$id] != $row) $this->notice('SHOPS_CART_REFRESHED');
		}
		if ($items === $this->session->items) return false;
		$this->session->items = $items;
		$this->session->revision++;
		return true;
	}

	public function addItem($id, $title, $sku, $price, $quantity = 1, $uom = null)
	{
		$id = (int)$id;
		if ($id <= 0 || !self::validQuantity($quantity) || !is_numeric($price)
			|| !is_finite((float)$price) || (float)$price < 0 || (float)$price > self::MAX_TOTAL) return false;
		$quantity = round(($this->session->items[$id]['quantity'] ?? 0) + (float)$quantity, 4);
		if (!self::validQuantity($quantity) || !$this->fitsTotal($id, (float)$price, $quantity)) return false;
		$this->session->items[$id] = ['title' => $title, 'sku' => $sku, 'price' => (float)$price, 'quantity' => $quantity, 'uom' => $uom];
		$this->session->revision++;
		return true;
	}

	public function updateItem($id, $quantity)
	{
		$id = (int)$id;
		if (!isset($this->session->items[$id]) || !self::validQuantity($quantity, true)) return false;
		if ((float)$quantity == 0) return $this->removeItem($id);
		if (!$this->fitsTotal($id, $this->session->items[$id]['price'], (float)$quantity)) return false;
		$this->session->items[$id]['quantity'] = (float)$quantity;
		$this->session->revision++;
		return true;
	}

	private function fitsTotal(int $id, float $price, float $quantity): bool
	{
		$total = round($price * $quantity, 2);
		foreach ($this->session->items as $itemId => $item) {
			if ($itemId !== $id) $total += round($item['price'] * $item['quantity'], 2);
		}
		return $total <= self::MAX_TOTAL;
	}

	public function removeItem($id)
	{
		$id = (int)$id;
		if (!isset($this->session->items[$id])) return false;
		unset($this->session->items[$id]);
		$this->session->revision++;
		return true;
	}

	public function getItems()
	{
		return $this->session->items;
	}

	public function getItemCount()
	{
		return array_sum(array_column($this->session->items, 'quantity'));
	}

	public function getTotalPrice()
	{
		$total = 0;
		foreach ($this->session->items as $item) $total += round($item['price'] * $item['quantity'], 2);
		return round($total, 2);
	}

	public function clearCart()
	{
		$this->session->items = [];
		$this->session->revision++;
		return true;
	}
}
