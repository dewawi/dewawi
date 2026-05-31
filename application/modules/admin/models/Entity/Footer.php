<?php

class Admin_Model_Entity_Footer
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Footer',
			'alias' => 'f',

			'search' => [
				'templateid',
				'column',
				'text',
				'width',
			],

			'orders' => [
				'id',
				'templateid',
				'column',
				'width',
				'created',
				'modified',
			],
		];
	}
}
