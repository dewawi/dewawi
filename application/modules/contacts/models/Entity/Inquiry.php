<?php

class Contacts_Model_Entity_Inquiry
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Contacts_Model_DbTable_Inquiry',
			'alias' => 'i',
			'module' => 'contacts',
			'controller' => 'inquiry',
			'clientModule' => 'shops',
			'tags' => false,
			'columns' => [
				'id',
				'formid',
				'shopid',
				'token',
				'data',
				'status',
				'clientid',
				'created',
				'modified',
			],
			'joins' => [
				[
					'type' => 'left',
					'table' => 'shop',
					'clientModule' => 'shops',
					'alias' => 's',
					'on' => 'i.shopid = s.id',
					'columns' => [
						'website_title' => 'title',
						'website_url' => 'url',
					],
				],
				[
					'type' => 'left',
					'table' => 'shopinquiryform',
					'clientModule' => 'shops',
					'alias' => 'f',
					'on' => 'i.formid = f.id AND i.shopid = f.shopid',
					'columns' => [
						'form_title' => 'title',
					],
				],
			],
			'search' => [
				'i.data',
				'i.token',
				's.title',
				's.url',
				'f.title',
			],
			'filters' => [
				'shopid' => [
					'type' => 'equals',
					'column' => 'shopid',
				],
				'inquirytype' => [
					'type' => 'callback',
					'callback' => [self::class, 'applyInquiryTypeFilter'],
				],
				'status' => [
					'type' => 'equals',
					'column' => 'status',
				],
			],
			'orders' => [
				'id',
				'created',
				'modified',
				'status',
				'website_title' => 's.title',
			],
			'normalizers' => [
				'data' => 'json',
			],
		];
	}

	public static function applyInquiryTypeFilter($select, $value): void
	{
		if ($value === 'contact') {
			$select->where('i.formid = ?', 0);
			return;
		}

		if ($value === 'technical_inquiry') {
			$select->where('i.formid > ?', 0);
			return;
		}

		$select->where('1 = 0');
	}
}
