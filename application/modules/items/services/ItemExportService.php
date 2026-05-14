<?php

class Items_Service_ItemExportService
{
	public function export(string $filePath, $items, array $context = []): array
	{
		$options = $context['options'] ?? [];

		$rows = [];

		$rows[] = [
			'id',
			'sku',
			'gtin',
			'title',
			'description',
			'quantity',
			'price',
			'currency',
			'taxid',
			'manufacturersku',
			'manufacturergtin',
			'length',
			'width',
			'height',
			'weight',
			'category',
			'tags',
		];

		$tagEntityDb = new Application_Model_DbTable_Tagentity();

		foreach ($items as $item) {

			$category = '';

			if (
				isset($options['categories'][$item->catid]['title'])
			) {
				$category = $options['categories'][$item->catid]['title'];
			}

			$tags = [];

			$tagRows = $tagEntityDb->getTagEntities(
				(int)$item->id,
				'items',
				'item'
			);

			foreach ($tagRows as $tag) {
				$tags[] = $tag['tag'];
			}

			$rows[] = [
				$item->id,
				$item->sku,
				$item->gtin,
				$item->title,
				$item->description,
				$item->quantity,
				$item->price,
				$item->currency,
				$item->taxid,
				$item->manufacturersku,
				$item->manufacturergtin,
				$item->length,
				$item->width,
				$item->height,
				$item->weight,
				$category,
				implode(', ', $tags),
			];
		}

		$file = 'items-' . date('Ymd-His') . '.csv';

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
}
