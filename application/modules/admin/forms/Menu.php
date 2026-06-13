<?php

class Admin_Form_Menu extends DEEC_Form
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
			'name' => 'shopid',
			'type' => 'select',
			'label' => 'ADMIN_MAIN_CATEGORY',
			'options' => [
				'0' => 'ADMIN_MAIN_CATEGORY',
			],
			'default' => '0',
			'tab' => 'settings',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'label' => 'ADMIN_TYPE',
			'options' => [
				'contact' => 'CONTACT',
				'item' => 'ITEM',
			],
			'default' => 'contact',
			'tab' => 'settings',
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'ADMIN_LANGUAGE',
			'options' => [],
			'default' => '',
			'tab' => 'settings',
			'col' => 3,
		]);
	}
}
