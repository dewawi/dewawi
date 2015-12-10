<?php

class Contacts_Bootstrap extends Zend_Application_Module_Bootstrap
{
	protected function _initPlugins() {
		$front = Zend_Controller_Front::getInstance();
		$front->registerPlugin(new Contacts_Plugin_Helper());
	}
}
