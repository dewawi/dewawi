<?php

class Admin_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();
		$params  = [];

		/*$params['clientid'] = $request->getParam('clientid', $request->getCookie('clientid', key($options['clients'])));
		$toolbar->setValue('clientid', $params['clientid']);*/

		$params['parentid'] = $request->getParam('parentid', $request->getCookie('parentid', $toolbar->getDefault('parentid')));
		$toolbar->setValue('parentid', $params['parentid']);

		$params['language'] = $request->getParam('language', $request->getCookie('language', $toolbar->getDefault('language')));
		$toolbar->setValue('language', $params['language']);

		$params['type'] = $request->getParam('type', $request->getCookie('type', $toolbar->getDefault('type')));
		$toolbar->setValue('type', $params['type']);

		$params['shopid'] = $request->getParam('shopid', $request->getCookie('shopid', $toolbar->getDefault('shopid')));
		$toolbar->setValue('shopid', $params['shopid']);

		return $params;
	}
}
