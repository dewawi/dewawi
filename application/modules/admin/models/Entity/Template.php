<?php

class Admin_Model_Entity_Template
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Template',
			'alias' => 't',

			'search' => [
				'description',
				'logo',
				'website',
			],

			'orders' => [
				'id',
				'description',
				'logo',
				'website',
				'activated',
				'created',
				'modified',
			],
		];
	}
}
