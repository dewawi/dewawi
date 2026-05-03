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
				'type' => 'callback',
				'label' => '',
				'callback' => function ($row, $column, $list) {
					$id = (int)$row->id;
					$module = $list->escapeAttr($row->module);
					$controller = $list->escapeAttr($row->controller);

					return '<div class="dw-row-actions">'
						. '<button type="button" class="view nolabel" data-action="view" data-id="'.$id.'" data-module="'.$module.'" data-controller="'.$controller.'"></button>'
						. '<button type="button" class="copy nolabel" data-action="copy" data-id="'.$id.'" data-module="'.$module.'" data-controller="'.$controller.'"></button>'
						. '<button type="button" class="pdf nolabel" data-action="pdf" data-id="'.$id.'" data-module="'.$module.'" data-controller="'.$controller.'"></button>'
						. '</div>';
				},
			],
		];
	}
}
