<?php

class Application_Plugin_ListResponse extends Zend_Controller_Plugin_Abstract
{
	public function postDispatch(Zend_Controller_Request_Abstract $request)
	{
		if($request->getActionName() !== 'search' || !$request->isXmlHttpRequest()) return;

		$response = $this->getResponse();
		$content = $response->getBody();
		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
		$view = $viewRenderer->view;

		if(!$view) return;

		$contextAction = (string)$request->getParam('context_action', 'index');
		if(!in_array($contextAction, ['index', 'select'], true)) $contextAction = 'index';

		$previousAction = $view->action ?? null;
		$view->action = $contextAction;
		$toolbar = $view->Toolbar();
		$view->action = $previousAction;

		$response->clearBody();
		$response->setHeader('Content-Type', 'application/json; charset=UTF-8', true);
		$response->setBody(json_encode([
			'content' => $content,
			'toolbar' => $toolbar,
		]));
	}
}
