<?php

class Application_Controller_Action_Helper_Pagination extends Zend_Controller_Action_Helper_Abstract
{
	public function getPagination($toolbar, $params, $records, $count)
	{
		if($params['limit'] == 0) $params['limit'] = 1000;
		$pages = ceil($records/$params['limit']);
		if($params['page'] > $pages) $params['page'] = 1;
		for ($i = 1; $i <= $pages; $i++) {
			$toolbar->page->addMultiOption($i, $i);
		}
		$toolbar->page->setValue($params['page']);

		$pagination = array();
		$pagination['page'] = $params['page'];
		$pagination['count'] = $count;
		$pagination['records'] = $records;
		$pagination['start'] = (($params['page']-1)*$params['limit'])+1;
		$pagination['end'] = (($params['page']-1)*$params['limit'])+$pagination['count'];

		return $pagination;
	}
}
