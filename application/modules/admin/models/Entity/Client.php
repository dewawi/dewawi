<?php

class Admin_Model_Entity_Client
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Client',
			'alias' => 'c',

			'clientFilter' => false,

			'search' => [
				'id',
				'parentid',
				'company',
				'address',
				'postcode',
				'city',
				'country',
				'email',
				'website',
			],

			'orders' => [
				'id',
				'parentid',
				'company',
				'postcode',
				'city',
				'country',
				'email',
				'website',
				'activated',
				'created',
				'modified',
			],
		];
	}
}
