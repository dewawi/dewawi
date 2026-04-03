<?php
/**
* Class inserts neccery code for Messages	
*/
class Zend_View_Helper_EmailMessages extends Zend_View_Helper_Abstract
{
	public function EmailMessages()
	{
		return $this->view->partial('partials/email-messages.phtml', [
			'emailForm' => $this->view->emailForm,
			'attachments' => $this->view->attachments,
			'documentUrl' => $this->view->documentUrl,
			'module' => $this->view->module,
			'controller' => $this->view->controller,
			'id' => $this->view->id,
		]);
	}
}
