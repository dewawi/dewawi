<?php

class Admin_Model_Entity_Menu
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Menu',
			'alias' => 'm',

			'search' => [
				'title',
			],

			'filters' => [
				'shopid' => [
					'type' => 'equals',
					'column' => 'shopid',
				],
			],

			'orders' => [
				'id',
				'title',
				'ordering',
				'created',
				'modified',
			],
		];
	}
}
