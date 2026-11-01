<?php

class Shops_Form_DynamicForm extends DEEC_Form
{
	public function __construct(array $config, $step = null, string $csrfToken = '', string $inquiryToken = '')
	{
		$this->setMethod('post');
 	 	$this->currentStep = $step;

		$this->addCsrfToken();
		$this->setValue('csrf_token', $csrfToken);
		$this->addElement([
			'name' => 'inquiry_token',
			'type' => 'hidden',
			'value' => $inquiryToken,
			'wrap' => false,
		]);

		foreach (($config[$step] ?? []) as $field) {
			if (!is_array($field)) continue;
			if (empty($field['name']) || empty($field['type'])) continue;

			$type = strtolower((string)$field['type']);
			$maximum = $type === 'textarea' ? 10000 : ($type === 'email' ? 254 : 1000);
			if (in_array($type, ['text', 'email', 'textarea', 'search', 'tel', 'url'], true)) {
				$field['attribs'] = is_array($field['attribs'] ?? null) ? $field['attribs'] : [];
				$configuredMaximum = (int)($field['attribs']['maxlength'] ?? 0);
				$field['attribs']['maxlength'] = $configuredMaximum > 0 ? min($maximum, $configuredMaximum) : $maximum;
			}

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

	public function isValid(array $data)
	{
		$scalarData = $data;
		$invalidFields = [];
		foreach ($this->getElements() as $name => $element) {
			if (array_key_exists($name, $scalarData) && !is_scalar($scalarData[$name]) && $scalarData[$name] !== null) {
				unset($scalarData[$name]);
				$invalidFields[] = $name;
			}
		}

		$valid = parent::isValid($scalarData);
		foreach ($invalidFields as $name) {
			$this->errors[$name][] = 'invalid';
			$valid = false;
		}

		foreach ($this->getElements() as $name => $element) {
			if (!array_key_exists($name, $data)) continue;
			if (!is_scalar($data[$name]) && $data[$name] !== null) continue;
			$maximum = (int)($element['attribs']['maxlength'] ?? 0);
			if ($maximum <= 0) continue;
			$length = function_exists('mb_strlen') ? mb_strlen((string)$data[$name], 'UTF-8') : strlen((string)$data[$name]);
			if ($length > $maximum) {
				$this->errors[$name][] = 'maxlength';
				$valid = false;
			}
		}

		return $valid;
	}
}

