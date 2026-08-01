<?php

class Admin_Model_Entity_Warehouse
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Warehouse',
			'alias' => 'w',

			'search' => [
				'name',
			],

			'orders' => [
				'id',
				'title',
				'created',
				'modified',
			],
		];
	}
}
