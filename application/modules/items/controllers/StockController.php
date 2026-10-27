<?php

class Items_StockController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$stockDate = trim(
			(string)$this->_getParam('stockdate', '')
		);

		if($stockDate !== '') {
			$this->buildHistoricalStockView($stockDate);
			return;
		}

		$this->buildCurrentStockView();
	}

	protected function buildCurrentStockView(): void
	{
		$entity = Items_Model_Entity_Stock::listConfig();

		$this->buildListView([
			'viewKey' => 'stocks',
			'list' => 'Items_Model_List_Stocks',
			'entity' => $entity,
		]);

		if($this->getRequest()->getActionName() === 'export') {
			return;
		}

		$params = $this->getListParams();
		$warehouseId = (int)($params['warehouseid'] ?? 0);

		$stockDb = new Items_Model_DbTable_Itemstock();

		$totalValue = $stockDb->getTotalValue(
			$warehouseId > 0
				? $warehouseId
				: null
		);

		$filteredValue = $stockDb->getFilteredTotalValue(
			$params,
			$this->getListOptions()
		);

		$pageValue = 0.0;

		foreach($this->view->stocksItems as $item) {
			$pageValue += (float)$item->stockvalue;
		}

		$this->view->assign([
			'stockTotalValue' => $totalValue,
			'stockFilteredValue' => $filteredValue,
			'stockPageValue' => $pageValue,
			'stockRecords' => (int)($this->view->pagination['records'] ?? 0),
			'showStockFilteredValue' => $this->hasStockValueFilter(
				$params,
				$entity
			),
			'showStockPageValue' =>
				(int)($this->view->pagination['pages'] ?? 1) > 1
				&& abs($pageValue - $filteredValue) > 0.00001,
		]);
	}

	protected function hasStockValueFilter(array $params, array $config): bool
	{
		$keyword = preg_replace(
			'/[^\p{L}\p{N}\s]/u',
			' ',
			trim((string)($params['keyword'] ?? ''))
		);

		if(trim((string)$keyword) !== '') {
			return true;
		}

		foreach(array_keys($config['filters'] ?? []) as $name) {
			if($name === 'warehouseid') {
				continue;
			}

			$value = $params[$name] ?? null;

			if(is_array($value)) {
				if($value) {
					return true;
				}

				continue;
			}

			if($value !== null && $value !== '' && $value !== '0' && $value !== 'all') {
				return true;
			}
		}

		return false;
	}

	protected function getToolbarClass(): string
	{
		return 'Items_Form_ToolbarStock';
	}

	protected function buildHistoricalStockView(
		string $stockDate
	): void {
		$date = DateTime::createFromFormat(
			'Y-m-d',
			$stockDate
		);

		if(!$date) {
			$this->buildCurrentStockView();
			return;
		}

		$date->setTime(23, 59, 59);

		$warehouseId = (int)$this->_getParam(
			'warehouseid',
			0
		);

		$ledgerDb = new Items_Model_DbTable_Ledger();

		$items = $ledgerDb->getStockAt(
			$date->format('Y-m-d H:i:s'),
			$warehouseId > 0
				? $warehouseId
				: null
		);

		$list = new Items_Model_List_StockHistory([
			'items' => $items,
			'view' => $this->view,
			'module' => 'items',
			'controller' => 'stock',
			'selectable' => false,
		]);

		$this->view->assign([
			'stocks' => $list,
			'stockdate' => $stockDate,
		]);

		$this->assignMessages();
	}
}
