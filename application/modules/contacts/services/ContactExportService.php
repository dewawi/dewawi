<?php

class Contacts_Service_ContactExportService
{
	public function export(string $filePath, $contacts, array $context = []): array
	{
		$options = $context['options'] ?? [];

		$rows = [];

		$rows[] = [
			'id',
			'name1',
			'name2',
			'department',
			'street',
			'postcode',
			'city',
			'country',
			'taxnumber',
			'vatin',
			'phone',
			'email',
			'contactperson',
			'internet',
			'category',
			'tags',
		];

		$tagEntityDb = new Application_Model_DbTable_Tagentity();

		foreach ($contacts as $contact) {

			$category = '';

			if (
				isset($options['categories'][$contact->catid]['title'])
			) {
				$category = $options['categories'][$contact->catid]['title'];
			}

			$tags = [];

			$tagRows = $tagEntityDb->getTagEntities(
				(int)$contact->id,
				'contacts',
				'contact'
			);

			foreach ($tagRows as $tag) {
				$tags[] = $tag['tag'];
			}

			$rows[] = [
				$contact->id,
				$contact->name1,
				$contact->name2,
				$contact->department,
				$contact->street,
				$contact->postcode,
				$contact->city,
				$contact->country,
				$contact->taxnumber,
				$contact->vatin,
				$this->implodeValue($contact->phones),
				$this->implodeValue($contact->emails),
				'',
				$this->implodeValue($contact->internets),
				$category,
				implode(', ', $tags),
			];
		}

		$file = 'contacts-' . date('Ymd-His') . '.csv';

		$this->writeCsv(
			$filePath . $file,
			$rows
		);

		return [
			'name' => $file,
			'path' => $filePath . $file,
		];
	}

	protected function writeCsv(string $file, array $rows): void
	{
		$handle = fopen($file, 'w');

		foreach ($rows as $row) {
			fputcsv($handle, $row, ';');
		}

		fclose($handle);
	}

	protected function implodeValue($value): string
	{
		if (empty($value)) {
			return '';
		}

		if (is_array($value)) {
			return implode(', ', $value);
		}

		return (string)$value;
	}
}
