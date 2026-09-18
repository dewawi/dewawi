<?php

class Shops_Bootstrap extends Zend_Application_Module_Bootstrap
{
	protected function _initControllerAutoload() {
		$this->getResourceLoader()->addResourceType('controllerbase', 'controllers', 'Controller');
	}

	protected function _initPlugins() {
		$front = Zend_Controller_Front::getInstance();
		$front->registerPlugin(new Shops_Plugin_Helper());
	}
}
