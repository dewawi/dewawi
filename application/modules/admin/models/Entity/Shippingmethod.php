<?php

class Admin_Model_Entity_Shippingmethod
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Shippingmethod',
			'alias' => 's',

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
