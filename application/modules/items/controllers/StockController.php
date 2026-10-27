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
		$this->buildListView([
			'viewKey' => 'stocks',
			'list' => 'Items_Model_List_Stocks',
			'entity' => Items_Model_Entity_Stock::listConfig(),
		]);
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
