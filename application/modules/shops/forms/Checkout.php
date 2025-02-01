<?php

class Shops_Form_Checkout extends Zend_Form
{
	public function init()
	{
		$this->setName('checkout');
		$this->setAction('/checkout/send');

		// Add CSRF token
		$csrf = new Zend_Form_Element_Hash('csrf_token');
		$csrf->setTimeout(600); // Token expires in 10 minutes
		$this->addElement($csrf);

		// Hidden field for subject
		$subject = new Zend_Form_Element_Hidden('subject');
		$subject->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SUBJECT'));

		// Billing name field (required)
		$billingname = new Zend_Form_Element_Text('billingname');
		$billingname->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_BILLING_NAME'))
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true)
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_NAME_REQUIRED')));

		// Billing company field (required)
		$billingcompany = new Zend_Form_Element_Text('billingcompany');
		$billingcompany->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_BILLING_COMPANY'));
			//->setAttrib('required', 'required') // HTML5 'required' Attribut
			//->setRequired(true)
			//->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_NAME_REQUIRED')));

		// Billing department field
		$billingdepartment = new Zend_Form_Element_Text('billingdepartment');
		$billingdepartment->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_BILLING_DEPARTMENT'));

		// Billing street field (required)
		$billingstreet = new Zend_Form_Element_Text('billingstreet');
		$billingstreet->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_BILLING_STREET'))
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true)
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_NAME_REQUIRED')));

		// Billing postcode field (required)
		$billingpostcode = new Zend_Form_Element_Text('billingpostcode');
		$billingpostcode->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_BILLING_POSTCODE'))
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true)
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_NAME_REQUIRED')));

		// Billing city field (required)
		$billingcity = new Zend_Form_Element_Text('billingcity');
		$billingcity->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_BILLING_CITY'))
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true)
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_NAME_REQUIRED')));

		// Billing country field (required)
		$billingcountry = new Zend_Form_Element_Select('billingcountry');
		$billingcountry->setLabel('CONTACTS_COUNTRY')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		// Billing phone field (optional)
		$billingphone = new Zend_Form_Element_Text('billingphone');
		$billingphone->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_BILLING_PHONE'));

		// Checkbox for different shipping address
		$differentshippingaddress = new Zend_Form_Element_Checkbox('differentshippingaddress');
		$differentshippingaddress->setLabel('SHOPS_PLACEHOLDER_DIFFERENT_SHIPPING_ADDRESS')
			->setDecorators(array('ViewHelper', 'Label'));

		// Shipping name field
		$shippingname = new Zend_Form_Element_Text('shippingname');
		$shippingname->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SHIPPING_NAME'));

		// Shipping company field
		$shippingcompany = new Zend_Form_Element_Text('shippingcompany');
		$shippingcompany->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SHIPPING_COMPANY'));

		// Shipping department field
		$shippingdepartment = new Zend_Form_Element_Text('shippingdepartment');
		$shippingdepartment->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SHIPPING_DEPARTMENT'));

		// Shipping street field
		$shippingstreet = new Zend_Form_Element_Text('shippingstreet');
		$shippingstreet->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SHIPPING_STREET'));

		// Shipping postcode field
		$shippingpostcode = new Zend_Form_Element_Text('shippingpostcode');
		$shippingpostcode->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SHIPPING_POSTCODE'));

		// Shipping city field
		$shippingcity = new Zend_Form_Element_Text('shippingcity');
		$shippingcity->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SHIPPING_CITY'));

		// Shipping country field
		$shippingcountry = new Zend_Form_Element_Select('shippingcountry');
		$shippingcountry->setLabel('CONTACTS_COUNTRY');

		// Shipping phone field (optional)
		$shippingphone = new Zend_Form_Element_Text('shippingphone');
		$shippingphone->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_SHIPPING_PHONE'));

		// Add shipping elements to the display group
		$this->addDisplayGroup(
			array($shippingname, $shippingcompany, $shippingdepartment, $shippingstreet, $shippingpostcode, $shippingcity, $shippingcountry, $shippingphone),
			'shippingContainer',
			array(
				'order' => 11,
				'decorators' => array(
					'FormElements', // Includes the form elements
					array('HtmlTag', array('tag' => 'div', 'id' => 'shipping-container', 'style' => 'display:none')) // Wrap the group in a div without a label
				)
			)
		);

		// Email field (required, with email validation)
		$email = new Zend_Form_Element_Text('email');
		$email->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_EMAIL'))
			->addValidator('NotEmpty', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_EMAIL_REQUIRED')))
			->addValidator('EmailAddress', true, array('messages' => $this->getView()->translate('SHOPS_ERROR_EMAIL_INVALID')))
			->setAttrib('required', 'required') // HTML5 'required' Attribut
			->setRequired(true);

		// Message field (required)
		$message = new Zend_Form_Element_Textarea('message');
		$message->removeDecorator('Label')
			->setAttrib('class', 'form-control')
			->setAttrib('placeholder', $this->getView()->translate('SHOPS_PLACEHOLDER_MESSAGE'))
			->setAttrib('rows', '5');

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
		$submit->setLabel($this->getView()->translate('SHOPS_LABEL_ORDER'))
			   ->setAttrib('class', 'btn btn-primary btn-block');

		// Add elements to form
		$this->addElements(array($subject, $billingname, $billingcompany, $billingdepartment, $billingstreet, $billingpostcode, $billingcity, $billingcountry, $billingphone, $differentshippingaddress, $shippingname, $shippingcompany, $shippingdepartment, $shippingstreet, $shippingpostcode, $shippingcity, $shippingcountry, $shippingphone, $email, $message, $module, $controller, $privacy, $submit));
	}
}

