<?php

class Sales_Form_ToolbarPositions extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'addPosition add'],
		]);

		$this->addElement([
			'name' => 'addset',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW_SET',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'addSet add'],
		]);

		$this->addElement([
			'name' => 'copyset',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY_SET',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'copySet copy'],
		]);

		$this->addElement([
			'name' => 'deleteset',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE_SET',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'deleteSet delete'],
		]);

		$this->addElement([
			'name' => 'select',
			'type' => 'button',
			'label' => 'TOOLBAR_SELECT',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => [
				'class' => 'select poplight',
				'rel' => 'selectPosition',
			],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'copyPosition copy'],
		]);

		$this->addElement([
			'name' => 'copypos',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'copyPosition copy nolabel'],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'deletePosition delete'],
		]);

		$this->addElement([
			'name' => 'deletepos',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'deletePosition delete nolabel'],
		]);

		$this->addElement([
			'name' => 'sortup',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'up nolabel'],
		]);

		$this->addElement([
			'name' => 'sortdown',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'down nolabel'],
		]);
	}
}
