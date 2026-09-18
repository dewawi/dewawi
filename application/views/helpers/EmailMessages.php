<?php

public function EmailMessages(array $options = [])
{
	$options += [
		'mode' => 'send',
	];

	return $this->view->partial('partials/email-messages.phtml', [
		'emailForm' => $this->view->emailForm,
		'attachments' => $this->view->attachments,
		'documentUrl' => $this->view->documentUrl,
		'module' => $this->view->module,
		'controller' => $this->view->controller,
		'id' => $this->view->id,
		'mode' => $options['mode'],
	]);
}
