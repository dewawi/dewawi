<?php

class Application_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();

		$params = array();

		$params['catid'] = $request->getParam('catid', $request->getCookie('catid', $toolbar->catid->getAttrib('default')));
		$toolbar->catid->setValue($params['catid']);

		$params['country'] = $request->getParam('country', $request->getCookie('country', $toolbar->country->getAttrib('default')));
		$toolbar->country->setValue($params['country']);

		$params['width'] = $request->getParam('width', $request->getCookie('width', $toolbar->width->getAttrib('default')));
		$toolbar->width->setValue($params['width']);

		$params['height'] = $request->getParam('height', $request->getCookie('height', $toolbar->height->getAttrib('default')));
		$toolbar->height->setValue($params['height']);

		return $params;
	}
}
