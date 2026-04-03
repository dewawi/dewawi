<?php

class Purchases_Form_ToolbarPositions extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'wrap' => false,
			'attribs' => [
				'class' => 'addPosition add',
			],
		]);

		$this->addElement([
			'name' => 'addset',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW_SET',
			'wrap' => false,
			'attribs' => [
				'class' => 'addSet add',
			],
		]);

		$this->addElement([
			'name' => 'copyset',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY_SET',
			'wrap' => false,
			'attribs' => [
				'class' => 'copySet copy',
			],
		]);

		$this->addElement([
			'name' => 'deleteset',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE_SET',
			'wrap' => false,
			'attribs' => [
				'class' => 'deleteSet delete',
			],
		]);

		$this->addElement([
			'name' => 'select',
			'type' => 'button',
			'label' => 'TOOLBAR_SELECT',
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
			'wrap' => false,
			'attribs' => [
				'class' => 'copyPosition copy',
			],
		]);

		$this->addElement([
			'name' => 'copypos',
			'type' => 'button',
			'label' => '',
			'wrap' => false,
			'attribs' => [
				'class' => 'copyPosition copy nolabel',
			],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'wrap' => false,
			'attribs' => [
				'class' => 'deletePosition delete',
			],
		]);

		$this->addElement([
			'name' => 'deletepos',
			'type' => 'button',
			'label' => '',
			'wrap' => false,
			'attribs' => [
				'class' => 'deletePosition delete nolabel',
			],
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
