<?php

class Admin_Model_Entity_Currency
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Currency',
			'alias' => 'c',

			'search' => [
				'code',
				'name',
				'symbol',
			],

			'orders' => [
				'id',
				'code',
				'name',
				'symbol',
				'created',
				'modified',
			],
		];
	}
}
