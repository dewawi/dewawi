<?php

class Admin_Model_Entity_Deliverytime
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Deliverytime',
			'alias' => 'd',

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
