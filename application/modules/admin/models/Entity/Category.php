<?php

class Admin_Model_Entity_Category
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Admin_Model_DbTable_Category',
			'alias' => 'c',

			'columns' => [
				'c.*',
				'shoptitle' => 's.title',
			],

			'joins' => [
				[
					'type' => 'left',
					'table' => 'shop',
					'alias' => 's',
					'on' => 'c.shopid = s.id',
					'columns' => [],
				],
			],

			'search' => [
				'title',
				'subtitle',
				'description',
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
				'title',
				'parentid',
				'ordering',
				'created',
				'modified',
			],

			'normalizers' => [
				'description' => [
					'type' => 'truncate',
					'length' => 43,
				],
			],
		];
	}
}
