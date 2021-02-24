<?php

class Items_Form_Import extends Zend_Form
{
	public function init()
	{
		$this->setName('import');
		$this->setAttrib('enctype', 'multipart/form-data');

		$form['file'] = new Zend_Form_Element_File('file');
		$form['file']->setLabel('FILE')
			->setAttrib('name', 'file[]')
			->setAttrib('multiple', 'multiple')
			->setRequired(true)
			->addValidator('Count', false, 10)
			->addValidator('Size', false, 10240000)
			->addValidator('Extension', false, 'pdf,jpg,jpeg,png,gif,csv,zip');

		$form['separator'] = new Zend_Form_Element_Select('separator');
		$form['separator']->setLabel('ITEMS_IMPORT_SEPARATOR')
			->setRequired(true)
			->addMultiOption('comma', 'ITEMS_IMPORT_COMMA')
			->addMultiOption('semicolon', 'ITEMS_IMPORT_SEMICOLON')
			->addValidator('NotEmpty');

		$form['delimiter'] = new Zend_Form_Element_Select('delimiter');
		$form['delimiter']->setLabel('ITEMS_IMPORT_DELIMITER')
			->setRequired(true)
			->addMultiOption('double', 'ITEMS_IMPORT_DOUBLE_QUOTES')
			->addMultiOption('single', 'ITEMS_IMPORT_SINGLE_QUOTES')
			->addValidator('NotEmpty');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setLabel('UPLOAD_SUBMIT')
			->setAttrib('id', 'submitbutton');

		$this->addElements($form);
	}
}
