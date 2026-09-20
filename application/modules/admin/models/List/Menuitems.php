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
				'type' => 'callback',
				'callback' => [$this, 'renderTitle'],
				'class' => 'dw-col-title',
			],
			[
				'name' => 'variant',
				'label' => 'ADMIN_MENU_VARIANT',
				'type' => 'text',
				'class' => 'dw-col-variant',
			],
			[
				'name' => 'parentid',
				'label' => 'ADMIN_MAIN_MENU_ITEM',
				'data_label' => 'ADMIN_MAIN_MENU_ITEM',
				'type' => 'text',
				'field' => 'parentid',
				'fallback_field' => 'id',
				'class' => 'dw-col-parentid',
			],
			[
				'name' => 'activated',
				'label' => 'ADMIN_ACTIVATED',
				'type' => 'checkbox',
				'class' => 'dw-col-activated',
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
					[
						'name' => 'sortup',
						'show' => function ($menuitem) {
							return $this->hasPermission('admin');
						},
					],
					[
						'name' => 'sortdown',
						'show' => function ($menuitem) {
							return $this->hasPermission('admin');
						},
					],
				],
			],
		];
	}

	public function renderTitle($item): string
	{
		$id = (int)$this->getFieldValue($item, 'id');
		$depth = (int)$this->getFieldValue($item, 'depth', 0);
		$title = str_repeat('— ', $depth).(string)$this->getFieldValue($item, 'title');

		$editUrl = $this->buildUrl($item, [
			'action' => 'edit',
			'id_field' => 'id',
		]);

		$addUrl = $this->getView()->url([
			'module' => 'admin',
			'controller' => 'menuitem',
			'action' => 'add',
			'menuid' => (int)$this->getContext('menuid'),
			'parentid' => $id,
		], null, true);

		return '<a href="'.$this->escapeAttr($editUrl).'">'.$this->escape($title).'</a>'
			.' <a class="dw-menuitem-add-child" href="'.$this->escapeAttr($addUrl).'">+ '.$this->escape($this->translate('ADMIN_ADD_SUBMENUITEM')).'</a>';
	}
}
