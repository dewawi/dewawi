<?php

class Application_Form_Upload extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'file',
			'type' => 'file',
			'label' => 'FILE',
			'required' => true,
			'attribs' => [
				'name' => 'file[]',
				'id' => 'file',
				'multiple' => 'multiple',
				'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.csv,.zip',
			],
		]);

		$this->addElement([
			'name' => 'submit',
			'type' => 'submit',
			'label' => 'UPLOAD_SUBMIT',
			'attribs' => [
				'id' => 'submitbutton',
				'class' => 'dw-btn dw-btn--primary',
			],
			'wrap' => false,
		]);
	}
}
