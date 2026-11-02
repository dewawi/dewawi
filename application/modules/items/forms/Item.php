<?php

class Items_Form_Item extends DEEC_Form
{
	protected array $shopOptions = [];
	protected array $shopCategoryOptions = [];
	protected int $currentShopId = 0;
	protected int $currentShopCategoryId = 0;

	public function __construct()
	{
		$this->addElement([
			'type' => 'hidden',
			'name' => 'id',
			'tab' => 'overview',
		]);

		$this->addElement([
			'name' => 'parentid',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
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
			'source' => 'currency',
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
			'source' => 'warehouse',
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
			'source' => 'uom',
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
			'source' => 'manufacturer',
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
			'source' => 'deliverytime',
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
			'source' => 'deliverytime',
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
			'type' => 'select',
			'name' => 'shopid',
			'label' => 'ITEMS_SHOP',
			'options' => ['0' => 'ITEMS_SHOP_NONE'],
			'format' => ['type' => 'int'],
			'attribs' => ['data-shop-assignment' => 'shop'],
			'tab' => 'shop',
			'col' => 6,
		]);

		$this->addElement([
			'type' => 'select',
			'name' => 'shopcatid',
			'label' => 'ITEMS_SHOP_CATEGORY',
			'options' => ['0' => 'ITEMS_SHOP_CATEGORY_NONE'],
			'format' => ['type' => 'int'],
			'attribs' => ['data-shop-assignment' => 'category'],
			'tab' => 'shop',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'shopdescription',
			'type' => 'textarea',
			'label' => 'ITEMS_SHOP_DESCRIPTION',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'],
				'allowAttribs' => ['style','title','href','id','class'],
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
				'allowAttribs' => ['style','title','href','id','class'],
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
				'allowAttribs' => ['style','title','href','id','class'],
			],
			'attribs'=> [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'shop',
		]);
	}

	public function configureShopAssignment(array $shopOptions, array $categoryOptions, int $shopId, int $categoryId): self
	{
		$this->shopOptions = $shopOptions;
		$this->shopCategoryOptions = $categoryOptions;
		$this->currentShopId = $shopId;
		$this->currentShopCategoryId = $categoryId;
		$this->addOptions('shopid', $shopOptions, 'replace');
		$this->addOptions('shopcatid', $categoryOptions, 'replace');

		return $this;
	}

	public function isValid(array $data)
	{
		$valid = parent::isValid($data);
		return $this->validateShopAssignment($data, false) && $valid;
	}

	public function isValidPartial(array $data): bool
	{
		$valid = parent::isValidPartial($data);
		return $this->validateShopAssignment($data, true) && $valid;
	}

	protected function validateShopAssignment(array $data, bool $partial): bool
	{
		$hasShop = array_key_exists('shopid', $data);
		$hasCategory = array_key_exists('shopcatid', $data);

		if ($partial && !$hasShop && !$hasCategory) return true;

		$shopValue = $hasShop ? $data['shopid'] : $this->currentShopId;
		$categoryValue = $hasCategory ? $data['shopcatid'] : $this->currentShopCategoryId;
		$shopId = is_scalar($shopValue) ? (int)$shopValue : -1;
		$categoryId = is_scalar($categoryValue) ? (int)$categoryValue : -1;
		$valid = true;

		if (!array_key_exists((string)$shopId, $this->shopOptions)) {
			$this->errors['shopid'][] = 'invalid';
			$valid = false;
		}

		if ($hasShop && !$hasCategory && $shopId !== $this->currentShopId) $categoryId = 0;

		if (($hasCategory && !is_scalar($categoryValue)) || $categoryId < 0
			|| ($categoryId > 0 && ($shopId <= 0 || !array_key_exists((string)$categoryId, $this->shopCategoryOptions)))) {
			$this->errors['shopcatid'][] = 'invalid';
			$valid = false;
		}

		return $valid;
	}

	public function setVariantMode(): self
	{
		foreach(
			Items_Model_Entity_Item::inheritedFields()
			as $field
		) {
			if(!isset($this->elements[$field])) {
				continue;
			}

			$type = (string)$this->elements[$field]['type'];

			if(
				in_array(
					$type,
					[
						'text',
						'email',
						'number',
						'date',
						'textarea',
					],
					true
				)
			) {
				$this->elements[$field]['attribs']['readonly']
					= 'readonly';
			} else {
				$this->elements[$field]['attribs']['disabled']
					= 'disabled';

				$this->elements[$field]['required'] = false;
			}

			$this->elements[$field]['attribs'][
				'data-inherited'
			] = 'true';
		}

		return $this;
	}
}
