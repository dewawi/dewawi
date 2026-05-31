<?php

class Admin_Model_Entity_Shop
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Shop',
			'alias' => 's',

			'search' => [
				'title',
				'url',
				'logo',
				'footer',
				'emailsender',
				'timezone',
				'language',
			],

			'orders' => [
				'id',
				'title',
				'url',
				'emailsender',
				'timezone',
				'language',
				'activated',
				'created',
				'modified',
			],
		];
	}
}
