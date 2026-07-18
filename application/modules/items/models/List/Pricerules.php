<?php

class Items_Model_List_Pricerules extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'title',
				'label' => 'PRICE_RULES_TITLE',
				'type' => 'link',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'amount',
				'label' => 'PRICE_RULES_AMOUNT',
				'type' => 'currency',
				'class' => 'dw-col-amount',
			],
			[
				'name' => 'action',
				'label' => 'PRICE_RULES_ACTION',
				'type' => 'callback',
				'class' => 'dw-col-action',
				'callback' => [$this, 'renderAction'],
			],
			[
				'name' => 'datefrom',
				'label' => 'PRICE_RULES_DATE_FROM',
				'type' => 'date',
				'class' => 'dw-col-date-from',
			],
			[
				'name' => 'dateto',
				'label' => 'PRICE_RULES_DATE_TO',
				'type' => 'date',
				'class' => 'dw-col-date-to',
			],
			[
				'name' => 'priority',
				'label' => 'PRICE_RULES_PRIORITY',
				'type' => 'text',
				'class' => 'dw-col-priority',
			],
			[
				'name' => 'itemcatid',
				'label' => 'PRICE_RULES_ITEM_CATEGORY',
				'type' => 'callback',
				'class' => 'dw-col-item-category',
				'callback' => [$this, 'renderItemCategory'],
			],
			[
				'name' => 'contactcatid',
				'label' => 'PRICE_RULES_CONTACT_CATEGORY',
				'type' => 'callback',
				'class' => 'dw-col-contact-category',
				'callback' => [$this, 'renderContactCategory'],
			],
			[
				'name' => 'activated',
				'label' => 'PRICE_RULES_ACTIVATED',
				'type' => 'text',
				'class' => 'dw-col-activated',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					['name' => 'edit'],
					['name' => 'copy'],
					['name' => 'delete'],
				],
			],
		];
	}

	public function renderAction($item): string
	{
		$actions = [
			'bypercent' => 'ITEMS_PRICE_RULES_BY_PERCENT',
			'byfixed' => 'ITEMS_PRICE_RULES_BY_FIXED',
			'topercent' => 'ITEMS_PRICE_RULES_TO_PERCENT',
			'tofixed' => 'ITEMS_PRICE_RULES_TO_FIXED',
		];

		$action = (string)$this->getFieldValue($item, 'action');

		if (!isset($actions[$action])) {
			return '';
		}

		return $this->translate($actions[$action]);
	}

	public function renderItemCategory($item): string
	{
		return $this->renderCategory($item, 'itemcatid', 'categories');
	}

	public function renderContactCategory($item): string
	{
		return $this->renderCategory($item, 'contactcatid', 'contactCategories');
	}

	protected function renderCategory($item, string $field, string $option): string
	{
		$categoryId = (int)$this->getFieldValue($item, $field);
		$categories = (array)$this->getOption($option, []);

		if (!$categoryId || empty($categories[$categoryId]['title'])) {
			return '';
		}

		return $this->escape($categories[$categoryId]['title']);
	}
}

