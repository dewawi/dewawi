<?php

class Admin_Controller_Action_Helper_Params extends Zend_Controller_Action_Helper_Abstract
{
	public function getParams($toolbar, $options)
	{
		$request = $this->getRequest();

		$params = array();

		if(isset($toolbar->clientid)) {
			$params['clientid'] = $request->getParam('clientid', $request->getCookie('clientid', key($options['clients'])));
			$toolbar->clientid->setValue($params['clientid']);
		}

		if(isset($toolbar->parentid)) {
			$params['parentid'] = $request->getParam('parentid', $request->getCookie('parentid', $toolbar->parentid->getAttrib('default')));
			$toolbar->parentid->setValue($params['parentid']);
		}

		if(isset($toolbar->language)) {
			$params['language'] = $request->getParam('language', $request->getCookie('language', $toolbar->language->getAttrib('default')));
			$toolbar->language->setValue($params['language']);
		}

		if(isset($toolbar->type)) {
			$params['type'] = $request->getParam('type', $request->getCookie('type', $toolbar->type->getAttrib('default')));
			$toolbar->type->setValue($params['type']);
		}

		if(isset($toolbar->shopid)) {
			$params['shopid'] = $request->getParam('shopid', $request->getCookie('shopid', $toolbar->shopid->getAttrib('default')));
			$toolbar->shopid->setValue($params['shopid']);
		}

		return $params;
	}
}
