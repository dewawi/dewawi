<?php

class Admin_Model_List_Menuitems extends DEEC_List
{
	public function __construct()
	{
		$this->init();
	}

	public function init(): void
	{
		$this->setId('menuitems');

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
				'label' => 'ADMIN_MENU_ITEM_ID',
				'data_label' => 'ADMIN_MENU_ITEM_ID',
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
				'name' => 'subtitle',
				'label' => 'ADMIN_SUBTITLE',
				'data_label' => 'ADMIN_SUBTITLE',
				'type' => 'link',
				'field' => 'subtitle',
				'fallback_field' => 'id',
				'url' => [
					'action' => 'edit',
					'id_field' => 'id',
				],
				'class' => 'dw-col-subtitle',
			],
			[
				'name' => 'parentid',
				'label' => 'ADMIN_MAIN_MENU_ITEM',
				'data_label' => 'ADMIN_MAIN_MENU_ITEM',
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
						'show' => function ($menuitem) {
							return $this->hasPermission('admin');
						},
					],
					[
						'name' => 'copy',
						'show' => function ($menuitem) {
							return $this->hasPermission('admin');
						},
					],
					[
						'name' => 'delete',
						'show' => function ($menuitem) {
							return $this->hasPermission('admin');
						},
					],
					['name' => 'view'],
				],
			],
		];
	}
}
