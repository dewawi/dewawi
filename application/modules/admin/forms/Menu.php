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
			'name' => 'shopid',
			'type' => 'select',
			'label' => 'ADMIN_SHOP',
			'options' => [
				'0' => 'ADMIN_SHOP',
			],
			'source' => 'shop',
			'default' => '0',
			'tab' => 'settings',
			'col' => 6,
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
			'name' => 'position',
			'type' => 'text',
			'label' => 'ADMIN_POSITION',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 12,
			],
			'tab' => 'overview',
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
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVATED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'tab' => 'overview',
			'col' => 3,
		]);
	}
}
