<?php

class Processes_Model_Entity_Process
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Processes_Model_DbTable_Process',
			'alias' => 'p',

			'pinned' => true,

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
				'processid',
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
				'documentid' => 'processid',
				'name1' => 'c.name1',
			],
		];
	}
}
