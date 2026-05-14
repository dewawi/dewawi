<?php

class Campaigns_Model_Entity_Campaign
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Campaigns_Model_DbTable_Campaign',
			'alias' => 'ca',

			'search' => [
				'title',
				'campaignid',
				'customerid',
				'billingname1',
				'billingname2',
				'billingcity',
				'emailsubject',
			],

			'filters' => [
				'catid' => [
					'type' => 'category',
					'column' => 'contactcatid',
				],
				'states' => [
					'type' => 'states',
				],
				'country' => [
					'type' => 'country',
					'columns' => ['billingcountry'],
				],
				'daterange' => [
					'type' => 'daterange',
					'columns' => ['created', 'modified'],
				],
			],

			'orders' => [
				'documentid' => 'campaignid',
				'campaignid' => 'campaignid',
				'title' => 'title',
				'customerid' => 'customerid',
				'modified' => 'modified',
				'created' => 'created',
				'startdate' => 'startdate',
				'duedate' => 'duedate',
			],
		];
	}
}
