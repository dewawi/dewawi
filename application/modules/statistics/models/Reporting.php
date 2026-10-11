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
			'negativeCreditnotes' => $turnover->getNegativeCreditnotes(),
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

		$checks = $this->getAuditRows($data);
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
				['Kundenentwicklung', 'Jahreswerte vollständig innerhalb des Filters; Vorjahresvergleiche für übereinstimmende Kalendertage', ''],
				['Kundenaktivität', 'Einordnung ausschließlich nach Belegen in den verglichenen Zeiträumen', ''],
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

		$sheets[] = $this->customerMatrixWorksheet($data);
		$sheets[] = $this->customerComparisonWorksheet($data, $from, $to);

		foreach($data['allCustomersByYear'] as $year => $customers) {
			$sheets[] = $this->worksheet('Kunden '.$year, $customerColumns, $customers);
		}

		$sheets[] = [
			'name' => 'Datenprüfung',
			'headers' => [
				'Jahr', 'Jahresumsatz', 'Monatssumme', 'Kundensumme',
				'Kundengruppensumme', 'Diff. Monate', 'Diff. Kunden',
				'Diff. Gruppen', 'Status', 'Hinweis',
			],
			'rows' => $checks,
			'formats' => ['integer', 'money', 'money', 'money', 'money', 'money', 'money', 'money', 'text', 'text'],
			'widths' => [12, 22, 22, 22, 25, 20, 20, 20, 18, 58],
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

	public function getAuditRows(array $data): array
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

			$issues = [];

			if(abs($monthDiff) > 0.05) $issues[] = 'Monatssumme abweichend';
			if(abs($customerDiff) > 0.05) $issues[] = 'Kundensumme abweichend';
			if(abs($categoryDiff) > 0.05) $issues[] = 'Kundengruppensumme abweichend';

			$negativeCount = (int)($data['negativeCreditnotes'][$year] ?? 0);
			if($negativeCount > 0) $issues[] = $negativeCount.' Gutschrift(en) mit negativem Nettobetrag';

			$hasDocuments = (int)$row['invoicesQuantity'] + (int)$row['creditnotesQuantity'] > 0;
			$status = !$hasDocuments ? 'KEINE BELEGE' : ($issues ? 'PRÜFEN' : 'OK');

			$checks[] = [
				$year, $net, $monthlyNet, $customerNet, $categoryNet,
				$monthDiff, $customerDiff, $categoryDiff, $status, implode('; ', $issues),
			];
		}

		return $checks;
	}

	private function customerMatrixWorksheet(array $data): array
	{
		$years = array_column($data['yearly'], 'period');
		$headers = ['Kundennr.', 'Kunde', 'Kundengruppe', 'Gesamt netto'];
		$formats = ['text', 'text', 'text', 'money'];
		$amounts = [];

		foreach($data['allCustomersByYear'] as $year => $customers) {
		    foreach($customers as $customer) $amounts[$year][(int)$customer['contactid']] = (float)$customer['subtotal'];
		}

		foreach($years as $year) {
		    $headers[] = $year.' netto';
		    $formats[] = 'money';
		}

		$rows = [];
		foreach($data['allCustomers'] as $customer) {
		    $id = (int)$customer['contactid'];
		    $row = [(string)$customer['contactid'], $customer['name1'], $customer['cfulltitle'], (float)$customer['subtotal']];
		    foreach($years as $year) $row[] = $amounts[$year][$id] ?? 0.0;
		    $rows[] = $row;
		}

		return [
		    'name' => 'Kunden Jahresmatrix',
		    'headers' => $headers,
		    'rows' => $rows,
		    'formats' => $formats,
		    'widths' => array_merge([18, 44, 42, 22], array_fill(0, count($years), 20)),
		];
	}

	private function customerComparisonWorksheet(array $data, string $from, string $to): array
	{
		$years = array_column($data['yearly'], 'period');
		$customersByYear = [];

		foreach($data['allCustomersByYear'] as $year => $customers) {
		    foreach($customers as $customer) $customersByYear[$year][(int)$customer['contactid']] = $customer;
		}

		$rows = [];

		foreach($years as $index => $year) {
		    if($index === 0) continue;

		    $previousYear = (int)$year - 1;
		    $currentFrom = max($from, $year.'-01-01');
		    $currentTo = min($to, $year.'-12-31');
		    $previousFrom = max($from, $previousYear.'-01-01');
		    $previousTo = min($to, $previousYear.'-12-31');

		    $start = max(substr($currentFrom, 5), substr($previousFrom, 5));
		    $end = min(substr($currentTo, 5), substr($previousTo, 5));
		    if($start > $end) continue;

		    $period = substr($start, 3, 2).'.'.substr($start, 0, 2).'.–'.substr($end, 3, 2).'.'.substr($end, 0, 2).'.';

		    foreach($data['allCustomers'] as $customer) {
		        $id = (int)$customer['contactid'];

		        [$previous, $hadPrevious] = $this->sumCustomerDays($customersByYear[$previousYear][$id]['daily'] ?? [], $start, $end);
		        [$current, $hadCurrent] = $this->sumCustomerDays($customersByYear[$year][$id]['daily'] ?? [], $start, $end);

		        if(!$hadPrevious && !$hadCurrent) continue;

		        $difference = $current - $previous;
		        $status = !$hadPrevious ? 'Vorjahr ohne Belege' : (!$hadCurrent ? 'Aktuelles Jahr ohne Belege' : 'In beiden Zeiträumen aktiv');

		        $rows[] = [
		            (int)$year,
		            $period,
		            (string)$customer['contactid'],
		            $customer['name1'],
		            $customer['cfulltitle'],
		            $previous,
		            $current,
		            $difference,
		            $previous > 0 ? $difference / $previous : null,
		            $status,
		        ];
		    }
		}

		usort($rows, static fn($a, $b) => ($a[0] <=> $b[0]) ?: (abs($b[7]) <=> abs($a[7])));

		return [
		    'name' => 'Kunden Veränderung',
		    'headers' => ['Jahr', 'Vergleichszeitraum', 'Kundennr.', 'Kunde', 'Kundengruppe', 'Vorjahr netto', 'Aktuelles Jahr netto', 'Differenz netto', 'Veränderung (%)', 'Einordnung'],
		    'rows' => $rows,
		    'formats' => ['integer', 'text', 'text', 'text', 'text', 'money', 'money', 'money', 'percent', 'text'],
		    'widths' => [12, 22, 18, 44, 42, 22, 22, 22, 20, 29],
		];
	}

	private function sumCustomerDays(array $daily, string $start, string $end): array
	{
		$sum = 0.0;
		$hasDocuments = false;

		foreach($daily as $date => $amount) {
		    $day = substr((string)$date, 5, 5);
		    if($day < $start || $day > $end) continue;

		    $sum += (float)$amount;
		    $hasDocuments = true;
		}

		return [$sum, $hasDocuments];
	}
}
