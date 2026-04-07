<?php

class Purchases_Service_ReadonlyFormFactory
{
	public function build(string $formClass, array $document, $locale): DEEC_Form
	{
		$form = new $formClass();

		$optionsHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Options');
		$optionsHelper->applyFormOptions($form);

		$displayValues = DEEC_Display::rowToFormValues($form, $document, $locale);
		$form->setValues($displayValues);
		$form->setMode('readonly');

		return $form;
	}
}
