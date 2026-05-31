<?php

class Admin_Model_Entity_Taxrate
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Taxrate',
			'alias' => 't',

			'search' => [
				'name',
				'rate',
			],

			'orders' => [
				'id',
				'name',
				'rate',
				'created',
				'modified',
			],
		];
	}
}
