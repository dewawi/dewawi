<?php

class Admin_InfoController extends DEEC_Controller_AdminAction
{
	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Admin_Form_Toolbar();

		ob_start();
		phpinfo();
		$phpinfo = ob_get_contents();
		ob_end_clean();
		$phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);

		$this->view->phpinfo = $phpinfo;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}
