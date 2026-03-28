<?php

class Application_Controller_Action_Helper_Pagination extends Zend_Controller_Action_Helper_Abstract
{
	public function getPagination($toolbar, $params, $records, $count)
	{
		// defaults sauber holen
		$limit = isset($params['limit']) ? (int)$params['limit'] : (int)$toolbar->getDefault('limit');
		$page = isset($params['page']) ? (int)$params['page'] : (int)$toolbar->getDefault('page');

		if ($limit <= 0) {
			// "ALL": zeige alles auf einer Seite
			$limit = ($records > 0) ? $records : 1;
		}

		$pages = (int)ceil($records / $limit);
		if ($pages < 1) $pages = 1;

		if ($page < 1) $page = 1;
		if ($page > $pages) $page = 1;

		// zurück in params (damit Model/Get gleiche Werte hat)
		$params['limit'] = $limit;
		$params['page'] = $page;

		// page select options ersetzen (sonst doppelt bei mehreren Requests)
		$pageOptions = [];
		for ($i = 1; $i <= $pages; $i++) {
			$pageOptions[(string)$i] = (string)$i;
		}
		$toolbar->addOptions('page', $pageOptions, 'replace');
		$toolbar->setValue('page', (string)$page);

		// start/end sauber (bei 0 items: 0/0)
		if ($records === 0 || $count === 0) {
			$start = 0;
			$end = 0;
		} else {
			$start = (($page - 1) * $limit) + 1;
			$end = min($start + $count - 1, $records);
		}

		return [
			'page' => $page,
			'pages' => $pages,
			'count' => $count,
			'records' => $records,
			'start' => $start,
			'end' => $end,
			'limit' => $limit,
		];
	}
}
