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
		$date = $this->parseStockDate($stockDate);

		if(!$date) {
			$this->buildCurrentStockView();
			return;
		}

		$date->setTime(23, 59, 59);

		$toolbarClass = $this->getToolbarClass();
		$toolbarInlineClass = $this->getToolbarInlineClass();
		$toolbar = new $toolbarClass();
		$toolbarInline = new $toolbarInlineClass();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);
		$params['_export'] = $this->getRequest()->getActionName() === 'export';

		if($this->getRequest()->getActionName() === 'index') {
			$toolbar->addElement([
				'name' => 'export',
				'type' => 'button',
				'label' => 'TOOLBAR_EXPORT',
				'toolbar' => 'actions',
				'wrap' => false,
				'attribs' => ['class' => 'export'],
			]);
		}

		$warehouseId = (int)$this->_getParam(
			'warehouseid',
			0
		);

		$ledgerDb = new Items_Model_DbTable_Ledger();

		$items = $ledgerDb->getStockAt(
			$date->format('Y-m-d H:i:s'),
			$warehouseId > 0
				? $warehouseId
				: null,
			[
				'categoryids' => $this->getHistoricalCategoryIds((int)($params['catid'] ?? 0)),
				'manufacturerid' => (int)($params['manufacturerid'] ?? 0),
				'stockstatus' => (string)($params['stockstatus'] ?? 'all'),
				'keyword' => (string)($params['keyword'] ?? ''),
				'order' => (string)($params['order'] ?? 'sku'),
				'sort' => (string)($params['sort'] ?? 'ASC'),
			]
		);
		$records = count($items);

		if(!$params['_export']) {
			$limit = (int)($params['limit'] ?? 25);
			$page = (int)($params['page'] ?? 1);
			$pages = max(1, (int)ceil($records / max(1, $limit)));

			if($page > $pages) {
				$params['page'] = 1;
				$params['offset'] = 0;
			}

			$items = array_slice($items, (int)$params['offset'], $limit);
			$this->assignPagination($params, $records, $items, $toolbar);
		}

		$list = new Items_Model_List_StockHistory([
			'items' => $items,
			'options' => $options,
			'pagination' => (array)($this->view->pagination ?? []),
			'listFooter' => true,
			'view' => $this->view,
			'module' => 'items',
			'controller' => 'stock',
			'toolbar' => $toolbar,
			'toolbarInline' => $toolbarInline,
			'context' => [
				'user' => $this->_user,
				'action' => $this->_getParam('context_action', $this->getRequest()->getActionName()),
			],
			'selectable' => false,
		]);

		$this->view->assign([
			'stocks' => $list,
			'stocksItems' => $items,
			'stockdate' => $stockDate,
			'options' => $options,
			'toolbar' => $toolbar,
			'toolbarInline' => $toolbarInline,
		]);

		$this->assignMessages();

		if($params['_export']) {
			$this->exportList = $list;
		}
	}

	protected function getHistoricalCategoryIds(int $categoryId): array
	{
		if($categoryId <= 0) {
			return [];
		}

		$categories = (new Application_Model_DbTable_Category())->getCategories('item');

		if(!isset($categories[$categoryId])) {
			return [];
		}

		$byParent = Application_Model_DbTable_Category::groupByParent($categories);
		$ids = [$categoryId];
		$pending = [$categoryId];

		while($pending) {
			$parentId = array_shift($pending);

			foreach(array_keys($byParent[$parentId] ?? []) as $id) {
				$ids[] = (int)$id;
				$pending[] = (int)$id;
			}
		}

		return array_values(array_unique($ids));
	}

	protected function parseStockDate(string $stockDate): ?DateTime
	{
		$date = DateTime::createFromFormat('!Y-m-d', $stockDate);
		$errors = DateTime::getLastErrors();

		if(
			!$date
			|| ($errors && ($errors['warning_count'] || $errors['error_count']))
			|| $date->format('Y-m-d') !== $stockDate
		) {
			return null;
		}

		return $date;
	}
}
