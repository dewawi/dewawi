<?php

class Items_Model_List_Movements extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'movementdate',
				'label' => 'ITEMS_MOVEMENT_DATE',
				'type' => 'date',
			],
			[
				'name' => 'sku',
				'label' => 'ITEMS_SKU',
				'type' => 'link',
				'class' => 'dw-col-sku',
				'url' => [
					'module_field' => 'module',
					'controller_field' => 'controller',
					'action' => 'edit',
				],
			],
			[
				'name' => 'itemtitle',
				'label' => 'ITEMS_TITLE',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'warehousetitle',
				'label' => 'ITEMS_WAREHOUSE',
				'type' => 'text',
			],
			[
				'name' => 'movementtype',
				'label' => 'ITEMS_MOVEMENT_TYPE',
				'type' => 'callback',
				'callback' => static function($item, array $column, DEEC_List $list): string {
					return $list->escape($list->translate(self::getTypeKey($item)));
				},
				'export_callback' => static function($item, array $column, DEEC_List $list): string {
					return $list->translate(self::getTypeKey($item));
				},
			],
			[
				'name' => 'signedquantity',
				'label' => 'ITEMS_MOVEMENT_QUANTITY',
				'type' => 'callback',
				'callback' => static function($item, array $column, DEEC_List $list): string {
					return $list->escape(self::formatQuantity($list->getFieldValue($item, 'signedquantity')));
				},
				'export_callback' => static fn($item, array $column, DEEC_List $list): string => self::formatQuantity($list->getFieldValue($item, 'signedquantity')),
			],
			[
				'name' => 'status',
				'label' => 'ITEMS_MOVEMENT_STATUS',
				'type' => 'callback',
				'callback' => static function($item, array $column, DEEC_List $list): string {
					return $list->escape($list->translate(self::getStatusKey($list->getFieldValue($item, 'status'))));
				},
				'export_callback' => static function($item, array $column, DEEC_List $list): string {
					return $list->translate(self::getStatusKey($list->getFieldValue($item, 'status')));
				},
			],
			[
				'name' => 'reference',
				'label' => 'ITEMS_MOVEMENT_REFERENCE',
				'type' => 'callback',
				'callback' => static function($item, array $column, DEEC_List $list): string {
					return $list->escape(self::getReference($item, $list));
				},
				'export_callback' => static fn($item, array $column, DEEC_List $list): string => self::getReference($item, $list),
			],
			[
				'name' => 'comment',
				'label' => 'ITEMS_LEDGER_COMMENT',
				'type' => 'text',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'controller_field' => 'controller',
				'elements' => [
					[
						'name' => 'view',
						'show' => static fn($item, array $element, DEEC_List $list): bool => $list->getFieldValue($item, 'source') === 'ledger',
					],
					['name' => 'edit'],
					['name' => 'copy'],
					['name' => 'delete'],
				],
			],
		];
	}

	protected static function getTypeKey($item): string
	{
		$source = (string)(is_array($item) ? ($item['source'] ?? '') : ($item->source ?? ''));
		$type = (string)(is_array($item) ? ($item['movementtype'] ?? '') : ($item->movementtype ?? ''));
		$reason = (string)(is_array($item) ? ($item['reason'] ?? '') : ($item->reason ?? ''));

		if($source === 'stockplanning') {
			return $type === 'reservation' ? 'ITEMS_PLANNING_RESERVATION' : 'ITEMS_PLANNING_INCOMING';
		}

		$reasonKey = 'ITEMS_LEDGER_REASON_' . strtoupper($reason);
		return $reason !== '' ? $reasonKey : ($type === 'outflow' ? 'ITEMS_LEDGER_OUTFLOW' : 'ITEMS_LEDGER_INFLOW');
	}

	protected static function getStatusKey($status): string
	{
		$status = strtoupper((string)$status);
		return $status === 'BOOKED' ? 'ITEMS_MOVEMENT_STATUS_BOOKED' : 'ITEMS_PLANNING_STATUS_' . $status;
	}

	protected static function formatQuantity($quantity): string
	{
		$value = (float)$quantity;
		$formatted = rtrim(rtrim(number_format(abs($value), 4, '.', ''), '0'), '.');
		if($formatted === '') $formatted = '0';
		return ($value > 0 ? '+' : ($value < 0 ? '-' : '')) . $formatted;
	}

	protected static function getReference($item, DEEC_List $list): string
	{
		$external = trim((string)$list->getFieldValue($item, 'externalnumber'));
		if($external !== '') return $external;

		$type = trim((string)$list->getFieldValue($item, 'referencetype'));
		$id = (int)$list->getFieldValue($item, 'referenceid');
		if($type !== '' && $id > 0) return $type . ' #' . $id;
		if($id > 0) return '#' . $id;
		return '';
	}
}
