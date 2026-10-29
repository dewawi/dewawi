<?php

class Contacts_Model_List_Inquiries extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'created',
				'label' => 'CONTACT_INQUIRIES_DATE',
				'type' => 'callback',
				'callback' => [$this, 'renderCreated'],
				'export_callback' => [$this, 'exportCreated'],
			],
			[
				'name' => 'website_title',
				'label' => 'CONTACT_INQUIRIES_WEBSITE',
				'type' => 'link',
				'fallback_field' => 'website_url',
				'url' => ['action' => 'view'],
			],
			[
				'name' => 'name',
				'label' => 'CONTACT_INQUIRIES_NAME_COMPANY',
				'type' => 'callback',
				'callback' => [$this, 'renderName'],
				'export_callback' => [$this, 'exportName'],
			],
			[
				'name' => 'email',
				'label' => 'CONTACT_INQUIRIES_EMAIL',
				'type' => 'callback',
				'callback' => [$this, 'renderEmail'],
				'export_callback' => [$this, 'exportEmail'],
			],
			[
				'name' => 'subject',
				'label' => 'CONTACT_INQUIRIES_SUBJECT',
				'type' => 'callback',
				'callback' => [$this, 'renderSubject'],
				'export_callback' => [$this, 'exportSubject'],
			],
			[
				'name' => 'origin',
				'label' => 'CONTACT_INQUIRIES_ORIGIN',
				'type' => 'callback',
				'callback' => [$this, 'renderOrigin'],
				'export_callback' => [$this, 'exportOrigin'],
			],
			[
				'name' => 'status',
				'label' => 'CONTACT_INQUIRIES_STATUS',
				'type' => 'state_badge',
				'option_key' => 'status',
				'state_map' => [
					'new' => 'warning',
					'in_progress' => 'info',
					'completed' => 'success',
				],
				'editable' => static fn() => false,
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					['name' => 'view'],
					[
						'name' => 'edit',
						'show' => function ($item, $element, $list) {
							$view = $list->getView();
							return !empty($view->permissions['edit']);
						},
					],
				],
			],
		];
	}

	public function renderCreated($item): string
	{
		$url = $this->buildUrl($item, ['action' => 'view']);

		return '<a href="'.$this->escapeAttr($url).'">'.$this->escape($this->exportCreated($item)).'</a>';
	}

	public function exportCreated($item): string
	{
		$value = (string)$this->getFieldValue($item, 'created', '');
		$timestamp = strtotime($value);
		return $timestamp ? date('d.m.Y H:i', $timestamp) : $value;
	}

	public function renderName($item): string
	{
		return $this->escape($this->exportName($item));
	}

	public function exportName($item): string
	{
		$data = $this->getData($item);

		foreach (['company', 'firma', 'name1', 'name'] as $field) {
			$value = trim((string)($data[$field] ?? ''));
			if ($value !== '') return $value;
		}

		$name = trim(implode(' ', array_filter([
			(string)($data['firstname'] ?? $data['vorname'] ?? ''),
			(string)($data['lastname'] ?? $data['nachname'] ?? ''),
		])));

		return $name;
	}

	public function renderEmail($item): string
	{
		return $this->escape($this->exportEmail($item));
	}

	public function exportEmail($item): string
	{
		return (string)($this->getData($item)['email'] ?? '');
	}

	public function renderSubject($item): string
	{
		return $this->escape($this->exportSubject($item));
	}

	public function exportSubject($item): string
	{
		$data = $this->getData($item);
		$subject = trim((string)($data['subject'] ?? ''));

		if ($subject === '') $subject = (string)$this->getFieldValue($item, 'form_title', '');
		if ($subject === '') $subject = $this->translate('CONTACT_INQUIRIES_TYPE_CONTACT');

		return $subject;
	}

	public function renderOrigin($item): string
	{
		return $this->escape($this->exportOrigin($item));
	}

	public function exportOrigin($item): string
	{
		$data = $this->getData($item);
		$path = trim((string)($data['source_path'] ?? ''));

		return $path !== '' ? $path : '/';
	}

	private function getData($item): array
	{
		$data = $this->getFieldValue($item, 'data', []);
		return is_array($data) ? $data : [];
	}
}
