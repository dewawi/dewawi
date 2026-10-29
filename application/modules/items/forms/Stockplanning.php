<?php

class Items_Form_Stockplanning extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement(['name' => 'id', 'type' => 'hidden', 'format' => ['type' => 'int'], 'wrap' => false]);
		$this->addElement(['name' => 'itemid', 'type' => 'hidden', 'format' => ['type' => 'int'], 'wrap' => false]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'label' => 'ITEMS_PLANNING_TYPE',
			'required' => true,
			'options' => [
				'incoming' => 'ITEMS_PLANNING_INCOMING',
				'reservation' => 'ITEMS_PLANNING_RESERVATION',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_PLANNING_DETAILS',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'sku',
			'type' => 'text',
			'label' => 'ITEMS_SKU',
			'attribs' => [
				'class' => 'autocomplete',
				'autocomplete' => 'off',
				'data-autocomplete-source' => '/items/item/suggest',
				'data-autocomplete-value' => 'sku',
				'data-autocomplete-target' => 'itemid',
				'data-autocomplete-target-value' => 'id',
				'data-autocomplete-min-length' => 2,
				'data-autocomplete-skip-autosave' => 1,
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_PLANNING_DETAILS',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'warehouseid',
			'type' => 'select',
			'label' => 'ITEMS_WAREHOUSE',
			'source' => 'warehouse',
			'required' => true,
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'section' => 'ITEMS_PLANNING_DETAILS',
			'col' => 6,
		]);

		foreach(['quantity' => 'ITEMS_PLANNING_QUANTITY', 'fulfilled' => 'ITEMS_PLANNING_FULFILLED'] as $name => $label) {
			$this->addElement([
				'name' => $name,
				'type' => 'text',
				'label' => $label,
				'attribs' => array_filter([
					'class' => 'number',
					'data-precision' => 4,
					'readonly' => $name === 'fulfilled' ? 'readonly' : null,
				]),
				'format' => ['type' => 'decimal', 'precision' => 4],
				'tab' => 'overview',
				'section' => 'ITEMS_PLANNING_QUANTITIES',
				'col' => 6,
			]);
		}

		$this->addElement([
			'name' => 'planningdate',
			'type' => 'text',
			'label' => 'ITEMS_PLANNING_DATE',
			'attribs' => ['class' => 'datePicker'],
			'format' => ['type' => 'date'],
			'tab' => 'overview',
			'section' => 'ITEMS_PLANNING_DETAILS',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'dateconfirmed',
			'type' => 'checkbox',
			'label' => 'ITEMS_PLANNING_DATE_CONFIRMED',
			'format' => ['type' => 'bool'],
			'tab' => 'overview',
			'section' => 'ITEMS_PLANNING_DETAILS',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'status',
			'type' => 'select',
			'label' => 'ITEMS_PLANNING_STATUS',
			'options' => [
				'draft' => 'ITEMS_PLANNING_STATUS_DRAFT',
				'planned' => 'ITEMS_PLANNING_STATUS_PLANNED',
				'completed' => 'ITEMS_PLANNING_STATUS_COMPLETED',
				'cancelled' => 'ITEMS_PLANNING_STATUS_CANCELLED',
				'released' => 'ITEMS_PLANNING_STATUS_RELEASED',
			],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'section' => 'ITEMS_PLANNING_DETAILS',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'allowoverbooking',
			'type' => 'checkbox',
			'label' => 'ITEMS_PLANNING_ALLOW_OVERBOOKING',
			'format' => ['type' => 'bool'],
			'tab' => 'overview',
			'section' => 'ITEMS_PLANNING_DETAILS',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'contactid',
			'type' => 'text',
			'label' => 'ITEMS_PLANNING_CONTACT',
			'attribs' => [
				'class' => 'autocomplete',
				'data-autocomplete-source' => '/contacts/contact/suggest',
				'data-autocomplete-value' => 'id',
				'data-autocomplete-min-length' => 2,
			],
			'format' => ['type' => 'int'],
			'tab' => 'reference',
			'section' => 'ITEMS_PLANNING_ORIGIN',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'referencemodule',
			'type' => 'select',
			'label' => 'ITEMS_PLANNING_REFERENCE_MODULE',
			'options' => ['' => 'ITEMS_PLANNING_NONE', 'purchases' => 'ITEMS_PLANNING_PURCHASE', 'sales' => 'ITEMS_PLANNING_SALE'],
			'format' => ['type' => 'string'],
			'tab' => 'reference',
			'section' => 'ITEMS_PLANNING_ORIGIN',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'referencetype',
			'type' => 'select',
			'label' => 'ITEMS_PLANNING_REFERENCE_TYPE',
			'options' => ['' => 'ITEMS_PLANNING_NONE', 'purchaseorder' => 'ITEMS_PLANNING_PURCHASE_ORDER', 'salesorder' => 'ITEMS_PLANNING_SALES_ORDER'],
			'format' => ['type' => 'string'],
			'tab' => 'reference',
			'section' => 'ITEMS_PLANNING_ORIGIN',
			'col' => 6,
		]);

		foreach([
			'referenceid' => 'ITEMS_PLANNING_REFERENCE_ID',
			'referencepositionid' => 'ITEMS_PLANNING_REFERENCE_POSITION_ID',
			'linkedplanningid' => 'ITEMS_PLANNING_LINKED_INCOMING',
		] as $name => $label) {
			$this->addElement([
				'name' => $name,
				'type' => 'text',
				'label' => $label,
				'format' => ['type' => 'int'],
				'tab' => 'reference',
				'section' => 'ITEMS_PLANNING_ORIGIN',
				'col' => 6,
			]);
		}

		$this->addElement([
			'name' => 'externalnumber',
			'type' => 'text',
			'label' => 'ITEMS_PLANNING_EXTERNAL_NUMBER',
			'format' => ['type' => 'string'],
			'tab' => 'reference',
			'section' => 'ITEMS_PLANNING_ORIGIN',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'comment',
			'type' => 'textarea',
			'label' => 'ITEMS_PLANNING_COMMENT',
			'attribs' => ['rows' => 4],
			'format' => ['type' => 'string'],
			'tab' => 'reference',
			'section' => 'ITEMS_PLANNING_ORIGIN',
			'col' => 12,
		]);
	}
}
