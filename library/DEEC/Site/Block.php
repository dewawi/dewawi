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
}
