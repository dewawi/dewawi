<?php

class Contacts_Model_List_Contacts extends DEEC_List
{
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
				'label' => 'CONTACTS_NAME',
				'type' => 'link',
				'class' => 'dw-col-title',
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
				'name' => 'roles',
				'label' => 'CONTACTS_ROLES',
				'type' => 'callback',
				'callback' => [$this, 'renderRoles'],
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

	public function renderRoles($contact): string
	{
		$contactRoles = (array)$this->getOption('contactRoles', []);
		$id = (int)$this->getFieldValue($contact, 'id');
		$html = [];

		foreach ($contactRoles[$id] ?? [] as $role) {
			$label = Contacts_Model_DbTable_Contactrole::ROLE_LABELS[$role] ?? null;

			if ($label !== null) {
				$html[] = '<span class="dw-badge">'.$this->escape($this->translate($label)).'</span>';
			}
		}

		return implode(' ', $html);
	}
}
