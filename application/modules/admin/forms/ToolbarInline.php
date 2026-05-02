<?php

class Admin_Form_ToolbarInline extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'button',
			'name' => 'save',
			'attribs' => ['class' => 'save nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'view',
			'attribs' => ['class' => 'view nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'edit',
			'attribs' => ['class' => 'edit nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'copy',
			'attribs' => ['class' => 'copy nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'delete',
			'attribs' => ['class' => 'delete nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'sortup',
			'attribs' => ['class' => 'up nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'sortdown',
			'attribs' => ['class' => 'down nolabel'],
			'wrap' => false,
		]);
	}
}
