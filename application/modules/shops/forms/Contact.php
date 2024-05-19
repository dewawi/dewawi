<?php

class Shops_Form_Contact extends Zend_Form
{
	public function init()
	{
		$this->setName('contact');
		$this->setAction('/contact/send');

        $name = new Zend_Form_Element_Text('name');
        $name->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', 'Name')
			->setRequired(true);

        $email = new Zend_Form_Element_Text('email');
        $email->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', 'Email')
			->addValidator('EmailAddress')
			->setRequired(true);

        $phone = new Zend_Form_Element_Text('phone');
        $phone->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', 'Phone')
			->setRequired(true);

        $message = new Zend_Form_Element_Textarea('message');
        $message->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', 'Message')
			->setAttrib('rows', '5')
			->setRequired(true);

        $privacy = new Zend_Form_Element_Checkbox('privacy');
        $privacy->setLabel('Ich habe die Datenschutzerklärung gelesen und erkläre mich mit dieser einverstanden.')
                    ->setRequired(true)
                    ->addValidator('NotEmpty');

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Submit')
               ->setAttrib('class', 'btn btn-primary btn-block');

        $this->addElements(array($name, $email, $phone, $message, $privacy, $submit));
	}
}
