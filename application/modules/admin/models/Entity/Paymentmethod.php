<?php

class Admin_Model_Entity_Paymentmethod
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Paymentmethod',
			'alias' => 'p',

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
