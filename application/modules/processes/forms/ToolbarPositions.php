<?php

class Processes_Form_ToolbarPositions extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add-position',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'add'],
		]);

		$this->addElement([
			'name' => 'select-position',
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
			'name' => 'copy-selected-position',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'copy'],
		]);

		$this->addElement([
			'name' => 'delete-selected-position',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'delete'],
		]);

		$this->addElement([
			'name' => 'add-position-set',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW_SET',
			'toolbar' => 'positionsets',
			'wrap' => false,
			'attribs' => ['class' => 'add'],
		]);

		$this->addElement([
			'name' => 'copy-position-set',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY_SET',
			'toolbar' => 'positionsets',
			'wrap' => false,
			'attribs' => ['class' => 'copy'],
		]);

		$this->addElement([
			'name' => 'delete-position-set',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE_SET',
			'toolbar' => 'positionsets',
			'wrap' => false,
			'attribs' => ['class' => 'delete'],
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
