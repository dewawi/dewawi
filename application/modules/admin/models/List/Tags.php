<?php

class Admin_Model_List_Tags extends DEEC_List
{
	protected function buildColumns()
	{
		$columns = [
			[
				'name' => 'id',
				'label' => 'ADMIN_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'ADMIN_TITLE',
				'type' => 'link',
				'class' => 'dw-col-title',
				'fallback_field' => 'id',
			],
		];

		$columns[] = [
			'name' => 'slug',
			'label' => 'ADMIN_SLUG',
			'type' => 'callback',
			'class' => 'dw-col-slug',
			'callback' => function ($tag, $column, $list) {
				$slugs = $list->getContext('slugs', []);
				$id = (int)$list->getFieldValue($tag, 'id');

				return $list->escape($slugs[$id] ?? '');
			},
		];

		$columns[] = [
			'name' => 'image',
			'label' => 'ADMIN_CATEGORY_IMAGE',
			'type' => 'text',
			'class' => 'dw-col-image',
		];

		$columns[] = [
			'name' => 'parentid',
			'label' => 'ADMIN_PARENT_CATEGORY',
			'type' => 'text',
			'class' => 'dw-col-parentid',
		];

		$columns[] = [
			'name' => 'ordering',
			'label' => 'ADMIN_ORDERING',
			'type' => 'text',
			'class' => 'dw-col-ordering',
		];

		$columns[] = [
			'name' => 'actions',
			'label' => '',
			'type' => 'actions',
			'class' => 'dw-col-actions',
			'elements' => [
				['name' => 'copy'],
				['name' => 'delete'],
				['name' => 'sortup'],
				['name' => 'sortdown'],
			],
		];

		return $columns;
	}
}
