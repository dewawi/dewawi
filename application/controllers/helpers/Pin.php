<?php

class Application_Controller_Action_Helper_Pin extends Zend_Controller_Action_Helper_Abstract
{
	public function toogle($id) {
		$request = $this->getRequest();
		$params = $request->getParams();
		if($request->isPost()) $this->disableView();

		$getFunction = 'get'.ucfirst($params['controller']);
		$updateFunction = 'update'.ucfirst($params['controller']);
		$class = ucfirst($params['module']).'_Model_DbTable_'.ucfirst($params['controller']);
		$db = new $class();

		$data = $db->$getFunction($id);
		$db->$updateFunction($id, array('pinned' => (1 - $data['pinned'])));
	}

	public function disableView() {
		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
		$layout = Zend_Controller_Action_HelperBroker::getStaticHelper('layout');
		//header('Content-type: application/json');
		$viewRenderer->setNoRender();
		$layout->disableLayout();
	}
}
