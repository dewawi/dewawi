<?php

class Application_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options = [])
	{
		$request = $this->getRequest();
		$params = [];

		foreach ($toolbar->getParamElements() as $name => $element) {
			$value = $this->readValue($request, $toolbar, $name, $element);
			$value = $this->normalizeValue($value, $element);

			$params[$name] = $value;
			$toolbar->setValue($name, $value);
		}

		$params['limit'] = $request->getParam('limit', $request->getCookie('limit', 25));
		$params['page'] = $request->getParam('page', $request->getCookie('page', 1));

		$this->applyDateRange($params, $toolbar);
		$this->applyPagination($params);

		return $params;
	}

	protected function readValue($request, $toolbar, string $name, array $element)
	{
		$default = $toolbar->getDefault($name);

		if ($name === 'from' && empty($default)) {
			$default = date('Y-m-d', strtotime('-1 month'));
		}

		if ($name === 'to' && empty($default)) {
			$default = date('Y-m-d');
		}

		return $request->getParam(
			$name,
			$request->getCookie($name, $default)
		);
	}

	protected function normalizeValue($value, array $element)
	{
		$type = $element['type'] ?? 'text';

		if ($type === 'multicheckbox') {
			return $this->normalizeArrayValue($value);
		}

		if (is_array($value)) {
			return array_values(array_filter($value, static function ($item) {
				return $item !== null && $item !== '';
			}));
		}

		return is_string($value) ? trim($value) : $value;
	}

	protected function normalizeArrayValue($value): array
	{
		if (is_array($value)) {
			return array_values(array_filter($value, static function ($item) {
				return $item !== null && $item !== '';
			}));
		}

		if (is_string($value) && $value !== '') {
			try {
				$decoded = Zend_Json::decode($value);

				if (is_array($decoded)) {
					return array_values(array_filter($decoded, static function ($item) {
						return $item !== null && $item !== '';
					}));
				}
			} catch (Exception $e) {
				return [$value];
			}

			return [$value];
		}

		return [];
	}

	public function getDateRange($dateRange)
	{
		switch ($dateRange) {
			case 'today':
				$from = date('Y-m-d');
				$to = date('Y-m-d');
				break;
			case 'yesterday':
				$from = date('Y-m-d', strtotime('-1 day'));
				$to = date('Y-m-d', strtotime('-1 day'));
				break;
			case 'last7days':
				$from = date('Y-m-d', strtotime('-7 days'));
				$to = date('Y-m-d');
				break;
			case 'last14days':
				$from = date('Y-m-d', strtotime('-14 days'));
				$to = date('Y-m-d');
				break;
			case 'last30days':
				$from = date('Y-m-d', strtotime('-30 days'));
				$to = date('Y-m-d');
				break;
			case 'thisMonth':
				$from = date('Y-m-01');
				$to = date('Y-m-t');
				break;
			case 'lastMonth':
				$from = date('Y-m-01', strtotime('-1 month'));
				$to = date('Y-m-t', strtotime('-1 month'));
				break;
			case 'thisYear':
				$from = date('Y-01-01');
				$to = date('Y-12-31');
				break;
			case 'lastYear':
				$from = date('Y-01-01', strtotime('-1 year'));
				$to = date('Y-12-31', strtotime('-1 year'));
				break;
			default:
				$from = date('Y-m-d', strtotime('-1 month'));
				$to = date('Y-m-d');
		}

		return [
			'from' => $from,
			'to' => $to,
		];
	}

	protected function applyDateRange(array &$params, $toolbar): void
	{
		if (empty($params['daterange']) || $params['daterange'] === 'custom' || $params['daterange'] === 'all') {
			return;
		}

		$dateRange = $this->getDateRange($params['daterange']);

		$params['from'] = $dateRange['from'];
		$params['to'] = $dateRange['to'];

		$toolbar->setValue('from', $params['from']);
		$toolbar->setValue('to', $params['to']);
	}

	protected function applyPagination(array &$params): void
	{
		$params['page'] = isset($params['page']) ? (int)$params['page'] : 1;

		if ($params['page'] <= 0) {
			$params['page'] = 1;
		}

		$params['limit'] = isset($params['limit']) ? (int)$params['limit'] : 25;

		if ($params['limit'] <= 0) {
			$params['limit'] = 25;
		}

		if ($params['limit'] > 100) {
			$params['limit'] = 100;
		}

		$params['offset'] = ($params['page'] - 1) * $params['limit'];
	}
}
