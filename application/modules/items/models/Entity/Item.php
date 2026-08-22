<?php

class Items_Model_Entity_Item
{
	private const INHERITED_FIELDS = [
		'title',
		'type',
		'catid',
		'description',
		'info',
		'taxid',
		'currency',
		'uomid',
		'manufacturerid',
		'ctn',
		'origincountry',
		'originregion',
		'deliverytime',
		'deliverytimeoos',
		'video',
		'shopid',
		'shopdescription',
		'shopdescriptionshort',
		'shopdescriptionmini',
	];

	private const VARIANT_FIELDS = [
		'sku',
		'gtin',
		'manufacturersku',
		'manufacturergtin',
		'cost',
		'price',
		'specialprice',
		'margin',
		'quantity',
		'inventory',
		'minquantity',
		'orderquantity',
		'quantityreminder',
		'warehouseid',
		'width',
		'length',
		'height',
		'weight',
		'packwidth',
		'packlength',
		'packheight',
		'packweight',
	];

	public static function inheritedFields(): array
	{
		return self::INHERITED_FIELDS;
	}

	public static function variantFields(): array
	{
		return self::VARIANT_FIELDS;
	}

	public static function isInheritedField(string $field): bool
	{
		return in_array(
			$field,
			self::INHERITED_FIELDS,
			true
		);
	}

	public static function isVariantField(string $field): bool
	{
		return in_array(
			$field,
			self::VARIANT_FIELDS,
			true
		);
	}

	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Items_Model_DbTable_Item',
			'alias' => 'i',

			'pinned' => true,

			'search' => [
				'title',
				'sku',
				'manufacturersku',
				'description',
			],

			'filters' => [
				'catid' => [
					'type' => 'category',
				],
				'quantity' => [
					'type' => 'quantity',
					'column' => 'quantity',
				],
			],

			'orders' => [
				'title',
				'sku',
				'manufacturersku',
				'created',
				'modified',
			],

			'normalizers' => [
				'description' => [
					'type' => 'truncate',
					'length' => 43,
				],
			],
		];
	}
}
