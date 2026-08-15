<?php

class Items_Model_List_Ledgers extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'ITEMS_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
			],
			[
				'name' => 'ledgerdate',
				'label' => 'ITEMS_LEDGER_DATE',
				'type' => 'text',
			],
			[
				'name' => 'sku',
				'label' => 'ITEMS_SKU',
				'type' => 'text',
				'class' => 'dw-col-sku',
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
				'name' => 'type',
				'label' => 'ITEMS_LEDGER_TYPE',
				'type' => 'text',
			],
			[
				'name' => 'reason',
				'label' => 'ITEMS_LEDGER_REASON',
				'type' => 'text',
			],
			[
				'name' => 'quantity',
				'label' => 'ITEMS_LEDGER_QUANTITY',
				'type' => 'text',
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
				'elements' => [
					[
						'name' => 'view',
					],
					[
						'name' => 'edit',
					],
					[
						'name' => 'copy',
					],
					[
						'name' => 'delete',
					],
				],
			],
		];
	}
}
