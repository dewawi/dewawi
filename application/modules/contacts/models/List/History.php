<?php

class Contacts_Model_List_History extends DEEC_List
{
	protected function buildColumns(): array
	{
		return [
			[
				'name' => 'document_id',
				'type' => 'link',
				'label' => 'CONTACTS_DOCUMENT_ID',
				'field' => 'document_number',
				'url' => [
					'module_field' => 'module',
					'controller_field' => 'controller',
					'action' => 'edit',
					'id_field' => 'id',
				],
			],
			[
				'name' => 'title',
				'type' => 'link',
				'label' => 'CONTACTS_DOCUMENT_TITLE',
				'field' => 'title',
				'url' => [
					'module_field' => 'module',
					'controller_field' => 'controller',
					'action' => 'edit',
					'id_field' => 'id',
				],
			],
			[
				'name' => 'date',
				'type' => 'date',
				'label' => 'CONTACTS_DOCUMENT_DATE',
				'field' => 'document_date',
			],
			[
				'name' => 'notes',
				'type' => 'editable_note',
				'label' => 'CONTACTS_NOTES',
				'field' => 'notes',
			],
			[
				'name' => 'modified',
				'type' => 'date',
				'label' => 'CONTACTS_DOCUMENT_MODIFIED',
				'field' => 'modified',
			],
			[
				'name' => 'total',
				'type' => 'callback',
				'label' => 'CONTACTS_DOCUMENT_TOTAL',
				'callback' => function ($row, $column, $list) {
					return $list->escape($row->total) . '<br>(' . $list->escape($row->subtotal) . ')';
				},
			],
			[
				'name' => 'state',
				'type' => 'state_badge',
				'label' => 'CONTACTS_DOCUMENT_STATE',
				'field' => 'state',
				'option_key' => 'states',
				'editable' => function () {
					return false;
				},
			],
			[
				'name' => 'actions',
				'type' => 'actions',
				'label' => '',
				'module_field' => 'module',
				'controller_field' => 'controller',
				'id_field' => 'id',
				'elements' => [
					[
						'name' => 'view',
						'show' => function ($item, $element, $list) {
							return $list->isReadonly($item);
						},
					],
					[
						'name' => 'edit',
						'show' => function ($item, $element, $list) {
							return !$list->isReadonly($item);
						},
					],
					['name' => 'copy'],
					['name' => 'pdf'],
					['name' => 'delete'],
				],
			],
		];
	}
}
