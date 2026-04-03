<?php

class Sales_Form_Reminderpos extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'sku',
			'type' => 'text',
			'label' => 'POSITIONS_SKU',
			'required' => true,
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'POSITIONS_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 9,
		]);

		$this->addElement([
			'name' => 'image',
			'type' => 'text',
			'label' => 'POSITIONS_IMAGE',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'description',
			'type' => 'textarea',
			'label' => 'POSITIONS_DESCRIPTION',
			'format' => ['type' => 'string'],
			'attribs' => [
				'rows' => 3,
				'cols' => 75,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'price',
			'type' => 'text',
			'label' => 'POSITIONS_PRICE',
			'required' => true,
			'format' => ['type' => 'float'],
			'attribs' => [
				'class' => 'number',
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'taxrate',
			'type' => 'select',
			'label' => 'POSITIONS_TAX_RATE',
			'required' => true,
			'options' => [
				'0' => 'POSITIONS_NONE',
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'quantity',
			'type' => 'text',
			'label' => 'POSITIONS_QUANTITY',
			'required' => true,
			'format' => ['type' => 'float'],
			'attribs' => [
				'class' => 'number',
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'uom',
			'type' => 'select',
			'label' => 'POSITIONS_UOM',
			'required' => true,
			'options' => [
				'0' => 'POSITIONS_NONE',
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'total',
			'type' => 'text',
			'format' => ['type' => 'float'],
			'attribs' => [
				'class' => 'number',
				'readonly' => 'readonly',
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'priceruleamount',
			'type' => 'text',
			'label' => 'POSITIONS_PRICE_RULE_AMOUNT',
			'required' => true,
			'format' => ['type' => 'float'],
			'attribs' => [
				'class' => 'number',
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'priceruleaction',
			'type' => 'select',
			'label' => 'POSITIONS_PRICE_RULE_APPLY',
			'required' => true,
			'options' => [
				'0' => 'POSITIONS_NONE',
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'pricerulemaster',
			'type' => 'checkbox',
			'label' => 'POSITIONS_PRICE_RULE_MASTER',
			'format' => ['type' => 'int'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'ordering',
			'type' => 'select',
			'required' => true,
			'options' => [],
			'col' => 3,
		]);
	}
}
