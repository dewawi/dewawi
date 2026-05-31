<?php

class Admin_Model_Entity_User
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_User',
			'alias' => 'u',

			'columns' => [
				'id',
				'username',
				'email',
				'activated',
				'created',
				'modified',
			],

			'search' => [
				'username',
				'email',
			],

			'orders' => [
				'id',
				'username',
				'email',
				'activated',
				'created',
				'modified',
			],
		];
	}
}
