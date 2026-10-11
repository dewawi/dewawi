<?php

class Statistics_Model_List_Customers extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'rank',
				'label' => '',
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
				'class' => 'dw-col-title',
			],
			[
				'name' => 'cfulltitle',
				'label' => 'STATISTICS_CUSTOMER_CATEGORY',
			],
			[
				'name' => 'subtotal',
				'label' => 'STATISTICS_TURNOVER',
			],
			[
				'name' => 'documents',
				'label' => 'STATISTICS_INVOICE_QUANTITY',
				'type' => 'callback',
				'callback' => static function($item, $column, $list) {
					$parts = [];

					if(!empty($item['invoices'])) {
						$parts[] = $list->escape($list->translate('STATISTICS_INVOICE')).': '.count($item['invoices']);
					}

					if(!empty($item['creditnotes'])) {
						$parts[] = $list->escape($list->translate('STATISTICS_CREDIT_NOTE')).': '.count($item['creditnotes']);
					}

					return implode('<br>', $parts);
				},
				'export_callback' => static function($item, $column, $list) {
					$parts = [];

					if(!empty($item['invoices'])) {
						$parts[] = $list->translate('STATISTICS_INVOICE').': '.count($item['invoices']);
					}

					if(!empty($item['creditnotes'])) {
						$parts[] = $list->translate('STATISTICS_CREDIT_NOTE').': '.count($item['creditnotes']);
					}

					return implode(' | ', $parts);
				},
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
