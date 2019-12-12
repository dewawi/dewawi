<?php

class Application_Form_Upload extends Zend_Form
{
	public function init()
	{
		$this->setName('upload');
		$this->setAttrib('enctype', 'multipart/form-data');

		$file = new Zend_Form_Element_File('file');
		$file->setLabel('FILE')
			->setRequired(true)
			->addValidator('Count', false, 1)
			->addValidator('Size', false, 10240000)
			->addValidator('Extension', false, 'pdf,jpg,jpeg,png,gif,csv');

		$submit = new Zend_Form_Element_Submit('submit');
		$submit->setLabel('UPLOAD_SUBMIT')
			->setAttrib('id', 'submitbutton');

		$this->addElements(array($file, $submit));
	}
}
