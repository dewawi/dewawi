<?php

class Items_Service_ShopAssignment
{
	public function getShopOptions(): array
	{
		return ['0' => 'ITEMS_SHOP_NONE'] + $this->loadShopOptions();
	}

	public function getCategoryOptions(int $shopId): array
	{
		$options = ['0' => 'ITEMS_SHOP_CATEGORY_NONE'];

		if ($shopId <= 0 || !$this->findShop($shopId)) return $options;

		return $options + $this->loadCategoryOptions($shopId);
	}

	public function normalize(array $values, array $current): array
	{
		$hasShop = array_key_exists('shopid', $values);
		$hasCategory = array_key_exists('shopcatid', $values);

		if (!$hasShop && !$hasCategory) return $values;

		$shopId = (int)($values['shopid'] ?? $current['shopid'] ?? 0);
		$shopChanged = $hasShop && $shopId !== (int)($current['shopid'] ?? 0);

		if ($shopId <= 0) {
			$values['shopid'] = 0;
			$values['shopcatid'] = null;
			return $values;
		}

		if (!$this->findShop($shopId)) throw new InvalidArgumentException('Invalid shop assignment');
		if (!$shopChanged && !$hasCategory) return $values;

		$categoryId = $shopChanged && !$hasCategory
			? 0
			: (int)($values['shopcatid'] ?? $current['shopcatid'] ?? 0);

		$values['shopid'] = $shopId;

		if ($categoryId <= 0) {
			$values['shopcatid'] = null;
			return $values;
		}

		$category = $this->findCategory($categoryId);

		if (!$category || ($category['type'] ?? '') !== 'shop' || (int)($category['shopid'] ?? 0) !== $shopId) {
			throw new InvalidArgumentException('Invalid shop category assignment');
		}

		$values['shopcatid'] = $categoryId;
		return $values;
	}

	public function getStatus(array $item): array
	{
		$shopId = (int)($item['shopid'] ?? 0);
		$categoryId = (int)($item['shopcatid'] ?? 0);

		if ($shopId <= 0) return ['state' => 'unassigned', 'message' => 'ITEMS_SHOP_ASSIGNMENT_UNASSIGNED'];
		if (!$this->findShop($shopId)) return ['state' => 'invalid', 'message' => 'ITEMS_SHOP_ASSIGNMENT_INVALID'];
		if ($categoryId <= 0) return ['state' => 'incomplete', 'message' => 'ITEMS_SHOP_ASSIGNMENT_INCOMPLETE'];

		$category = $this->findCategory($categoryId);
		if (!$category || ($category['type'] ?? '') !== 'shop' || (int)($category['shopid'] ?? 0) !== $shopId) {
			return ['state' => 'invalid', 'message' => 'ITEMS_SHOP_ASSIGNMENT_INVALID'];
		}

		return ['state' => 'assigned', 'message' => 'ITEMS_SHOP_ASSIGNMENT_ASSIGNED'];
	}

	protected function loadShopOptions(): array
	{
		return (new Admin_Model_DbTable_Shop())->getSelectOptions();
	}

	protected function loadCategoryOptions(int $shopId): array
	{
		return (new Admin_Model_DbTable_Category())->getParentSelectOptions('shop', $shopId);
	}

	protected function findShop(int $shopId): ?array
	{
		return (new Admin_Model_DbTable_Shop())->getById($shopId);
	}

	protected function findCategory(int $categoryId): ?array
	{
		return (new Admin_Model_DbTable_Category())->getById($categoryId);
	}
}
