<?php

class Items_Form_Ledger extends DEEC_Form
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
			'name' => 'itemid',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'sku',
			'type' => 'text',
			'label' => 'ITEMS_SKU',
			'required' => true,
			'attribs' => [
				'class' => 'required',
				'autocomplete' => 'off',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_ITEM',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'warehouseid',
			'type' => 'select',
			'label' => 'ITEMS_WAREHOUSE',
			'required' => true,
			'source' => 'warehouse',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'label' => 'ITEMS_LEDGER_TYPE',
			'required' => true,
			'options' => [
				'inflow' => 'ITEMS_LEDGER_INFLOW',
				'outflow' => 'ITEMS_LEDGER_OUTFLOW',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'reason',
			'type' => 'select',
			'label' => 'ITEMS_LEDGER_REASON',
			'required' => true,
			'options' => [
				'receipt' => 'ITEMS_LEDGER_REASON_RECEIPT',
				'delivery' => 'ITEMS_LEDGER_REASON_DELIVERY',
				'returncustomer' => 'ITEMS_LEDGER_REASON_RETURN_CUSTOMER',
				'returnsupplier' => 'ITEMS_LEDGER_REASON_RETURN_SUPPLIER',
				'transfer' => 'ITEMS_LEDGER_REASON_TRANSFER',
				'inventory' => 'ITEMS_LEDGER_REASON_INVENTORY',
				'damage' => 'ITEMS_LEDGER_REASON_DAMAGE',
				'loss' => 'ITEMS_LEDGER_REASON_LOSS',
				'scrap' => 'ITEMS_LEDGER_REASON_SCRAP',
				'correction' => 'ITEMS_LEDGER_REASON_CORRECTION',
				'initial' => 'ITEMS_LEDGER_REASON_INITIAL',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'quantity',
			'type' => 'text',
			'label' => 'ITEMS_LEDGER_QUANTITY',
			'required' => true,
			'attribs' => [
				'class' => 'number required',
				'data-precision' => 4,
			],
			'format' => [
				'type' => 'decimal',
				'precision' => 4,
			],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'ledgerdate',
			'type' => 'text',
			'label' => 'ITEMS_LEDGER_DATE',
			'required' => true,
			'attribs' => [
				'class' => 'datePicker required',
			],
			'format' => ['type' => 'datetime'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'comment',
			'type' => 'text',
			'label' => 'ITEMS_LEDGER_COMMENT',
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_LEDGER_BOOKING',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'referencemodule',
			'type' => 'hidden',
			'format' => ['type' => 'string'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'referencetype',
			'type' => 'hidden',
			'format' => ['type' => 'string'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'referenceid',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'referencepositionid',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'reversalid',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'created',
			'type' => 'text',
			'label' => 'ITEMS_CREATED',
			'attribs' => [
				'readonly' => 'readonly',
			],
			'format' => ['type' => 'datetime'],
			'tab' => 'details',
			'section' => 'ITEMS_OTHER',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'modified',
			'type' => 'text',
			'label' => 'ITEMS_MODIFIED',
			'attribs' => [
				'readonly' => 'readonly',
			],
			'format' => ['type' => 'datetime'],
			'tab' => 'details',
			'section' => 'ITEMS_OTHER',
			'col' => 6,
		]);
	}
}
