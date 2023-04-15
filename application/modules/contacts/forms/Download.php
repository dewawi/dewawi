<?php

class Contacts_Form_Download extends Zend_Form
{
	public function init()
	{
		$this->setName('upload');
		$this->setAttrib('enctype', 'multipart/form-data');

		$form = array();

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('CONTACTS_DOWNLOAD_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['setid'] = new Zend_Form_Element_Select('setid');
		$form['setid']->setLabel('CONTACTS_DOWNLOAD_SET')
			->addMultiOption(0, 'CONTACTS_NOT_CATEGORIZED')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['filename'] = new Zend_Form_Element_Text('filename');
		$form['filename']->setLabel('CONTACTS_DOWNLOAD_FILENAME')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '50');

		$form['file'] = new Zend_Form_Element_File('file');
		$form['file']->setLabel('FILE')
			->setAttrib('name', 'file[]')
			->setAttrib('multiple', 'multiple')
			->setRequired(true)
			->addValidator('Count', false, 10)
			->addValidator('Size', false, 10240000)
			->addValidator('Extension', false, 'pdf,jpg,jpeg,png,gif,csv,zip');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setLabel('UPLOAD_SUBMIT')
			->setAttrib('id', 'submitbutton');

		$this->addElements($form);
	}
}
