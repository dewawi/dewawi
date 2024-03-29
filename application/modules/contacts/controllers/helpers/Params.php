<?php

class Contacts_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();

		$params = array();

		$params['keyword'] = $request->getParam('keyword', $request->getCookie('keyword', $toolbar->keyword->getAttrib('default')));
		$toolbar->keyword->setValue($params['keyword']);

		$params['catid'] = $request->getParam('catid', $request->getCookie('catid', $toolbar->catid->getAttrib('default')));
		$toolbar->catid->setValue($params['catid']);

		$params['limit'] = $request->getParam('limit', $request->getCookie('limit', $toolbar->limit->getAttrib('default')));
		$toolbar->limit->setValue($params['limit']);

		$params['order'] = $request->getParam('order', $request->getCookie('order', $toolbar->order->getAttrib('default')));
		$toolbar->order->setValue($params['order']);

		$params['sort'] = $request->getParam('sort', $request->getCookie('sort', $toolbar->sort->getAttrib('default')));
		$toolbar->sort->setValue($params['sort']);

		$params['country'] = $request->getParam('country', $request->getCookie('country', $toolbar->country->getAttrib('default')));
		$toolbar->country->setValue($params['country']);

		$params['page'] = $request->getParam('page', $request->getCookie('page', $toolbar->page->getAttrib('default')));
		$toolbar->page->setValue($params['page']);

		$params['tagid'] = $request->getParam('tagid', $request->getCookie('tagid', $toolbar->tagid->getAttrib('default')));
		$toolbar->tagid->setValue($params['tagid']);

		return $params;
	}
}
