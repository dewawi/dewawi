<?php

class Items_Form_Ledger extends DEEC_Form
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
			'type' => 'hidden',
			'name' => 'itemid',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'sku',
			'label' => 'ITEMS_SKU',
			'required' => true,
			'attribs' => [
				'size' => 20,
				'class' => 'required',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_ITEM',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'title',
			'label' => 'ITEMS_TITLE',
			'attribs' => [
				'size' => 40,
				'readonly' => 'readonly',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_ITEM',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'type',
			'label' => 'ITEMS_LEDGER_TYPE',
			'required' => true,
			'options' => [
				'inflow' => 'ITEMS_LEDGER_INFLOW',
				'outflow' => 'ITEMS_LEDGER_OUTFLOW',
			],
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'quantity',
			'label' => 'ITEMS_LEDGER_QUANTITY',
			'required' => true,
			'attribs' => [
				'size' => 10,
				'class' => 'number required',
				'data-precision' => 2,
			],
			'format' => [
				'type' => 'decimal',
				'precision' => 2,
			],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'warehouseid',
			'label' => 'ITEMS_WAREHOUSE',
			'required' => true,
			'source' => 'warehouse',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'ledgerdate',
			'label' => 'ITEMS_LEDGER_DATE',
			'attribs' => [
				'class' => 'datePicker',
				'size' => 9,
			],
			'format' => ['type' => 'date'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'comment',
			'label' => 'ITEMS_LEDGER_COMMENT',
			'attribs' => ['size' => 40],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 12,
		]);

		$this->addElement([
			'type' => 'hidden',
			'name' => 'docid',
			'format' => ['type' => 'int'],
			'tab' => 'details',
		]);

		$this->addElement([
			'type' => 'hidden',
			'name' => 'doctype',
			'format' => ['type' => 'string'],
			'tab' => 'details',
		]);

		$this->addElement([
			'type' => 'hidden',
			'name' => 'language',
			'format' => ['type' => 'string'],
			'tab' => 'details',
		]);

		$this->addElement([
			'name' => 'modified',
			'type' => 'text',
			'label' => 'ITEMS_MODIFIED',
			'attribs' => ['readonly' => 'readonly'],
			'format' => ['type' => 'date'],
			'tab' => 'details',
			'section' => 'ITEMS_OTHER',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'created',
			'type' => 'text',
			'label' => 'ITEMS_CREATED',
			'attribs' => ['readonly' => 'readonly'],
			'format' => ['type' => 'date'],
			'tab' => 'details',
			'section' => 'ITEMS_OTHER',
			'col' => 6,
		]);
	}
}
