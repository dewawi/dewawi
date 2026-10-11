
<?php

class Statistics_ReportingController extends DEEC_Controller_Action
{
    public function indexAction()
    {
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);

        $from = (string)$this->_getParam('from', date('Y-01-01'));
        $to = (string)$this->_getParam('to', date('Y-m-d'));

        foreach([$from, $to] as $value) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if(!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Invalid reporting date.');
        }

        if($from > $to) throw new InvalidArgumentException('Reporting start date must not exceed end date.');

        $data = (new Statistics_Model_Reporting())->build($from, $to, $this->view->translate('STATISTICS_UNCATEGORIZED'), $this->view->translate('STATISTICS_NO_DATA'));

        $percentage = static fn($item, $column) => $item[$column['name']] === null ? '–' : number_format((float)$item[$column['name']], 2, ',', '.').'%';
        $money = static fn($name, $label) => ['name' => $name, 'label' => $label, 'type' => 'currency'];
        $percent = static fn($name, $label) => ['name' => $name, 'label' => $label, 'type' => 'callback', 'callback' => $percentage];

        $turnoverColumns = [
            ['name' => 'period', 'label' => 'STATISTICS_PERIOD'],
            ['name' => 'invoicesQuantity', 'label' => 'STATISTICS_INVOICE_QUANTITY'],
            ['name' => 'creditnotesQuantity', 'label' => 'STATISTICS_CREDIT_NOTE_QUANTITY'],
            $money('netTurnover', 'STATISTICS_NET_TURNOVER'),
        ];

        $customerColumns = [
            ['name' => 'rank', 'label' => 'STATISTICS_CUSTOMER_RANK'],
            ['name' => 'contactid', 'label' => 'STATISTICS_CUSTOMER_ID'],
            ['name' => 'name1', 'label' => 'STATISTICS_CUSTOMER_NAME'],
            ['name' => 'cfulltitle', 'label' => 'STATISTICS_CUSTOMER_CATEGORY'],
            $money('subtotal', 'STATISTICS_NET_TURNOVER'),
            $percent('share', 'STATISTICS_CUSTOMER_SHARE_PERCENT'),
        ];

        $customerSummaryColumns = [
            ['name' => 'year', 'label' => 'STATISTICS_TURNOVER_YEAR'],
            ['name' => 'customers', 'label' => 'STATISTICS_CUSTOMERS_WITH_DOCUMENTS'],
            ['name' => 'invoices', 'label' => 'STATISTICS_INVOICE_QUANTITY'],
            ['name' => 'creditnotes', 'label' => 'STATISTICS_CREDIT_NOTE_QUANTITY'],
            $money('subtotal', 'STATISTICS_NET_TURNOVER'),
        ];

        foreach([1, 5, 10, 20, 30] as $count) $customerSummaryColumns[] = $percent('top'.$count, 'Top '.$count.' (%)');

        $lists = [
            'yearly' => $this->makeList('yearly', $data['yearly'], $turnoverColumns),
            'monthly' => $this->makeList('monthly', $data['monthly'], $turnoverColumns),
            'comparisons' => $this->makeList('comparisons', $data['comparisons'], [
                ['name' => 'year', 'label' => 'STATISTICS_TURNOVER_YEAR'],
                ['name' => 'period', 'label' => 'STATISTICS_COMPARISON_PERIOD'],
                $money('previousTurnover', 'STATISTICS_PREVIOUS_TURNOVER'),
                $money('currentTurnover', 'STATISTICS_CURRENT_TURNOVER'),
                $money('difference', 'STATISTICS_TURNOVER_DIFFERENCE'),
                $percent('growth', 'STATISTICS_TURNOVER_GROWTH'),
            ]),
            'categories' => $this->makeList('categories', $data['categories'], [
                ['name' => 'category', 'label' => 'STATISTICS_CUSTOMER_CATEGORY'],
                $money('subtotal', 'STATISTICS_NET_TURNOVER'),
                $percent('share', 'STATISTICS_CUSTOMER_SHARE_PERCENT'),
            ]),
            'yearlyCategories' => $this->makeList('yearly-categories', $data['yearlyCategories'], [
                ['name' => 'year', 'label' => 'STATISTICS_TURNOVER_YEAR'],
                ['name' => 'category', 'label' => 'STATISTICS_CUSTOMER_CATEGORY'],
                ['name' => 'customersQuantity', 'label' => 'STATISTICS_CUSTOMERS_WITH_DOCUMENTS'],
                ['name' => 'invoicesQuantity', 'label' => 'STATISTICS_INVOICE_QUANTITY'],
                ['name' => 'creditnotesQuantity', 'label' => 'STATISTICS_CREDIT_NOTE_QUANTITY'],
                $money('subtotal', 'STATISTICS_NET_TURNOVER'),
                $percent('share', 'STATISTICS_CUSTOMER_SHARE_PERCENT'),
            ]),
            'customerSummary' => $this->makeList('customer-summary', $data['customerSummary'], $customerSummaryColumns),
            'customers' => $this->makeList('customers', $data['customers'], $customerColumns),
        ];

        $yearlyCustomerLists = [];

        foreach($data['customersByYear'] as $year => $rows) $yearlyCustomerLists[$year] = $this->makeList('customers-'.$year, $rows, $customerColumns);

        $this->view->from = $from;
        $this->view->to = $to;
        $this->view->reportLists = $lists;
        $this->view->yearlyCustomerLists = $yearlyCustomerLists;
    }

    private function makeList(string $id, array $rows, array $columns): DEEC_List
    {
        return new DEEC_List([
            'id' => 'statistics-reporting-'.$id,
            'view' => $this->view,
            'module' => 'statistics',
            'controller' => 'reporting',
            'items' => $rows,
            'columns' => $columns,
            'selectable' => false,
        ]);
    }
}
