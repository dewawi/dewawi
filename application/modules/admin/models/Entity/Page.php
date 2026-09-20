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
				'slug' => 'sl.slug',
			],

			'joins' => [
				[
					'type' => 'left',
					'table' => 'shop',
					'alias' => 's',
					'on' => 'p.shopid = s.id',
					'columns' => [],
				],
				[
					'type' => 'left',
					'table' => 'slug',
					'alias' => 'sl',
					'on' => "sl.clientid = p.clientid AND sl.shopid = p.shopid AND sl.module = 'shops' AND sl.controller = 'page' AND sl.entityid = p.id AND sl.deleted = 0",
					'columns' => [],
				],
			],

			'search' => [
				'title',
				'content',
				'image',
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
				'id',
				'title',
				'slug' => 'sl.slug',
				'parentid',
				'ordering',
				'created',
				'modified',
			],
		];
	}
}
