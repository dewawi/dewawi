<?php

class Purchases_Model_Entity_Purchaseorder
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Purchases_Model_DbTable_Purchaseorder',
			'alias' => 'p',

			'joins' => [
				[
					'table' => 'contact',
					'alias' => 'c',
					'on' => 'p.contactid = c.contactid',
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
