<?php

class Items_Model_List_Inventories extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'ITEMS_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
			],
			[
				'name' => 'sku',
				'label' => 'ITEMS_SKU',
				'type' => 'link',
				'class' => 'dw-col-sku',
			],
			[
				'name' => 'title',
				'label' => 'ITEMS_TITLE',
				'type' => 'link',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'price',
				'label' => 'ITEMS_PRICE',
				'type' => 'currency',
			],
			[
				'name' => 'currency',
				'label' => 'ITEMS_CURRENCY',
				'type' => 'text',
				'class' => 'dw-col-currency',
			],
			[
				'name' => 'tags',
				'label' => 'ITEMS_TAGS',
				'type' => 'callback',
				'callback' => [$this, 'renderTags'],
			],
			[
				'name' => 'pin',
				'label' => '',
				'type' => 'pin',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					[
						'name' => 'apply',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') === 'select';
						},
					],
					[
						'name' => 'view',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') !== 'select';
						},
					],
					[
						'name' => 'edit',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') !== 'select';
						},
					],
					[
						'name' => 'copy',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') !== 'select';
						},
					],
					[
						'name' => 'delete',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') !== 'select';
						},
					],
				],
			],
		];
	}

	public function renderTags($item): string
	{
		$tagEntities = (array)$this->getOption('tagEntities', []);
		$id = (int)$this->getFieldValue($item, 'id');

		if (empty($tagEntities[$id])) {
			return '';
		}

		$html = [];

		foreach ($tagEntities[$id] as $tag) {
			$label = is_array($tag) ? ($tag['tag'] ?? '') : (string)$tag;

			if ($label === '') {
				continue;
			}

			$html[] = '<span class="dw-badge dw-badge--info">'
				. $this->escape($label)
				. '</span>';
		}

		return implode(' ', $html);
	}
}
