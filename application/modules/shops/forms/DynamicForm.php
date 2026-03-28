<?php

class Shops_Form_DynamicForm extends DEEC_Form
{
	public function __construct(array $config, $step = null)
	{
		$this->setMethod('post');
		//print_r($config);
 	 	$this->currentStep = $step;

		$this->addCsrfToken();

		foreach ($config[$step] as $field) {
			if (!is_array($field)) continue;
			if (empty($field['name']) || empty($field['type'])) continue;

			$this->addElement($field);

			// Dependent fields
			if (!empty($field['depends']) && is_array($field['depends'])) {
				foreach ($field['depends'] as $triggerValue => $deps) {
					foreach ($deps as $dep) {
						$dep['depends_on'] = $field['name'];
						$dep['depends_value'] = $triggerValue;
						$this->addElement($dep);
					}
				}
			}
		}

		// Submit button
		$this->addElement([
			'name' => 'next',
			'type' => 'submit',
			'label' => 'Weiter',
			'attribs' => ['class' => 'btn btn-primary'],
		]);
	}
}

