<?php

class Admin_Model_Entity_Manufacturer
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Manufacturer',
			'alias' => 'm',

			'search' => [
				'name',
			],

			'orders' => [
				'id',
				'name',
				'created',
				'modified',
			],
		];
	}
}
