
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
        $customerSummary = [];

        foreach($customerYears as $year => $report) {
            $customerSummary[] = $report['summary'];
            $topByYear[$year] = $this->rankCustomers($report['raw'], 30);

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
}
