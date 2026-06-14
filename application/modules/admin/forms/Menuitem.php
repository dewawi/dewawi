<?php

class Admin_Form_Menuitem extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'ADMIN_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 12,
			],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'menuid',
			'type' => 'select',
			'label' => 'ADMIN_MENU',
			'options' => [
				'0' => 'ADMIN_NO_MENU',
			],
			'source' => 'menu',
			'default' => '0',
			'tab' => 'settings',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'pageid',
			'type' => 'select',
			'label' => 'ADMIN_MENU_PAGE',
			'options' => [
				'0' => 'ADMIN_MENU_NO_PAGE',
			],
			'source' => 'page',
			'default' => '0',
			'tab' => 'settings',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'parentid',
			'type' => 'select',
			'label' => 'ADMIN_PARENT_MENU_ITEM',
			'options' => [
				'0' => 'ADMIN_MAIN_MENU_ITEM',
			],
			'default' => '0',
			'tab' => 'settings',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'ADMIN_LANGUAGE',
			'options' => [],
			'source' => 'language',
			'default' => '',
			'tab' => 'settings',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'clientid',
			'type' => 'select',
			'label' => 'ADMIN_CLIENT',
			'options' => [],
			'default' => '0',
			'tab' => 'settings',
			'col' => 6,
		]);
	}
}
