<?php

class Sales_Model_Entity_Salesorder
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Sales_Model_DbTable_Salesorder',
			'alias' => 's',

			'pinned' => true,

			'joins' => [
				[
					'table' => 'contact',
					'alias' => 'c',
					'on' => 's.contactid = c.contactid',
					'columns' => [
						'catid AS catid',
						'id AS cid',
					],
				],
			],

			'search' => [
				'title',
				'salesorderid',
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
				'documentid' => 'salesorderid',
				'name1' => 'c.name1',
			],
		];
	}
}
