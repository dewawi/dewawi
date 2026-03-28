<?php

class Contacts_Form_Email extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'email',
			'label' => 'CONTACTS_EMAIL',
			'type' => 'text',
			'format' => ['type' => 'string'],
		]);

		/*$this->addElement([
			'type' => 'text',
			'name' => 'subject',
			'label' => 'CONTACTS_EMAIL_SUBJECT',
			'format' => ['type' => 'string'],
		]);

		$form['attachment'] = new Zend_Form_Element_Checkbox('attachment');
		$form['attachment']->setLabel('CONTACTS_ATTACHMENTS')
			->setValue(1);

		$form['body'] = new Zend_Form_Element_Textarea('body');
		$form['body']->setLabel('CONTACTS_EMAIL_BODY')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6','img'),
				'allowAttribs' => array('style','title','href','src','height','width')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$this->addElements($form);*/
	}
}
