<?php

class Admin_Model_Entity_Module
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Module',
			'alias' => 'm',

			'clientFilter' => false,

			'search' => [
				'name',
				'menu',
			],

			'orders' => [
				'id',
				'name',
				'ordering',
				'active',
				'created',
				'modified',
			],
		];
	}
}
