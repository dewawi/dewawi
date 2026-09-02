<?php

class Items_Service_StockExportService
{
	public function export(
		string $filePath,
		$items,
		array $context = []
	): array {
		$rows = [];

		$rows[] = [
			'SKU',
			'Title',
			'Warehouse code',
			'Warehouse',
			'Quantity',
			'Reserved',
			'Available',
			'Incoming',
			'Cost',
			'Stock value',
		];

		foreach ($items as $item) {
			$rows[] = [
				$item->sku,
				$item->itemtitle,
				$item->warehousecode,
				$item->warehousetitle,
				$item->quantity,
				$item->reserved,
				$item->available,
				$item->incoming,
				$item->cost,
				$item->stockvalue,
			];
		}

		$file = 'stock-' . date('Ymd-His') . '.csv';

		$this->writeCsv(
			$filePath . $file,
			$rows
		);

		return [
			'name' => $file,
			'path' => $filePath . $file,
		];
	}

	protected function writeCsv(
		string $file,
		array $rows
	): void {
		$handle = fopen($file, 'w');

		if (!$handle) {
			throw new RuntimeException(
				'Could not create CSV file: ' . $file
			);
		}

		foreach ($rows as $row) {
			fputcsv(
				$handle,
				$row,
				';'
			);
		}

		fclose($handle);
	}
}
