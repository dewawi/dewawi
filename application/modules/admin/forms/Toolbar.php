<?php

class Admin_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'add'],
		]);

		$this->addElement([
			'name' => 'edit',
			'type' => 'button',
			'label' => 'TOOLBAR_EDIT',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'edit'],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'copy'],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'delete'],
		]);

		$this->addElement([
			'name' => 'parentid',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_MAIN_CATEGORY',
			],
			'source' => 'category',
			'filter' => true,
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_SELECT',
				'contact' => 'CONTACTS',
				'item' => 'ITEMS',
			],
			'filter' => true,
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'shopid',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'source' => 'shop',
			'filter' => true,
			'toolbar' => 'slide',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'menuid',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'source' => 'menu',
			'filter' => true,
			'toolbar' => 'menuitem',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'source' => 'language',
			'filter' => true,
			'toolbar' => 'language',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);
	}
}
