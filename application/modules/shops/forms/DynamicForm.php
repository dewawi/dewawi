<?php

class Shops_Form_DynamicForm extends DEEC_Form
{
	public function __construct(array $config, $step = null)
	{
		$this->setMethod('post');
		//print_r($config);
 	 	$this->currentStep = $step;

		// CSRF token
		$this->addElement([
			'name' => 'csrf_token',
			'type' => 'hidden',
			'value' => bin2hex(random_bytes(16)),
		]);

		//foreach ($config as $n => $stepConfig) {
			//if ($n > $step) continue;
			foreach ($config[$step] as $field) {
				if (empty($field['name']) || empty($field['type'])) continue;

				$el = [
					'name' => $field['name'],
					'type' => $field['type'],
					'label' => $field['label'] ?? $field['name'],
					'description' => $field['description'] ?? '',
					'unit' => $field['unit'] ?? null,
					'required' => !empty($field['required']),
					'default' => $field['default'] ?? null,
					'min' => $field['min'] ?? null,
					'max' => $field['max'] ?? null,
					'pattern' => $field['pattern'] ?? null,
					'step' => $field['step'] ?? null,
	 				'col' => isset($field['col']) ? (int)$field['col'] : null,
				];
				if ($field['type'] === 'select' && !empty($field['options'])) {
					$el['options'] = $field['options'];
				}
				$this->addElement($el);

				// Dependent fields
				if (!empty($field['depends']) && is_array($field['depends'])) {
					foreach ($field['depends'] as $triggerValue => $deps) {
						foreach ($deps as $dep) {
							$d = [
								'name' => $dep['name'],
								'type' => $dep['type'],
								'label' => $dep['label'] ?? $dep['name'],
								'description' => $dep['description'] ?? '',
								'unit' => $dep['unit'] ?? null,
								'required' => !empty($dep['required']),
								'default' => $dep['default'] ?? null,
								'min' => $dep['min'] ?? null,
								'max' => $dep['max'] ?? null,
								'pattern' => $dep['pattern'] ?? null,
								'depends_on' => $field['name'],
								'depends_value'=> $triggerValue,
					 			'col' => isset($dep['col']) ? (int)$dep['col'] : null,
							];
							if ($dep['type'] === 'select' && !empty($dep['options'])) {
								$d['options'] = $dep['options'];
							}
							$this->addElement($d);
						}
					}
				}
			}
		//}

		// Submit button
		$this->addElement([
			'name' => 'next',
			'type' => 'submit',
			'label' => 'Weiter',
			'class' => 'btn btn-primary'
		]);
	}
}

