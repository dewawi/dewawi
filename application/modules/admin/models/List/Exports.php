<?php

class Admin_Model_List_Exports extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'ADMIN_ID',
				'type' => 'callback',
				'class' => 'dw-col-id',
				'callback' => function ($item, $column, $list) {
					return (string)((int)$list->getFieldValue($item, 'id'));
				},
			],
			[
				'name' => 'name',
				'label' => 'ADMIN_FILE_NAME',
				'type' => 'callback',
				'callback' => function ($item, $column, $list) {
					$name = (string)$list->getFieldValue($item, 'name');
					$url = (string)$list->getFieldValue($item, 'url');

					return '<a href="/files/export/'
						. $list->escapeAttr($url)
						. '/'
						. $list->escapeAttr($name)
						. '" download="'
						. $list->escapeAttr($name)
						. '">'
						. $list->escape($name)
						. '</a>';
				},
			],
			[
				'name' => 'created_datetime',
				'label' => 'ADMIN_FILE_CREATED',
				'type' => 'text',
			],
			[
				'name' => 'size',
				'label' => 'ADMIN_FILE_SIZE',
				'type' => 'text',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					['name' => 'delete'],
				],
			],
		];
	}
}
