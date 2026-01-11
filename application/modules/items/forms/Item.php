<?php

class Items_Form_Item extends DEEC_Form
{
	protected $options = [];

	public function __construct()
	{
		// hidden
		$this->addElement([
			'type' => 'hidden',
			'name' => 'id',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'sku',
			'label' => 'ITEMS_SKU',
			'required' => true,
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 6,
		]);

		// title
		$this->addElement([
			'type' => 'text',
			'name' => 'title',
			'label' => 'ITEMS_TITLE',
			'required' => true,
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 6,
		]);

		// type select
		$this->addElement([
			'type' => 'select',
			'name' => 'type',
			'label' => 'ITEMS_TYPE',
			'required' => true,
			'options' => [
				'stockItem' => 'ITEMS_STOCK_ITEM',
				'deliveryItem' => 'ITEMS_DELIVERY_ITEM',
				'service' => 'ITEMS_SERVICE',
			],
			'attribs' => ['class' => 'required'],
			'format' => ['type' => 'string'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'catid',
			'label' => 'ITEMS_CATEGORY',
			'options'=> ['0' => 'CATEGORIES_MAIN_CATEGORY'],
			'source' => 'category:item',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'cost',
			'type' => 'text',
			'label' => 'ITEMS_COST',
			'attribs'=> ['class' => 'number', 'data-precision' => 2],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'prices',
			'section' => 'ITEMS_PRICES',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'price',
			'type' => 'text',
			'label' => 'ITEMS_PRICE',
			'attribs'=> ['class' => 'number'],
			'format' => [
			  'type' => 'decimal',
			  'precision' => 2,
			],
			'tab' => 'prices',
			'section' => 'ITEMS_PRICES',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'specialprice',
			'type' => 'text',
			'label' => 'ITEMS_SPECIAL_PRICE',
			'attribs'=> ['class' => 'number', 'data-precision' => 2],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'prices',
			'section' => 'ITEMS_PRICES',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'margin',
			'type' => 'text',
			'label' => 'ITEMS_MARGIN',
			'attribs'=> ['class' => 'number', 'data-precision' => 2, 'readonly' => 'readonly'],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'prices',
			'section' => 'ITEMS_PRICES',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'taxid',
			'type' => 'select',
			'label' => 'ITEMS_VAT',
			'required' => true,
			'options' => [
				'0' => 'ITEMS_NONE',
			],
			'source' => 'taxrate',
			'format' => ['type' => 'int'],
			'tab' => 'prices',
			'section' => 'ITEMS_PRICES',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'currency',
			'type' => 'select',
			'label' => 'ITEMS_CURRENCY',
			'required' => true,
			'options' => [
				'0' => 'ITEMS_NONE',
			],
			'format' => ['type' => 'string'],
			'tab' => 'prices',
			'section' => 'ITEMS_PRICES',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'description',
			'type' => 'textarea',
			'label' => 'ITEMS_DESCRIPTION',
			'format' => ['type' => 'string'],
			'attribs'=> [
				'cols' => 40,
				'rows' => 20,
			],
			'tab' => 'overview',
		]);

		$this->addElement([
			'name' => 'info',
			'type' => 'textarea',
			'label' => 'ITEMS_INFO_INTERNAL',
			'info' => 'Interne Informationen werden nicht auf Angeboten, Rechnungen etc. angezeigt.',
			'format' => ['type' => 'string'],
			'attribs'=> [
				'cols' => 75,
				'rows' => 10,
			],
			'tab' => 'overview',
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'quantity',
			'label' => 'ITEMS_QUANTITY',
			'attribs'=> ['class' => 'number', 'data-precision' => 2, 'readonly' => 'readonly'],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'details',
			'section' => 'ITEMS_WAREHOUSE',
			'col' => 6,
		]);

		// inventory (checkbox)
		$this->addElement([
			'name' => 'inventory',
			'type' => 'checkbox',
			'label' => 'ITEMS_INVENTORY_ACTIVATED',
			'format' => ['type' => 'bool'],
			'tab' => 'details',
			'section' => 'ITEMS_WAREHOUSE',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'minquantity',
			'label' => 'ITEMS_MIN_QUANTITY',
			'attribs'=> ['class' => 'number', 'data-precision' => 2],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'details',
			'section' => 'ITEMS_WAREHOUSE',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'orderquantity',
			'label' => 'ITEMS_ORDER_QUANTITY',
			'attribs'=> ['class' => 'number', 'data-precision' => 2],
			'format' => ['type' => 'decimal', 'precision' => 2],
			'tab' => 'details',
			'section' => 'ITEMS_WAREHOUSE',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'quantityreminder',
			'type' => 'checkbox',
			'label' => 'ITEMS_QUANTITY_REMINDER',
			'format' => ['type' => 'bool'],
			'tab' => 'details',
			'section' => 'ITEMS_WAREHOUSE',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'warehouseid',
			'type' => 'select',
			'label' => 'ITEMS_WAREHOUSE',
			'required' => true,
			'format' => ['type' => 'int'],
			'tab' => 'details',
			'section' => 'ITEMS_WAREHOUSE',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'uomid',
			'type' => 'select',
			'label' => 'ITEMS_UOM',
			'required' => true,
			'options' => [
				'0' => 'ITEMS_NONE',
			],
			'format' => ['type' => 'int'],
			'tab' => 'details',
			'section' => 'ITEMS_WAREHOUSE',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'manufacturerid',
			'type' => 'select',
			'label' => 'ITEMS_MANUFACTURER',
			'required' => true,
			'options' => [
				'0' => 'ITEMS_NONE',
			],
			'format' => ['type' => 'int'],
			'tab' => 'details',
			'section' => 'ITEMS_MANUFACTURER',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'manufacturersku',
			'type' => 'text',
			'label' => 'ITEMS_MANUFACTURER_SKU',
			'attribs'=> [],
			'format' => ['type' => 'string'],
			'tab' => 'details',
			'section' => 'ITEMS_MANUFACTURER',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'manufacturergtin',
			'type' => 'text',
			'label' => 'ITEMS_MANUFACTURER_GTIN',
			'attribs'=> [],
			'format' => ['type' => 'string'],
			'tab' => 'details',
			'section' => 'ITEMS_MANUFACTURER',
			'col' => 6,
		]);

		// ctn (text)
		$this->addElement([
			'name' => 'ctn',
			'type' => 'text',
			'label' => 'ITEMS_CTN',
			'attribs'=> [],
			'format' => ['type' => 'string'],
			'tab' => 'details',
			'section' => 'ITEMS_CUSTOMS',
			'col' => 6,
		]);

		// origincountry (text)
		$this->addElement([
			'name' => 'origincountry',
			'type' => 'text',
			'label' => 'ITEMS_ORIGIN_COUNTRY',
			'attribs'=> [],
			'format' => ['type' => 'string'],
			'tab' => 'details',
			'section' => 'ITEMS_CUSTOMS',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'originregion',
			'type' => 'text',
			'label' => 'ITEMS_ORIGIN_REGION',
			'attribs'=> [],
			'format' => ['type' => 'string'],
			'tab' => 'details',
			'section' => 'ITEMS_CUSTOMS',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'gtin',
			'label' => 'ITEMS_GTIN_EAN',
			'format' => ['type' => 'string'],
			'tab' => 'details',
			'section' => 'ITEMS_CUSTOMS',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'deliverytime',
			'type' => 'select',
			'label' => 'ITEMS_DELIVERY_TIME',
			'required' => true,
			'options' => [
				'0' => 'ITEMS_NONE',
			],
			'format' => ['type' => 'int'],
			'tab' => 'details',
			'section' => 'ITEMS_DELIVERY',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'deliverytimeoos',
			'type' => 'select',
			'label' => 'ITEMS_DELIVERY_TIME_OOS',
			'required' => true,
			'options' => [
				'0' => 'ITEMS_NONE',
			],
			'format' => ['type' => 'int'],
			'tab' => 'details',
			'section' => 'ITEMS_DELIVERY',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'video',
			'label' => 'ITEMS_VIDEO',
			'format' => ['type' => 'string'],
			'tab' => 'details',
			'section' => 'ITEMS_OTHER',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'modified',
			'type' => 'text',
			'label' => 'ITEMS_MODIFIED',
			'attribs'=> ['readonly' => 'readonly'],
			'format' => ['type' => 'date'],
			'tab' => 'details',
			'section' => 'ITEMS_OTHER',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'created',
			'type' => 'text',
			'label' => 'ITEMS_CREATED',
			'attribs'=> ['readonly' => 'readonly'],
			'format' => ['type' => 'date'],
			'tab' => 'details',
			'section' => 'ITEMS_OTHER',
			'col' => 6,
		]);

		// width/length/height/weight (text, number-like)
		foreach (['width'=>'ITEMS_WIDTH','length'=>'ITEMS_LENGTH','height'=>'ITEMS_HEIGHT','weight'=>'ITEMS_WEIGHT'] as $n => $lbl) {
			$this->addElement([
				'name' => $n,
				'type' => 'text',
				'label' => $lbl,
				'attribs'=> ['class' => 'number', 'data-precision' => 2],
				'format' => ['type' => 'decimal', 'precision' => 2],
				'tab' => 'details',
				'section' => 'ITEMS_DIMENSIONS_AND_WEIGHT',
				'col' => 6,
			]);
		}

		// packwidth/packlength/packheight/packweight (text, number-like)
		foreach (['packwidth'=>'ITEMS_WIDTH','packlength'=>'ITEMS_LENGTH','packheight'=>'ITEMS_HEIGHT','packweight'=>'ITEMS_WEIGHT'] as $n => $lbl) {
			$this->addElement([
				'name' => $n,
				'type' => 'text',
				'label' => $lbl,
				'attribs'=> ['class' => 'number', 'data-precision' => 2],
				'format' => ['type' => 'decimal', 'precision' => 2],
				'tab' => 'details',
				'section' => 'ITEMS_PACKAGING_DIMENSIONS_AND_WEIGHT',
				'col' => 6,
			]);
		}

		$this->addElement([
			'type' => 'hidden',
			'name' => 'shopid',
			'format' => ['type' => 'int'],
			'tab' => 'shop',
		]);

		$this->addElement([
			'name' => 'shopdescription',
			'type' => 'textarea',
			'label' => 'ITEMS_SHOP_DESCRIPTION',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'],
				'allowAttribs' => ['style','title','href'],
			],
			'attribs'=> [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'shop',
		]);

		$this->addElement([
			'name' => 'shopdescriptionshort',
			'type' => 'textarea',
			'label' => 'ITEMS_SHOP_DESCRIPTION_SHORT',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'],
				'allowAttribs' => ['style','title','href'],
			],
			'attribs'=> [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'shop',
		]);

		$this->addElement([
			'name' => 'shopdescriptionmini',
			'type' => 'textarea',
			'label' => 'ITEMS_SHOP_DESCRIPTION_MINI',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'],
				'allowAttribs' => ['style','title','href'],
			],
			'attribs'=> [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'shop',
		]);
	}
}
