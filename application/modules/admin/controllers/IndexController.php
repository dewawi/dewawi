<?php

class Admin_IndexController extends DEEC_Controller_AdminAction
{
	public function indexAction()
	{
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}
