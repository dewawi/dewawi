
<?php

class Statistics_Model_List_Turnover extends DEEC_List
{
	protected function buildColumns()
	{
		$exportAmount = static function($item, $column): string {
			return number_format((float)$item[$column['name']], 2, ',', '');
		};

		return [
			['name' => 'rank', 'label' => '', 'export' => false],
			['name' => 'period', 'label' => 'STATISTICS_TURNOVER_MONTH',
				'export_callback' => static fn($item) => str_replace('/', '-', $item['period'])],
			['name' => 'invoicesQuantity', 'label' => 'STATISTICS_INVOICE_QUANTITY'],
			['name' => 'invoicesSubtotal', 'label' => 'STATISTICS_INVOICE_SUBTOTAL',
				'type' => 'currency', 'export_callback' => $exportAmount],
			['name' => 'invoicesAverage', 'label' => 'STATISTICS_INVOICE_AVERAGE',
				'type' => 'currency', 'export_callback' => $exportAmount],
			['name' => 'creditnotesQuantity', 'label' => 'STATISTICS_CREDIT_NOTE_QUANTITY'],
			['name' => 'creditnotesSubtotal', 'label' => 'STATISTICS_CREDIT_NOTE_SUBTOTAL',
				'type' => 'currency', 'export_callback' => $exportAmount],
			['name' => 'creditnotesAverage', 'label' => 'STATISTICS_CREDIT_NOTE_AVERAGE',
				'type' => 'currency', 'export_callback' => $exportAmount],
			['name' => 'netTurnover', 'label' => 'STATISTICS_NET_TURNOVER',
				'type' => 'currency', 'export_callback' => $exportAmount],
		];
	}

	public function setPeriodLabel(string $label)
	{
		$columns = $this->getColumns();
		$columns[1]['label'] = $label;
		return $this->setColumns($columns);
	}
}

