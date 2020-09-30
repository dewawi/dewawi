<?php

class Application_Controller_Action_Helper_Validate extends Zend_Controller_Action_Helper_Abstract
{
	public function direct() {
		$class = ucfirst($params['module']).'_Form_'.ucfirst($params['controller']);
		$form = new $class();

		$optionsHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Options');
		$options = $optionsHelper->getOptions($form);

		$form->isValid($this->_getAllParams());
		$messages = $form->getMessages();

		$this->disableView();
		echo Zend_Json::encode($messages);
	}

	public function disableView() {
		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
		$layout = Zend_Controller_Action_HelperBroker::getStaticHelper('layout');
		header('Content-type: application/json');
		$viewRenderer->setNoRender();
		$layout->disableLayout();
	}
}
