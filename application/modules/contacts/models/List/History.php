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
				'class' => 'dw-col-id',
				'empty_hide' => true,
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
				'fallback_field' => 'id',
				'url' => [
					'module_field' => 'module',
					'controller_field' => 'controller',
					'action' => 'edit',
					'id_field' => 'id',
				],
			],
			[
				'name' => 'notes',
				'type' => 'editable_note',
				'label' => 'CONTACTS_NOTES',
				'field' => 'notes',
				'empty_label' => 'TOOLBAR_NEW',
			],
			[
				'name' => 'date',
				'type' => 'date',
				'label' => 'CONTACTS_DOCUMENT_DATE',
				'field' => 'document_date',
				'format' => 'd.m.Y',
			],
			[
				'name' => 'total',
				'label' => 'CONTACTS_DOCUMENT_TOTAL',
				'type' => 'currency',
				'secondary_field' => 'subtotal',
			],
			[
				'name' => 'state',
				'type' => 'state_badge',
				'label' => 'CONTACTS_DOCUMENT_STATE',
				'field' => 'state',
				'option_key' => 'states',
				'class' => 'dw-col-state state',
				'editable' => function () {
					return false;
				},
				'state_map' => [
					'100' => 'created',
					'101' => 'in-process',
					'102' => 'check',
					'103' => 'delete',
					'104' => 'released',
					'105' => 'completed',
					'106' => 'cancelled',
				],
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
					[
						'name' => 'cancel',
						'show' => function ($item, $element, $list) {
							return $list->isCancellable($item)
								&& $list->getController() !== 'process';
						},
					],
					['name' => 'delete'],
					['name' => 'pdf'],
				],
			],
		];
	}
}
