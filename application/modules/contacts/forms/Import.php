<?php

class Contacts_Form_Import extends Zend_Form
{
	public function init()
	{
		$this->setName('import');
		$this->setAttrib('enctype', 'multipart/form-data');

		$form['file'] = new Zend_Form_Element_File('file');
		$form['file']->setLabel('FILE')
			->setRequired(true)
			->addValidator('Count', false, 1)
			->addValidator('Size', false, 10240000)
			->addValidator('Extension', false, 'csv');

		$form['separator'] = new Zend_Form_Element_Select('separator');
		$form['separator']->setLabel('CONTACTS_IMPORT_SEPARATOR')
			->setRequired(true)
			->addMultiOption('auto', 'CONTACTS_IMPORT_SEPARATOR_AUTO')
			->addMultiOption('comma', 'CONTACTS_IMPORT_SEPARATOR_COMMA')
			->addMultiOption('semicolon', 'CONTACTS_IMPORT_SEPARATOR_SEMICOLON')
			->setValue('auto')
			->addValidator('NotEmpty');

		$form['tagid'] = new Zend_Form_Element_Select('tagid');
		$form['tagid']->setLabel('CONTACTS_IMPORT_TAG')
			->addMultiOption('0', 'CONTACTS_IMPORT_TAG_NONE')
			->setValue('0');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setLabel('CONTACTS_IMPORT_PREVIEW')
			->setAttrib('id', 'submitbutton');

		$this->addElements($form);
	}
}
