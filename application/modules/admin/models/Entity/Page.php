<?php

class Admin_Model_Entity_Page
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Page',
			'alias' => 'p',

			'columns' => [
				'p.*',
				'shoptitle' => 's.title',
			],

			'joins' => [
				[
					'type' => 'left',
					'table' => 'shop',
					'alias' => 's',
					'on' => 'p.shopid = s.id',
					'columns' => [],
				],
			],

			'search' => [
				'title',
				'subtitle',
				'content',
				'image',
			],

			'filters' => [
				'type' => [
					'type' => 'equals',
					'column' => 'type',
				],
				'shopid' => [
					'type' => 'equals',
					'column' => 'shopid',
				],
				'parentid' => [
					'type' => 'equals',
					'column' => 'parentid',
				],
			],

			'orders' => [
				'id',
				'title',
				'parentid',
				'ordering',
				'created',
				'modified',
			],
		];
	}
}
