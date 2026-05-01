<?php

class Contacts_Model_List_Contacts extends DEEC_List
{
	public function __construct()
	{
		$this->init();
	}

	public function init(): void
	{
		$this->setId('contacts');

		$this->setRowClassCallback(function ($item) {
			return !empty($item->pinned) ? 'is-pinned' : '';
		});

		$this->setColumns($this->buildColumns());
	}

	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'CONTACTS_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
			],
			[
				'name' => 'contactid',
				'label' => 'CONTACTS_CONTACT_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
			],
			[
				'name' => 'name1',
				'label' => 'CONTACTS_NAME1',
				'type' => 'link',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'name2',
				'label' => 'CONTACTS_NAME2',
			],
			[
				'name' => 'department',
				'label' => 'CONTACTS_DEPARTMENT',
			],
			[
				'name' => 'address',
				'label' => 'CONTACTS_ADDRESS',
				'type' => 'address',
				'fields' => [
					'street',
					'postcode',
					'city',
				],
			],
			[
				'name' => 'phones',
				'label' => 'CONTACTS_PHONE',
			],
			[
				'name' => 'emails',
				'label' => 'CONTACTS_EMAIL',
			],
			[
				'name' => 'tags',
				'label' => 'CONTACTS_TAGS',
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
					['name' => 'view'],
					['name' => 'edit'],
					['name' => 'copy'],
					['name' => 'delete'],
				],
			],
		];
	}

	public function renderTags($contact): string
	{
		$tagEntities = (array)$this->getOption('tagEntities', []);
		$id = (int)$this->getFieldValue($contact, 'id');

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
