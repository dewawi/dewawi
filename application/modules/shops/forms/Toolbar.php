<?php

class Shops_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'button',
			'name' => 'add',
			'label' => 'TOOLBAR_NEW',
			'attribs'=> ['class' => 'add'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'edit',
			'label' => 'TOOLBAR_EDIT',
			'attribs'=> ['class' => 'edit hidden-sm'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'save',
			'label' => 'TOOLBAR_SAVE',
			'attribs'=> ['class' => 'save'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'copy',
			'label' => 'TOOLBAR_COPY',
			'attribs'=> ['class' => 'copy hidden-sm'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'delete',
			'label' => 'TOOLBAR_DELETE',
			'attribs'=> ['class' => 'delete hidden-sm'],
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'sku',
			'label' => 'ITEMS_SKU',
			'required' => true,
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'keyword',
			'format' => ['type' => 'string'],
			'attribs'=> ['class' => 'keyword'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'clear',
			'attribs'=> ['class' => 'clear'],
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'reset',
			'label' => 'TOOLBAR_RESET',
			'attribs'=> ['class' => 'reset hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'catid',
			'label' => 'ITEMS_CATEGORY',
			'options'=> ['all' => 'CATEGORIES_ALL'],
			'source' => 'category:item',
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'order',
			'label' => 'TOOLBAR_ORDERING',
			'options'=> [
				'id' => 'ORDERING_CREATION',
				'sku' => 'ORDERING_SKU',
				'price' => 'ORDERING_PRICE',
				'cost' => 'ORDERING_COST',
				'margin' => 'ORDERING_MARGIN',
				'quantity' => 'ORDERING_QUANTITY',
				'catid' => 'ORDERING_CATEGORY',
				'modified' => 'ORDERING_MODIFIED',
			],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'sort',
			'label' => 'TOOLBAR_ORDERING',
			'options'=> [
				'asc' => 'ORDERING_ASC',
				'desc' => 'ORDERING_DESC',
			],
			'default' => 'asc',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'limit',
			'options'=> [
				'50' => '50',
				'100' => '100',
				'250' => '250',
				'500' => '500',
				'0' => 'TOOLBAR_ALL',
			],
			'default' => '50',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'manufacturerid',
			'options'=> [
				'0' => 'TOOLBAR_ALL',
			],
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'tagid',
			'options'=> [
				'0' => 'TAGS_ALL',
			],
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'page',
			'options'=> [
				'1' => '1',
			],
			'default' => '1',
		]);
	}
}
