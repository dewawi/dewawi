<?php

class Admin_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'cancel',
			'type' => 'button',
			'label' => 'TOOLBAR_CANCEL',
			'wrap' => false,
			'attribs' => ['class' => 'cancel'],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'wrap' => false,
			'attribs' => ['class' => 'copy'],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'wrap' => false,
			'attribs' => ['class' => 'delete'],
		]);

		$this->addElement([
			'name' => 'clientid',
			'type' => 'select',
			'options' => [],
			'default' => '0',
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'parentid',
			'type' => 'select',
			'options' => [
				'0' => 'ADMIN_MAIN_CATEGORY',
			],
			'default' => '0',
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'options' => [
				'contact' => 'CONTACTS',
				'item' => 'ITEMS',
				'shop' => 'SHOPS',
			],
			'wrap' => false,
			'attribs' => ['class' => 'hidden'],
		]);

		$this->addElement([
			'name' => 'shopid',
			'type' => 'select',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'source' => 'shop',
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'menuid',
			'type' => 'select',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'source' => 'menu',
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'options' => [],
			//'source' => 'language',
			'default' => '',
			'wrap' => false,
		]);
	}
}
