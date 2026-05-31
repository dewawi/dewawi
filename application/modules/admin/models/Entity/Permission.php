<?php

class Admin_Model_Entity_Permission
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Permission',
			'alias' => 'p',

			'columns' => [
				'id',
				'userid',
				'contacts',
				'items',
				'processes',
				'purchases',
				'sales',
				'statistics',
				'created',
				'modified',
			],

			'search' => [
				'userid',
			],

			'orders' => [
				'id',
				'userid',
				'created',
				'modified',
			],

			'normalizers' => [
				'contacts' => 'json',
				'items' => 'json',
				'processes' => 'json',
				'purchases' => 'json',
				'sales' => 'json',
				'statistics' => 'json',
			],
		];
	}
}
