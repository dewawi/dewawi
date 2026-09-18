<?php

class DEEC_Site_Block
{
	public static function getDefinitions(): array
	{
		return [
			'hero' => [
				'label' => 'ADMIN_PAGEBLOCK_HERO',
				'fields' => [
					[
						'name' => 'title',
						'type' => 'text',
						'label' => 'ADMIN_TITLE',
						'format' => ['type' => 'string'],
						'col' => 12,
					],
					[
						'name' => 'text',
						'type' => 'textarea',
						'label' => 'ADMIN_TEXT',
						'format' => ['type' => 'string'],
						'attribs' => ['rows' => 6],
						'col' => 12,
					],
					[
						'name' => 'image',
						'type' => 'text',
						'label' => 'ADMIN_CATEGORY_IMAGE',
						'format' => ['type' => 'string'],
						'attribs' => [
							'data-filemanager' => 'image',
						],
						'col' => 12,
					],
					[
						'name' => 'buttonlabel',
						'type' => 'text',
						'label' => 'ADMIN_PAGEBLOCK_BUTTON_TEXT',
						'format' => ['type' => 'string'],
						'col' => 6,
					],
					[
						'name' => 'buttonurl',
						'type' => 'text',
						'label' => 'ADMIN_URL',
						'format' => ['type' => 'string'],
						'col' => 6,
					],
				],
			],
			'text' => [
				'label' => 'ADMIN_PAGEBLOCK_TEXT',
				'fields' => [
					[
						'name' => 'title',
						'type' => 'text',
						'label' => 'ADMIN_TITLE',
						'format' => ['type' => 'string'],
						'col' => 12,
					],
					[
						'name' => 'text',
						'type' => 'textarea',
						'label' => 'ADMIN_TEXT',
						'format' => [
							'type' => 'html',
							'allowTags' => ['a', 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5'],
							'allowAttribs' => ['href', 'title'],
						],
						'attribs' => [
							'rows' => 12,
							'class' => 'editor',
						],
						'col' => 12,
					],
				],
			],
			'image_text' => [
				'label' => 'ADMIN_PAGEBLOCK_IMAGE_TEXT',
				'fields' => [
					[
						'name' => 'title',
						'type' => 'text',
						'label' => 'ADMIN_TITLE',
						'format' => ['type' => 'string'],
						'col' => 12,
					],
					[
						'name' => 'text',
						'type' => 'textarea',
						'label' => 'ADMIN_TEXT',
						'format' => [
							'type' => 'html',
							'allowTags' => ['a', 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5'],
							'allowAttribs' => ['href', 'title'],
						],
						'attribs' => [
							'rows' => 10,
							'class' => 'editor',
						],
						'col' => 12,
					],
					[
						'name' => 'image',
						'type' => 'text',
						'label' => 'ADMIN_CATEGORY_IMAGE',
						'format' => ['type' => 'string'],
						'attribs' => [
							'data-filemanager' => 'image',
						],
						'col' => 12,
					],
					[
						'name' => 'imagealt',
						'type' => 'text',
						'label' => 'ADMIN_PAGEBLOCK_IMAGE_ALT',
						'format' => ['type' => 'string'],
						'col' => 6,
					],
					[
						'name' => 'imageposition',
						'type' => 'select',
						'label' => 'ADMIN_PAGEBLOCK_IMAGE_POSITION',
						'options' => [
							'left' => 'ADMIN_PAGEBLOCK_IMAGE_LEFT',
							'right' => 'ADMIN_PAGEBLOCK_IMAGE_RIGHT',
						],
						'default' => 'left',
						'format' => ['type' => 'string'],
						'col' => 6,
					],
				],
			],
		];
	}

	public static function getDefinition(string $type): array
	{
		$definitions = self::getDefinitions();

		if (!isset($definitions[$type])) {
			throw new InvalidArgumentException('Unknown site block type: ' . $type);
		}

		return $definitions[$type];
	}

	public static function getFields(string $type): array
	{
		return self::getDefinition($type)['fields'];
	}

	public static function getTypeOptions(): array
	{
		$options = [];

		foreach (self::getDefinitions() as $type => $definition) {
			$options[$type] = $definition['label'];
		}

		return $options;
	}

	public static function getFormatSchema(string $type): array
	{
		$schema = [];

		foreach (self::getFields($type) as $field) {
			if (!empty($field['format'])) {
				$schema[$field['name']] = $field['format'];
			}
		}

		return $schema;
	}
}
