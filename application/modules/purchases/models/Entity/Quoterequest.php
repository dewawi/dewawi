<?php

class Purchases_Model_Entity_Quoterequest
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Purchases_Model_DbTable_Quoterequest',
			'alias' => 'q',

			'pinned' => true,

			'joins' => [
				[
					'table' => 'contact',
					'alias' => 'c',
					'on' => 'q.contactid = c.contactid',
					'columns' => [
						'catid AS catid',
						'id AS cid',
					],
				],
			],

			'search' => [
				'title',
				'quoterequestid',
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
				'documentid' => 'quoterequestid',
				'name1' => 'c.name1',
			],
		];
	}
}
