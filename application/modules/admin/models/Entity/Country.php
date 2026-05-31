<?php

class Admin_Model_Entity_Country
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Country',
			'alias' => 'c',

			'deletedFilter' => false,

			'search' => [
				'code',
				'name',
			],

			'orders' => [
				'id',
				'code',
				'name',
			],
		];
	}
}
