<?php

class Processes_Bootstrap extends Zend_Application_Module_Bootstrap
{
	protected function _initPlugins() {
		$front = Zend_Controller_Front::getInstance();
		$front->registerPlugin(new Processes_Plugin_Helper());
	}
}
