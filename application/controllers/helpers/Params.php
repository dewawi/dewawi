<?php

class Application_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();

		$params = array();

		$params['catid'] = $request->getParam('catid', $request->getCookie('catid', $toolbar->getDefault('catid')));
		$toolbar->setValue('catid', $params['catid']);

		$params['country'] = $request->getParam('country', $request->getCookie('country', $toolbar->getDefault('country')));
		$toolbar->setValue('country', $params['country']);

		/*$params['width'] = $request->getParam('width', $request->getCookie('width', $toolbar->getDefault('width')));
		$toolbar->setValue('width', $params['width']);

		$params['height'] = $request->getParam('height', $request->getCookie('height', $toolbar->getDefault('height')));
		$toolbar->setValue('height', $params['height']);*/

		return $params;
	}
}
