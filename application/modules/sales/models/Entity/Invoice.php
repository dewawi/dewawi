<?php

class Sales_Model_Entity_Invoice
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Sales_Model_DbTable_Invoice',
			'alias' => 'i',

			'pinned' => true,

			'joins' => [
				[
					'table' => 'contact',
					'alias' => 'c',
					'on' => 'i.contactid = c.contactid',
					'columns' => [
						'catid AS catid',
						'id AS cid',
					],
				],
			],

			'search' => [
				'title',
				'quoteid',
				'contactid',
				'billingname1',
				'billingname2',
				'billingcity',
				'shippingname1',
				'shippingcity',
			],

			'filters' => [
				'catid' => [
					'type' => 'category',
					'alias' => 'c',
				],
				'states' => [
					'type' => 'states',
				],
				'country' => [
					'type' => 'country',
					'columns' => ['billingcountry', 'shippingcountry'],
				],
				'daterange' => [
					'type' => 'daterange',
				],
			],

			'orders' => [
				'documentid' => 'quoteid',
				'name1' => 'c.name1',
			],
		];
	}
}
