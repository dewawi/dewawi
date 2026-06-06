<?php

class Admin_Model_Entity_Slide
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Slide',
			'alias' => 's',

			'search' => [
				'image',
				'url',
				'title',
				'description',
			],

			'filters' => [
				'shopid' => [
					'type' => 'equals',
					'column' => 'shopid',
				],
				'activated' => [
					'type' => 'equals',
					'column' => 'activated',
				],
			],

			'orders' => [
				'id',
				'shopid',
				'title',
				'ordering',
				'created',
				'modified',
			],
		];
	}
}
