
<?php

class Statistics_Model_List_Turnover extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			['name' => 'rank', 'label' => ''],
			['name' => 'period', 'label' => 'STATISTICS_TURNOVER_MONTH'],
			['name' => 'invoicesQuantity', 'label' => 'STATISTICS_INVOICE_QUANTITY'],
			['name' => 'invoicesSubtotal', 'label' => 'STATISTICS_INVOICE_SUBTOTAL'],
			['name' => 'invoicesAverage', 'label' => 'STATISTICS_INVOICE_AVERAGE'],
			['name' => 'creditnotesQuantity', 'label' => 'STATISTICS_CREDIT_NOTE_QUANTITY'],
			['name' => 'creditnotesSubtotal', 'label' => 'STATISTICS_CREDIT_NOTE_SUBTOTAL'],
			['name' => 'creditnotesAverage', 'label' => 'STATISTICS_CREDIT_NOTE_AVERAGE'],
			['name' => 'netTurnover', 'label' => 'STATISTICS_NET_TURNOVER'],
		];
	}

	public function setPeriodLabel(string $label)
	{
		$columns = $this->getColumns();
		$columns[1]['label'] = $label;
		return $this->setColumns($columns);
	}
}

