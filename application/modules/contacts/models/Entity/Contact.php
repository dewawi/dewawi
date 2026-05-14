<?php

class Contacts_Model_Entity_Contact
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Contacts_Model_DbTable_Contact',
			'alias' => 'c',

			'columns' => [
				'id',
				'contactid',
				'catid',
				'name1',
				'name2',
				'notes',
				'taxnumber',
				'vatin',
				'pinned',
				'cashdiscountpercent',
			],

			'joins' => [
				[
					'type' => 'left',
					'table' => 'address',
					'alias' => 'a',
					'on' => "c.id = a.parentid AND a.module = 'contacts' AND a.controller = 'contact' AND a.type = 'billing' AND a.deleted = 0",
					'columns' => [
						'street',
						'department',
						'postcode',
						'city',
						'country',
						'latitude',
						'longitude',
					],
				],
				[
					'type' => 'left',
					'table' => 'phone',
					'alias' => 'p',
					'on' => "c.id = p.parentid AND p.module = 'contacts' AND p.controller = 'contact' AND p.deleted = 0",
					'columns' => [
						'phones' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT p.phone)'),
					],
				],
				[
					'type' => 'left',
					'table' => 'email',
					'alias' => 'e',
					'on' => "c.id = e.parentid AND e.module = 'contacts' AND e.controller = 'contact' AND e.deleted = 0",
					'columns' => [
						'emails' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT e.email)'),
					],
				],
				[
					'type' => 'left',
					'table' => 'internet',
					'alias' => 'i',
					'on' => "c.id = i.parentid AND i.module = 'contacts' AND i.controller = 'contact' AND i.deleted = 0",
					'columns' => [
						'internets' => new Zend_Db_Expr('GROUP_CONCAT(DISTINCT i.internet)'),
					],
				],
			],

			'search' => [
				'contactid',
				'name1',
				'name2',
				'a.postcode',
				'a.street',
				'a.department',
				'a.city',
				'a.country',
				'p.phone',
				'e.email',
				'i.internet',
			],

			'filters' => [
				'catid' => [
					'type' => 'category',
				],
				'country' => [
					'type' => 'country',
					'alias' => 'a',
					'columns' => ['country'],
				],
			],

			'orders' => [
				'street' => 'a.street',
				'postcode' => 'a.postcode',
				'city' => 'a.city',
				'country' => 'a.country',
			],

			'normalizers' => [
				'phones' => 'csv',
				'emails' => 'csv',
				'internets' => 'csv',
			],
		];
	}
}
