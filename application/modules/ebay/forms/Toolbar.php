<?php

class Ebay_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'button',
			'name' => 'add',
			'label' => 'TOOLBAR_NEW',
			'wrap' => false,
			'attribs' => ['class' => 'add'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'edit',
			'label' => 'TOOLBAR_EDIT',
			'wrap' => false,
			'attribs' => ['class' => 'edit hidden-sm'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'save',
			'label' => 'TOOLBAR_SAVE',
			'wrap' => false,
			'attribs' => ['class' => 'save'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'copy',
			'label' => 'TOOLBAR_COPY',
			'wrap' => false,
			'attribs' => ['class' => 'copy hidden-sm'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'delete',
			'label' => 'TOOLBAR_DELETE',
			'wrap' => false,
			'attribs' => ['class' => 'delete hidden-sm'],
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'keyword',
			'default' => '',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'keyword'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'clear',
			'wrap' => false,
			'attribs' => [
				'class' => 'clear nolabel',
				'rel' => 'keyword',
			],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'reset',
			'label' => 'TOOLBAR_RESET',
			'wrap' => false,
			'attribs' => ['class' => 'reset hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'order',
			'wrap' => false,
			'options' => [
				'id' => 'ORDERING_CREATION',
				'sku' => 'ORDERING_SKU',
				'price' => 'ORDERING_PRICE',
				'cost' => 'ORDERING_COST',
				'margin' => 'ORDERING_MARGIN',
				'quantity' => 'ORDERING_QUANTITY',
				'catid' => 'ORDERING_CATEGORY',
				'modified' => 'ORDERING_MODIFIED',
			],
			'default' => 'id',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'sort',
			'wrap' => false,
			'options' => [
				'asc' => 'ORDERING_ASC',
				'desc' => 'ORDERING_DESC',
			],
			'default' => 'asc',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'limit',
			'wrap' => false,
			'options' => [
				'50' => '50',
				'100' => '100',
				'250' => '250',
				'500' => '500',
				'0' => 'TOOLBAR_ALL',
			],
			'default' => '50',
			'attribs' => ['class' => 'hidden-sm'],
		]);
	}
}
