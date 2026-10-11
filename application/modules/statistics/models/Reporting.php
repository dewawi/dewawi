<?php

class Statistics_Model_Reporting
{
	public function build(string $from, string $to, string $uncategorized, string $noData): array
	{
		$params = ['daterange' => 'custom', 'from' => $from, 'to' => $to, 'catid' => 'all', 'country' => '0'];
		$options = ['categories' => (new Application_Model_DbTable_Category())->getCategories('contact')];

		$turnover = new Statistics_Model_Turnover();
		[$months, , $categories] = $turnover->createCharts(1000, 400, $uncategorized, $noData, $params, $options);

		$yearly = [];
		for($year = (int)substr($from, 0, 4); $year <= (int)substr($to, 0, 4); $year++) $yearly[$year] = ['period' => (string)$year, 'invoicesQuantity' => 0, 'invoicesSubtotal' => 0.0, 'creditnotesQuantity' => 0, 'creditnotesSubtotal' => 0.0];

		$monthly = [];
		$fields = ['invoicesQuantity', 'invoicesSubtotal', 'creditnotesQuantity', 'creditnotesSubtotal'];

		foreach($months as $month) {
			$year = substr($month['month'], 0, 4);
			$monthlyRow = ['period' => $month['month']];

			foreach($fields as $field) {
				$monthlyRow[$field] = $month[$field] ?? 0;
				$yearly[$year][$field] += $monthlyRow[$field];
			}

			$monthlyRow['netTurnover'] = $monthlyRow['invoicesSubtotal'] - $monthlyRow['creditnotesSubtotal'];
			$monthly[] = $monthlyRow;
		}

		foreach($yearly as &$row) $row['netTurnover'] = $row['invoicesSubtotal'] - $row['creditnotesSubtotal'];
		unset($row);

		$customer = new Statistics_Model_Customer();
		$customer->createCharts(1000, 600, $uncategorized, $noData, $params, $options);

		$customerYears = $customer->getYearlyReport();
		$allCustomers = [];
		$topByYear = [];
		$allCustomersByYear = [];
		$customerSummary = [];

		foreach($customerYears as $year => $report) {
			$customerSummary[] = $report['summary'];
			$topByYear[$year] = $this->rankCustomers($report['raw'], 30);
			$allCustomersByYear[$year] = $this->rankCustomers($report['raw'], count($report['raw']));

			foreach($report['raw'] as $row) {
				$id = (int)$row['contactid'];

				if(!isset($allCustomers[$id])) {
					$allCustomers[$id] = $row;
				} else {
					$allCustomers[$id]['subtotal'] += $row['subtotal'];
					$allCustomers[$id]['invoices'] = array_merge($allCustomers[$id]['invoices'], $row['invoices']);
					$allCustomers[$id]['creditnotes'] = array_merge($allCustomers[$id]['creditnotes'], $row['creditnotes']);
				}
			}
		}

		return [
			'yearly' => array_values($yearly),
			'monthly' => $monthly,
			'comparisons' => $turnover->getYearlyComparisons(),
			'categories' => $categories,
			'yearlyCategories' => $turnover->getYearlyCategoryRows(),
			'customerSummary' => $customerSummary,
			'customers' => $this->rankCustomers(array_values($allCustomers), 30),
			'customersByYear' => $topByYear,
			'allCustomers' => $this->rankCustomers(array_values($allCustomers), count($allCustomers)),
			'allCustomersByYear' => $allCustomersByYear,
		];
	}

	private function rankCustomers(array $rows, int $limit): array
	{
		usort($rows, static fn($a, $b) => $b['subtotal'] <=> $a['subtotal']);
		$total = array_sum(array_column($rows, 'subtotal'));
		$rows = array_slice($rows, 0, $limit);

		foreach($rows as $index => &$row) {
			$row['rank'] = $index + 1;
			$row['share'] = $total > 0 ? $row['subtotal'] / $total * 100 : 0;
		}
		unset($row);

		return $rows;
	}

	public function createWorksheets(array $data, string $from, string $to): array
	{
		$net = array_sum(array_column($data['yearly'], 'netTurnover'));
		$customerNet = array_sum(array_column($data['allCustomers'], 'subtotal'));

		$checks = $this->createAuditRows($data);
		$hasWarnings = count(array_filter($checks, static fn($row) => $row[8] !== 'OK')) > 0;

		$sheets = [[
			'name' => 'Übersicht',
			'headers' => ['Kennzahl', 'Wert', 'Einheit'],
			'rows' => [
				['Zeitraum', $from.' bis '.$to, ''],
				['Erstellt am', date('Y-m-d H:i:s'), ''],
				['Nettoumsatz', $net, 'EUR'],
				['Rechnungen', array_sum(array_column($data['yearly'], 'invoicesQuantity')), 'Anzahl'],
				['Gutschriften', array_sum(array_column($data['yearly'], 'creditnotesQuantity')), 'Anzahl'],
				['Kunden mit Belegen', count($data['allCustomers']), 'Anzahl'],
				['Differenz Umsatz/Kunden', round($net - $customerNet, 2), 'EUR'],
				['Datenprüfung', $hasWarnings ? 'PRÜFEN' : 'OK', ''],
				['Belege', 'Abgeschlossen (Status 105), nicht gelöscht, nicht storniert', ''],
				['Kategorien', 'Aktuelle Kategorien der Kontakte, nicht historisiert', ''],
				['Jahresvergleich', 'Gleiche Kalendertage innerhalb des gewählten Zeitraums', ''],
				['Teiljahre', 'Erstes und letztes Jahr können unvollständig sein', ''],
				['Datenprüfung', 'Vorzeichen historischer Gutschriften vor externer Nutzung prüfen', ''],
			],
			'widths' => [28, 76, 16],
		]];

		$sheets[] = $this->worksheet('Jahresumsatz', [
			['period', 'Jahr', 'text'],
			['invoicesQuantity', 'Rechnungen', 'integer'],
			['invoicesSubtotal', 'Rechnungen netto', 'money'],
			['creditnotesQuantity', 'Gutschriften', 'integer'],
			['creditnotesSubtotal', 'Gutschriften netto', 'money'],
			['netTurnover', 'Nettoumsatz', 'money'],
		], $data['yearly']);

		$sheets[count($sheets) - 1]['chart'] = ['category' => 0, 'value' => 5];

		$sheets[] = $this->worksheet('Monatsumsatz', [
			['period', 'Monat', 'text'],
			['invoicesQuantity', 'Rechnungen', 'integer'],
			['invoicesSubtotal', 'Rechnungen netto', 'money'],
			['creditnotesQuantity', 'Gutschriften', 'integer'],
			['creditnotesSubtotal', 'Gutschriften netto', 'money'],
			['netTurnover', 'Nettoumsatz', 'money'],
		], $data['monthly']);

		$sheets[] = $this->worksheet('Vorjahresvergleich', [
			['year', 'Jahr', 'integer'],
			['period', 'Vergleichszeitraum', 'text'],
			['previousTurnover', 'Vorjahr netto', 'money'],
			['currentTurnover', 'Aktuelles Jahr netto', 'money'],
			['difference', 'Differenz netto', 'money'],
			['growth', 'Veränderung (%)', 'percent'],
		], $data['comparisons']);

		$sheets[] = $this->worksheet('Kundengruppen', [
			['category', 'Kundengruppe', 'text'],
			['subtotal', 'Nettoumsatz', 'money'],
			['share', 'Anteil (%)', 'percent'],
		], $data['categories']);

		$sheets[] = $this->worksheet('Kundengruppen Jahre', [
			['year', 'Jahr', 'integer'],
			['category', 'Kundengruppe', 'text'],
			['customersQuantity', 'Kunden', 'integer'],
			['invoicesQuantity', 'Rechnungen', 'integer'],
			['creditnotesQuantity', 'Gutschriften', 'integer'],
			['subtotal', 'Nettoumsatz', 'money'],
			['share', 'Anteil (%)', 'percent'],
		], $data['yearlyCategories']);

		$customerSummaryColumns = [
			['year', 'Jahr', 'integer'],
			['customers', 'Kunden', 'integer'],
			['invoices', 'Rechnungen', 'integer'],
			['creditnotes', 'Gutschriften', 'integer'],
			['subtotal', 'Nettoumsatz', 'money'],
		];

		foreach([1, 5, 10, 20, 30] as $count) $customerSummaryColumns[] = ['top'.$count, 'Top '.$count.' (%)', 'percent'];

		$sheets[] = $this->worksheet('Kunden Kennzahlen', $customerSummaryColumns, $data['customerSummary']);

		$sheets[count($sheets) - 1]['chart'] = ['category' => 0, 'value' => 1];

		$customerColumns = [
			['rank', 'Rang', 'integer'],
			['contactid', 'Kundennummer', 'text'],
			['name1', 'Kunde', 'text'],
			['cfulltitle', 'Kundengruppe', 'text'],
			['invoicesCount', 'Rechnungen', 'integer'],
			['creditnotesCount', 'Gutschriften', 'integer'],
			['subtotal', 'Nettoumsatz', 'money'],
			['share', 'Anteil (%)', 'percent'],
		];

		$sheets[] = $this->worksheet('Kunden Gesamt', $customerColumns, $data['allCustomers']);

		foreach($data['allCustomersByYear'] as $year => $customers) {
			$sheets[] = $this->worksheet('Kunden '.$year, $customerColumns, $customers);
		}

		$sheets[] = [
			'name' => 'Datenprüfung',
			'headers' => [
				'Jahr', 'Jahresumsatz', 'Monatssumme', 'Kundensumme',
				'Kundengruppensumme', 'Diff. Monate', 'Diff. Kunden',
				'Diff. Gruppen', 'Status',
			],
			'rows' => $checks,
			'formats' => ['integer', 'money', 'money', 'money', 'money', 'money', 'money', 'money', 'text'],
			'widths' => [12, 22, 22, 22, 25, 20, 20, 20, 18],
		];

		return $sheets;
	}

	private function worksheet(string $name, array $columns, array $items): array
	{
		$rows = [];

		foreach($items as $item) {
			$row = [];

			foreach($columns as [$field, , $format]) {
				$value = $field === 'invoicesCount' ? count($item['invoices'] ?? []) : ($field === 'creditnotesCount' ? count($item['creditnotes'] ?? []) : ($item[$field] ?? null));

				if($format === 'percent' && $value !== null) $value = (float)$value / 100;
				if($field === 'contactid' && $value !== null) $value = (string)$value;

				$row[] = $value;
			}

			$rows[] = $row;
		}

		return [
			'name' => $name,
			'headers' => array_column($columns, 1),
			'rows' => $rows,
			'formats' => array_column($columns, 2),
		];
	}

	private function createAuditRows(array $data): array
	{
		$monthly = [];
		$categories = [];

		foreach($data['monthly'] as $row) {
		    $year = substr((string)$row['period'], 0, 4);
		    $monthly[$year] = ($monthly[$year] ?? 0) + (float)$row['netTurnover'];
		}

		foreach($data['yearlyCategories'] as $row) {
		    $year = (string)$row['year'];
		    $categories[$year] = ($categories[$year] ?? 0) + (float)$row['subtotal'];
		}

		$checks = [];

		foreach($data['yearly'] as $row) {
		    $year = (string)$row['period'];
		    $net = (float)$row['netTurnover'];
		    $monthlyNet = $monthly[$year] ?? 0.0;
		    $categoryNet = $categories[$year] ?? 0.0;
		    $customerNet = array_sum(array_column($data['allCustomersByYear'][$year] ?? [], 'subtotal'));

		    $monthDiff = round($net - $monthlyNet, 2);
		    $customerDiff = round($net - $customerNet, 2);
		    $categoryDiff = round($net - $categoryNet, 2);

		    $hasDocuments = (int)$row['invoicesQuantity'] + (int)$row['creditnotesQuantity'] > 0;
		    $status = !$hasDocuments ? 'KEINE BELEGE' : (max(abs($monthDiff), abs($customerDiff), abs($categoryDiff)) <= 0.05 ? 'OK' : 'PRÜFEN');

		    $checks[] = [
		        $year, $net, $monthlyNet, $customerNet, $categoryNet,
		        $monthDiff, $customerDiff, $categoryDiff, $status,
		    ];
		}

		return $checks;
	}
}
