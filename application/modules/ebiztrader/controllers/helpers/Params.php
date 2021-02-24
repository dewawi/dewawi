<?php

class Ebiztrader_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();

		$params = array();

		$params['keyword'] = $request->getParam('keyword', $request->getCookie('keyword', $toolbar->keyword->getAttrib('default')));
		$toolbar->keyword->setValue($params['keyword']);

		$params['limit'] = $request->getParam('limit', $request->getCookie('limit', $toolbar->limit->getAttrib('default')));
		$toolbar->limit->setValue($params['limit']);

		$params['order'] = $request->getParam('order', $request->getCookie('order', $toolbar->order->getAttrib('default')));
		$toolbar->order->setValue($params['order']);

		$params['sort'] = $request->getParam('sort', $request->getCookie('sort', $toolbar->sort->getAttrib('default')));
		$toolbar->sort->setValue($params['sort']);

		return $params;
	}
}
