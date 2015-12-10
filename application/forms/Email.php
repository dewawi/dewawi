<?php

class Application_Form_Email extends Zend_Form
{
	public function init()
	{
		$this->setName('email');

		$email = new Zend_Form_Element_Text('email');
		$email->setLabel('EMAIL')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->addValidator('EmailAddress')
			->setAttrib('size', '40');

		$subject = new Zend_Form_Element_Text('subject');
		$subject->setLabel('EMAIL_SUBJECT')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '40');

		$body = new Zend_Form_Element_Textarea('body');
		$body->setLabel('EMAIL_MESSAGE')
			/*->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('style','title','href')
			)))*/
			//->addFilter('StringTrim')
			->setAttrib('cols', '65')
			->setAttrib('rows', '25');

		$submit = new Zend_Form_Element_Button('submit',
				array('decorators'=>array('ViewHelper', array('HtmlTag', array('tag' => 'dd')))));
		$submit->setLabel('EMAIL_SEND');

		$this->addElements(array($email, $subject, $body, $submit));
	}
}
