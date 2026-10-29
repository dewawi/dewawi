<?php

class Items_Model_List_Stockplannings extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			['name' => 'id', 'label' => 'ITEMS_ID', 'type' => 'link', 'class' => 'dw-col-id'],
			['name' => 'planningdate', 'label' => 'ITEMS_PLANNING_DATE', 'type' => 'date'],
			[
				'name' => 'type',
				'label' => 'ITEMS_PLANNING_TYPE',
				'type' => 'callback',
				'callback' => static function($item, array $column, DEEC_List $list): string {
					$key = $item->type === 'incoming' ? 'ITEMS_PLANNING_INCOMING' : 'ITEMS_PLANNING_RESERVATION';
					return $list->escape($list->translate($key));
				},
				'export_callback' => static fn($item): string => (string)$item->type,
			],
			['name' => 'sku', 'label' => 'ITEMS_SKU', 'type' => 'text', 'class' => 'dw-col-sku'],
			['name' => 'itemtitle', 'label' => 'ITEMS_TITLE', 'type' => 'text', 'class' => 'dw-col-title'],
			['name' => 'warehousetitle', 'label' => 'ITEMS_WAREHOUSE', 'type' => 'text'],
			['name' => 'quantity', 'label' => 'ITEMS_PLANNING_QUANTITY', 'type' => 'text'],
			['name' => 'fulfilled', 'label' => 'ITEMS_PLANNING_FULFILLED', 'type' => 'text'],
			['name' => 'remaining', 'label' => 'ITEMS_PLANNING_REMAINING', 'type' => 'text'],
			[
				'name' => 'status',
				'label' => 'ITEMS_PLANNING_STATUS',
				'type' => 'callback',
				'callback' => static function($item, array $column, DEEC_List $list): string {
					return $list->escape($list->translate('ITEMS_PLANNING_STATUS_' . strtoupper((string)$item->status)));
				},
				'export_callback' => static fn($item): string => (string)$item->status,
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'elements' => [['name' => 'edit'], ['name' => 'copy'], ['name' => 'delete']],
			],
		];
	}
}
