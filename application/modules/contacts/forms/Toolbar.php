<?php

class Contacts_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'wrap' => false,
			'attribs' => ['class' => 'add'],
		]);

		$this->addElement([
			'name' => 'addset',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW_SET',
			'wrap' => false,
			'attribs' => ['class' => 'add addSet'],
		]);

		$this->addElement([
			'name' => 'view',
			'type' => 'button',
			'label' => 'TOOLBAR_VIEW',
			'wrap' => false,
			'attribs' => ['class' => 'view'],
		]);

		$this->addElement([
			'name' => 'edit',
			'type' => 'button',
			'label' => 'TOOLBAR_EDIT',
			'wrap' => false,
			'attribs' => ['class' => 'edit hidden-sm'],
		]);

		$this->addElement([
			'name' => 'save',
			'type' => 'button',
			'label' => 'TOOLBAR_SAVE',
			'wrap' => false,
			'attribs' => ['class' => 'save'],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'wrap' => false,
			'attribs' => ['class' => 'copy hidden-sm'],
		]);

		$this->addElement([
			'name' => 'pdf',
			'type' => 'button',
			'label' => 'TOOLBAR_PDF',
			'wrap' => false,
			'attribs' => ['class' => 'pdf'],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'wrap' => false,
			'attribs' => ['class' => 'delete hidden-sm'],
		]);

		$this->addElement([
			'name' => 'keyword',
			'type' => 'text',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'keyword'],
		]);

		$this->addElement([
			'name' => 'clear',
			'type' => 'button',
			'wrap' => false,
			'attribs' => ['class' => 'clear'],
		]);

		$this->addElement([
			'name' => 'reset',
			'type' => 'button',
			'label' => 'TOOLBAR_RESET',
			'wrap' => false,
			'attribs' => ['class' => 'reset hidden-sm'],
		]);

		$this->addElement([
			'name' => 'order',
			'type' => 'select',
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
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'sort',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'asc' => 'ORDERING_ASC',
				'desc' => 'ORDERING_DESC',
			],
			'default' => 'asc',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'country',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm hidden-md'],
		]);

		$this->addElement([
			'name' => 'states',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'state',
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm hidden-md'],
		]);

		$this->addElement([
			'name' => 'controller',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'0' => 'TOOLBAR_ALL',
				'contact' => 'CONTACTS',
				'creditnote' => 'CREDIT_NOTES',
				'deliveryorder' => 'DELIVERY_ORDERS',
				'invoice' => 'INVOICES',
				'quote' => 'QUOTES',
				'reminder' => 'REMINDERS',
				'salesorder' => 'SALES_ORDERS',
				'purchaseorder' => 'PURCHASE_ORDERS',
				'quoterequest' => 'QUOTE_REQUESTS',
			],
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'limit',
			'type' => 'select',
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

		$this->addElement([
			'name' => 'catid',
			'type' => 'select',
			'wrap' => false,
			'options' => ['all' => 'CATEGORIES_ALL'],
			'source' => 'category:contact',
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'tagid',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'0' => 'TAGS_ALL',
			],
			'default' => '0',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'page',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'1' => '1',
			],
			'default' => '1',
		]);
	}
}
