<?php

class Shops_Form_Contact extends Zend_Form
{
	public function init()
	{
		$this->setName('contact');
		$this->setAction('/contact/send');

		// Add CSRF token
		$csrf = new Zend_Form_Element_Hash('csrf_token');
		$csrf->setTimeout(600); // Token expires in 10 minutes
		$this->addElement($csrf);

		// Hidden field for subject
		$subject = new Zend_Form_Element_Hidden('subject');
		$subject->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SUBJECT'));

		// Name field (required)
		$name = new Zend_Form_Element_Text('name');
		$name->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_NAME'))
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true)
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_NAME_REQUIRED')));

		// Email field (required, with email validation)
		$email = new Zend_Form_Element_Text('email');
		$email->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_EMAIL'))
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_EMAIL_REQUIRED')))
			->addValidator('EmailAddress', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_EMAIL_INVALID')))
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true);

		// Phone field (optional)
		$phone = new Zend_Form_Element_Text('phone');
		$phone->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_PHONE'));

		// Message field (required)
		$message = new Zend_Form_Element_Textarea('message');
		$message->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_MESSAGE'))
			->setAttrib('rows', '5')
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true)
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_MESSAGE_REQUIRED')));

		// Hidden field for module
		$module = new Zend_Form_Element_Hidden('module');
		$module->removeDecorator('Label')
			->setValue('shops')
			->setRequired(true);

		// Hidden field for controller
		$controller = new Zend_Form_Element_Hidden('controller');
		$controller->removeDecorator('Label')
			->setValue('contact')
			->setRequired(true);

		// Privacy checkbox (must be checked)
		$privacy = new Zend_Form_Element_Checkbox('privacy');
		$privacy->setLabel($this->getView()->translate('SHOPS_LABEL_PRIVACY'))
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true)
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_PRIVACY_REQUIRED')));

		// Submit button
		$submit = new Zend_Form_Element_Submit('submit');
		$submit->setLabel($this->getView()->translate('SHOPS_LABEL_SUBMIT'))
			   ->setAttrib('class', 'btn btn-primary btn-block');

		// Add elements to form
		$this->addElements(array($subject, $name, $email, $phone, $message, $module, $controller, $privacy, $submit));
	}
}

