<?php

class Purchases_Service_EditFormFactory
{
	public function create(string $formClass): array
	{
		$form = new $formClass();

		$optionsHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Options');
		$options = $optionsHelper->applyFormOptions($form);

		return [
			'form' => $form,
			'options' => $options,
		];
	}

	public function populate(DEEC_Form $form, array $document, int $id, string $table, string $controller): DEEC_Form
	{
		$locale = Zend_Registry::get('Zend_Locale');

		$displayValues = DEEC_Display::rowToFormValues($form, $document, $locale);
		$form->setValues($displayValues);

		$multiEntityLoader = Zend_Controller_Action_HelperBroker::getStaticHelper('MultiEntityLoader');
		$multiEntityLoader->populate($form, $id, $table, $controller);

		return $form;
	}
}
