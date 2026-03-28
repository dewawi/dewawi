<?php

class Contacts_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();
		$params  = [];

		$params['keyword'] = $request->getParam('keyword', $request->getCookie('keyword', $toolbar->getDefault('keyword')));
		$toolbar->setValue('keyword', $params['keyword']);

		$params['catid'] = $request->getParam('catid', $request->getCookie('catid', $toolbar->getDefault('catid')));
		$toolbar->setValue('catid', $params['catid']);

		$params['limit'] = $request->getParam('limit', $request->getCookie('limit', $toolbar->getDefault('limit')));
		$toolbar->setValue('limit', $params['limit']);

		$params['order'] = $request->getParam('order', $request->getCookie('order', $toolbar->getDefault('order')));
		$toolbar->setValue('order', $params['order']);

		$params['sort'] = $request->getParam('sort', $request->getCookie('sort', $toolbar->getDefault('sort')));
		$toolbar->setValue('sort', $params['sort']);

		$params['country'] = $request->getParam('country', $request->getCookie('country', $toolbar->getDefault('country')));
		$toolbar->setValue('country', $params['country']);

		$params['page'] = $request->getParam('page', $request->getCookie('page', $toolbar->getDefault('page')));
		$toolbar->setValue('page', $params['page']);

		$params['tagid'] = $request->getParam('tagid', $request->getCookie('tagid', $toolbar->getDefault('tagid')));
		$toolbar->setValue('tagid', $params['tagid']);

		return $params;
	}
}
