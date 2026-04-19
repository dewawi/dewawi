<?php

class Admin_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'save',
			'type' => 'button',
			'label' => 'TOOLBAR_SAVE',
			'wrap' => false,
			'attribs' => [
				'class' => 'save',
			],
		]);

		$this->addElement([
			'name' => 'cancel',
			'type' => 'button',
			'label' => 'TOOLBAR_CANCEL',
			'wrap' => false,
			'attribs' => [
				'class' => 'cancel',
			],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'wrap' => false,
			'attribs' => [
				'class' => 'copy',
			],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'wrap' => false,
			'attribs' => [
				'class' => 'delete',
			],
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
			'attribs' => [
				'style' => 'display: none;',
			],
		]);

		$this->addElement([
			'name' => 'shopid',
			'type' => 'select',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'options' => [],
			'default' => '',
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'sortup',
			'type' => 'button',
			'label' => '',
			'wrap' => false,
			'attribs' => [
				'class' => 'up nolabel',
			],
		]);

		$this->addElement([
			'name' => 'sortdown',
			'type' => 'button',
			'label' => '',
			'wrap' => false,
			'attribs' => [
				'class' => 'down nolabel',
			],
		]);
	}
}
