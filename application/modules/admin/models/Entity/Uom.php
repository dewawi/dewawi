<?php

class Admin_Model_Entity_Uom
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Uom',
			'alias' => 'u',

			'search' => [
				'title',
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
