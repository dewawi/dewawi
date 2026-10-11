<?php

class Statistics_Model_List_Quotes extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'rank',
				'label' => '',
				'type' => 'text',
			],
			[
				'name' => 'contactid',
				'label' => 'STATISTICS_CUSTOMER_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'url' => [
					'module' => 'contacts',
					'controller' => 'contact',
					'action' => 'edit',
					'id_field' => 'cid',
				],
			],
			[
				'name' => 'name1',
				'label' => 'STATISTICS_CUSTOMER_NAME',
				'type' => 'text',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'subtotal',
				'label' => 'STATISTICS_QUOTE_VOLUME',
				'type' => 'text',
			],
			[
				'name' => 'quantity',
				'label' => 'STATISTICS_QUOTE_QUANTITY',
				'type' => 'text',
			],
			[
				'name' => 'share',
				'label' => 'STATISTICS_CUSTOMER_SHARE',
				'type' => 'callback',
				'callback' => static function($item, $column, $list) {
					return $list->escape((string)$item['share']).'%';
				},
				'export_callback' => static function($item, $column, $list) {
					return (string)$item['share'].'%';
				},
			],
		];
	}
}
