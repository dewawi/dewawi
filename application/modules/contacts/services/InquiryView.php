<?php

class Contacts_Service_InquiryView
{
	private const LABELS = [
		'name' => 'CONTACT_INQUIRIES_NAME',
		'company' => 'CONTACT_INQUIRIES_COMPANY',
		'firma' => 'CONTACT_INQUIRIES_COMPANY',
		'name1' => 'CONTACT_INQUIRIES_COMPANY',
		'firstname' => 'CONTACT_INQUIRIES_FIRSTNAME',
		'vorname' => 'CONTACT_INQUIRIES_FIRSTNAME',
		'lastname' => 'CONTACT_INQUIRIES_LASTNAME',
		'nachname' => 'CONTACT_INQUIRIES_LASTNAME',
		'email' => 'CONTACT_INQUIRIES_EMAIL',
		'phone' => 'CONTACT_INQUIRIES_PHONE',
		'subject' => 'CONTACT_INQUIRIES_SUBJECT',
		'message' => 'CONTACT_INQUIRIES_MESSAGE',
		'privacy' => 'CONTACT_INQUIRIES_PRIVACY',
	];

	public function build(array $row): array
	{
		$data = json_decode((string)($row['data'] ?? ''), true);
		$data = is_array($data) ? $data : [];
		$definitions = $this->getDefinitions((string)($row['form_fields'] ?? ''));
		$fields = [];

		foreach ($data as $name => $value) {
			if (in_array($name, ['origin', 'source_path'], true)) continue;

			$definition = $definitions[$name] ?? [];
			$formatted = $this->formatValue($value, $definition);
			$fields[] = [
				'name' => (string)$name,
				'label' => (string)($definition['label'] ?? self::LABELS[$name] ?? $this->humanize((string)$name)),
				'value' => $formatted['value'],
				'translate_value' => $formatted['translate'],
			];
		}

		$row['data'] = $data;
		$row['fields'] = $fields;
		$row['origin'] = (string)($data['origin'] ?? ((int)($row['formid'] ?? 0) > 0 ? 'technical_inquiry' : 'contact'));
		$row['source_path'] = (string)($data['source_path'] ?? '');
		$row['inquiry_type'] = (int)($row['formid'] ?? 0) > 0
			? ((string)($row['form_title'] ?? '') ?: 'CONTACT_INQUIRIES_TYPE_TECHNICAL')
			: 'CONTACT_INQUIRIES_TYPE_CONTACT';
		$row['translate_inquiry_type'] = (int)($row['formid'] ?? 0) === 0 || (string)($row['form_title'] ?? '') === '';

		return $row;
	}

	private function getDefinitions(string $json): array
	{
		$config = json_decode($json, true);

		if (!is_array($config)) return [];

		$definitions = [];

		foreach ($config as $fields) {
			$this->collectDefinitions(is_array($fields) ? $fields : [], $definitions);
		}

		return $definitions;
	}

	private function collectDefinitions(array $fields, array &$definitions): void
	{
		foreach ($fields as $field) {
			if (!is_array($field) || empty($field['name'])) continue;

			$definitions[(string)$field['name']] = $field;

			foreach ((array)($field['depends'] ?? []) as $dependentFields) {
				$this->collectDefinitions(is_array($dependentFields) ? $dependentFields : [], $definitions);
			}
		}
	}

	private function formatValue($value, array $definition): array
	{
		if (is_array($value)) {
			$value = implode(', ', array_map(static function ($item): string {
				if (is_scalar($item) || $item === null) return (string)$item;
				$json = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				return $json === false ? '' : $json;
			}, $value));
		}

		$value = (string)$value;
		$options = is_array($definition['options'] ?? null) ? $definition['options'] : [];

		return isset($options[$value])
			? ['value' => (string)$options[$value], 'translate' => true]
			: ['value' => $value, 'translate' => false];
	}

	private function humanize(string $name): string
	{
		return ucfirst(str_replace(['_', '-'], ' ', $name));
	}
}
