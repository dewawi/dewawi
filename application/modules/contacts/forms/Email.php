<?php

class Contacts_Form_Email extends Zend_Form
{
	public function init()
	{
		$this->setName('email');

		$form = array();

		$form['email'] = new Zend_Form_Element_Text('email');
		$form['email']->setLabel('CONTACTS_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		/*$form['sender'] = new Zend_Form_Element_Text('sender');
		$form['sender']->setLabel('CONTACTS_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['recipient'] = new Zend_Form_Element_Text('recipient');
		$form['recipient']->setLabel('CONTACTS_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['cc'] = new Zend_Form_Element_Text('cc');
		$form['cc']->setLabel('CONTACTS_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['bcc'] = new Zend_Form_Element_Text('bcc');
		$form['bcc']->setLabel('CONTACTS_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');

		$form['replyto'] = new Zend_Form_Element_Text('replyto');
		$form['replyto']->setLabel('CONTACTS_EMAIL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '30');*/

		$form['subject'] = new Zend_Form_Element_Text('subject');
		$form['subject']->setLabel('CONTACTS_EMAIL_SUBJECT')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');
			//->addValidator('NotEmpty');

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

		$this->addElements($form);
	}
}
