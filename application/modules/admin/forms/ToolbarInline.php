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
			'name' => 'delete',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => [
				'class' => 'delete nolabel',
				'data-action' => 'media-delete',
			],
		]);

		$this->addElement([
			'name' => 'sortup',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => [
				'class' => 'up nolabel',
				'data-ordering' => 'up',
			],
		]);

		$this->addElement([
			'name' => 'sortdown',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => [
				'class' => 'down nolabel',
				'data-ordering' => 'down',
			],
		]);
	}
}
