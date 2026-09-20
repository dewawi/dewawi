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
				'slug' => 'sl.slug',
			],

			'joins' => [
				[
					'type' => 'left',
					'table' => 'shop',
					'alias' => 's',
					'on' => 'c.shopid = s.id',
					'columns' => [],
				],
				[
					'type' => 'left',
					'table' => 'slug',
					'alias' => 'sl',
					'on' => "sl.clientid = c.clientid AND sl.shopid = c.shopid AND sl.module = 'shops' AND sl.controller = 'category' AND sl.entityid = c.id AND sl.deleted = 0",
					'columns' => [],
				],
			],

			'search' => [
				'title',
				'subtitle',
				'description',
				'sl.slug',
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
				'slug' => 'sl.slug',
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
