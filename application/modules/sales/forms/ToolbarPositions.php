<?php

class Sales_Form_ToolbarPositions extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'search-position',
			'type' => 'text',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => [
				'class' => 'autocomplete autocomplete--position',
				'placeholder' => 'Artikelnummer oder Bezeichnung',
				'autocomplete' => 'off',
				'data-autocomplete-source' => '/items/item/suggest',
				'data-autocomplete-apply' => 'position',
				'data-autocomplete-min-length' => 2,
				'data-autocomplete-skip-autosave' => 1,
			],
		]);

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
			'name' => 'delete-position',
			'type' => 'button',
			'toolbar' => 'position',
			'wrap' => false,
			'attribs' => ['class' => 'delete nolabel'],
		]);

		$this->addElement([
			'name' => 'copy-position',
			'type' => 'button',
			'toolbar' => 'position',
			'wrap' => false,
			'attribs' => ['class' => 'copy nolabel'],
		]);

		$this->addElement([
			'name' => 'sort-position-up',
			'type' => 'button',
			'toolbar' => 'position',
			'wrap' => false,
			'attribs' => ['class' => 'up nolabel'],
		]);

		$this->addElement([
			'name' => 'sort-position-down',
			'type' => 'button',
			'toolbar' => 'position',
			'wrap' => false,
			'attribs' => ['class' => 'down nolabel'],
		]);

		$this->addElement([
			'name' => 'add-option',
			'type' => 'button',
			'toolbar' => 'position-option',
			'wrap' => false,
			'attribs' => ['class' => 'add nolabel'],
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
			'name' => 'sort-position-set-up',
			'type' => 'button',
			'toolbar' => 'positionsets',
			'wrap' => false,
			'attribs' => ['class' => 'up nolabel'],
		]);

		$this->addElement([
			'name' => 'sort-position-set-down',
			'type' => 'button',
			'toolbar' => 'positionsets',
			'wrap' => false,
			'attribs' => ['class' => 'down nolabel'],
		]);
	}
}
