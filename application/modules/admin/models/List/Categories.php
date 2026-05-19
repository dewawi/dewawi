<?php

class Admin_Model_List_Categories extends DEEC_List
{
	public function __construct()
	{
		$this->init();
	}

	public function init(): void
	{
		$this->setId('categories');

		$this->setRowClassCallback(function ($item) {
			return !empty($item->pinned) ? 'is-pinned' : '';
		});

		$this->setColumns($this->buildColumns());
	}

	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'ADMIN_CATEGORY_ID',
				'data_label' => 'ADMIN_CATEGORY_ID',
				'type' => 'link',
				'field' => 'id',
				'url' => [
					'action' => 'edit',
					'id_field' => 'id',
				],
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'ADMIN_TITLE',
				'data_label' => 'ADMIN_TITLE',
				'type' => 'link',
				'field' => 'title',
				'fallback_field' => 'id',
				'url' => [
					'action' => 'edit',
					'id_field' => 'id',
				],
				'class' => 'dw-col-title',
			],
			[
				'name' => 'parentid',
				'label' => 'ADMIN_MAIN_CATEGORY',
				'data_label' => 'ADMIN_MAIN_CATEGORY',
				'type' => 'text',
				'field' => 'subtitle',
				'fallback_field' => 'id',
				'class' => 'dw-col-parentid',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'class' => 'dw-col-actions',
				'elements' => [
					[
						'name' => 'edit',
						'show' => function ($category) {
							return $this->hasPermission('admin');
						},
					],
					[
						'name' => 'copy',
						'show' => function ($category) {
							return $this->hasPermission('admin');
						},
					],
					[
						'name' => 'delete',
						'show' => function ($category) {
							return $this->hasPermission('admin');
						},
					],
					['name' => 'view'],
				],
			],
		];
	}
}
