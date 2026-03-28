<?php

class Shops_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar)
	{
		$request = $this->getRequest();

		$params = array();

		$params['keyword'] = $request->getParam('keyword', $request->getCookie('keyword', $toolbar->getDefault('keyword')));
		$toolbar->setValue('keyword', $params['keyword']);

		$params['limit'] = $request->getParam('limit', $request->getCookie('limit', $toolbar->getDefault('limit')));
		$toolbar->setValue('limit', $params['limit']);

		$params['order'] = $request->getParam('order', $request->getCookie('order', $toolbar->getDefault('order')));
		$toolbar->setValue('order', $params['order']);

		$params['sort'] = $request->getParam('sort', $request->getCookie('sort', $toolbar->getDefault('sort')));
		$toolbar->setValue('sort', $params['sort']);

		return $params;
	}
}
