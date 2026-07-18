<?php

class Items_Form_Pricerule extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'hidden',
			'name' => 'id',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'title',
			'label' => 'PRICE_RULES_TITLE',
			'required' => true,
			'attribs' => [
				'class' => 'required',
				'size' => 40,
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'action',
			'label' => 'PRICE_RULES_ACTION',
			'required' => true,
			'options' => [
				'0' => 'POSITIONS_NONE',
				'bypercent' => 'ITEMS_PRICE_RULES_BY_PERCENT',
				'byfixed' => 'ITEMS_PRICE_RULES_BY_FIXED',
				'topercent' => 'ITEMS_PRICE_RULES_TO_PERCENT',
				'tofixed' => 'ITEMS_PRICE_RULES_TO_FIXED',
			],
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 6,
		]);

		foreach ([
			'amount' => 'PRICE_RULES_AMOUNT',
			'amountmin' => 'PRICE_RULES_AMOUNT_MINIMUM',
			'amountmax' => 'PRICE_RULES_AMOUNT_MAXIMUM',
		] as $name => $label) {
			$this->addElement([
				'type' => 'text',
				'name' => $name,
				'label' => $label,
				'attribs' => [
					'class' => 'number',
					'data-precision' => 2,
				],
				'format' => ['type' => 'decimal', 'precision' => 2],
				'tab' => 'overview',
				'section' => 'PRICE_RULES_VALUE',
				'col' => 4,
			]);
		}

		$this->addElement([
			'type' => 'text',
			'name' => 'datefrom',
			'label' => 'PRICE_RULES_DATE_FROM',
			'attribs' => ['class' => 'datePicker'],
			'format' => ['type' => 'date'],
			'tab' => 'overview',
			'section' => 'PRICE_RULES_VALIDITY',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'dateto',
			'label' => 'PRICE_RULES_DATE_TO',
			'attribs' => ['class' => 'datePicker'],
			'format' => ['type' => 'date'],
			'tab' => 'overview',
			'section' => 'PRICE_RULES_VALIDITY',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'priority',
			'label' => 'PRICE_RULES_PRIORITY',
			'attribs' => ['class' => 'number', 'data-precision' => 0],
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'section' => 'PRICE_RULES_CONTROL',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'subsequent',
			'label' => 'PRICE_RULES_DISCARD_SUBSEQUENT_RULES',
			'format' => ['type' => 'bool'],
			'tab' => 'overview',
			'section' => 'PRICE_RULES_CONTROL',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'itemcatid',
			'label' => 'PRICE_RULES_ITEM_CATEGORY',
			'required' => true,
			'options' => ['0' => 'PRICE_RULES_ITEMS_ALL_CATEGORIES'],
			'source' => 'category:item',
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'int'],
			'tab' => 'conditions',
			'section' => 'PRICE_RULES_ITEMS',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'itemsubcat',
			'label' => 'PRICE_RULES_ITEM_APPLY_TO_SUBCATEGORIES',
			'format' => ['type' => 'bool'],
			'tab' => 'conditions',
			'section' => 'PRICE_RULES_ITEMS',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'itemtype',
			'label' => 'PRICE_RULES_ITEM_TYPE',
			'required' => true,
			'options' => [
				'0' => 'PRICE_RULES_ITEMS_ALL_TYPES',
				'stockItem' => 'ITEMS_STOCK_ITEM',
				'deliveryItem' => 'ITEMS_DELIVERY_ITEM',
				'service' => 'ITEMS_SERVICE',
			],
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
			'tab' => 'conditions',
			'section' => 'PRICE_RULES_ITEMS',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'itemmanufacturer',
			'label' => 'PRICE_RULES_ITEM_MANUFACTURER',
			'required' => true,
			'options' => ['0' => 'PRICE_RULES_ITEMS_ALL_MANUFACTURERS'],
			'source' => 'manufacturer',
			'format' => ['type' => 'int'],
			'tab' => 'conditions',
			'section' => 'PRICE_RULES_ITEMS',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'contactcatid',
			'label' => 'PRICE_RULES_CONTACT_CATEGORY',
			'required' => true,
			'options' => ['0' => 'PRICE_RULES_ITEMS_ALL_CATEGORIES'],
			'source' => 'category:contact',
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'int'],
			'tab' => 'conditions',
			'section' => 'PRICE_RULES_CONTACTS',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'contactsubcat',
			'label' => 'PRICE_RULES_CONTACT_APPLY_TO_SUBCATEGORIES',
			'format' => ['type' => 'bool'],
			'tab' => 'conditions',
			'section' => 'PRICE_RULES_CONTACTS',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'pricefrom',
			'label' => 'PRICE_RULES_PRICE_FROM',
			'attribs' => [
				'class' => 'number',
				'data-precision' => 2,
			],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'conditions',
			'section' => 'PRICE_RULES_PRICE_RANGE',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'priceto',
			'label' => 'PRICE_RULES_PRICE_TO',
			'attribs' => [
				'class' => 'number',
				'data-precision' => 2,
			],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'conditions',
			'section' => 'PRICE_RULES_PRICE_RANGE',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'checkbox',
			'name' => 'activated',
			'label' => 'PRICE_RULES_ACTIVATED',
			'format' => ['type' => 'bool'],
			'tab' => 'details',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'modified',
			'label' => 'ITEMS_MODIFIED',
			'attribs' => ['readonly' => 'readonly'],
			'format' => ['type' => 'date'],
			'tab' => 'details',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'created',
			'label' => 'ITEMS_CREATED',
			'attribs' => ['readonly' => 'readonly'],
			'format' => ['type' => 'date'],
			'tab' => 'details',
			'col' => 6,
		]);
	}
}

