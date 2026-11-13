<?php

class Shops_Form_DynamicForm extends DEEC_Form
{
	public function __construct(array $config, $step = null, string $csrfToken = '', string $inquiryToken = '')
	{
		$config = Shops_Model_FormConfig::normalize($config);
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
		$active = $this->getActiveValues($data);
		$scalarData = $active;
		$elements = $this->elements;
		foreach($this->elements as $name => $element) {
			if(!$this->isFieldActive($name, $data)) unset($this->elements[$name]);
		}
		$invalidFields = [];
		foreach ($this->getElements() as $name => $element) {
			if (array_key_exists($name, $scalarData) && !is_scalar($scalarData[$name]) && $scalarData[$name] !== null) {
				unset($scalarData[$name]);
				$invalidFields[] = $name;
			}
		}

		$valid = parent::isValid($scalarData);
		$validated = $this->elements;
		$this->elements = $elements;
		foreach($validated as $name => $element) $this->elements[$name] = $element;
		foreach($this->elements as $name => $element) {
			if(!$this->isFieldActive($name, $data)) $this->elements[$name]['value'] = null;
		}
		foreach ($invalidFields as $name) {
			$this->errors[$name][] = 'invalid';
			$valid = false;
		}

		foreach ($this->getElements() as $name => $element) {
			if(!$this->isFieldActive($name, $data)) continue;
			if(in_array($element['type'], ['select', 'radio'], true) && isset($active[$name]) && is_scalar($active[$name]) && $active[$name] !== '' && !array_key_exists((string)$active[$name], $element['options'])) {
				$this->errors[$name][] = 'invalid';
				$valid = false;
			}
			if (!array_key_exists($name, $active)) continue;
			if (!is_scalar($active[$name]) && $active[$name] !== null) continue;
			$maximum = (int)($element['attribs']['maxlength'] ?? 0);
			if ($maximum <= 0) continue;
			$length = function_exists('mb_strlen') ? mb_strlen((string)$active[$name], 'UTF-8') : strlen((string)$active[$name]);
			if ($length > $maximum) {
				$this->errors[$name][] = 'maxlength';
				$valid = false;
			}
		}

		return $valid;
	}

	public function isFieldActive(string $name, array $data): bool
	{
		$element = $this->elements[$name];
		$parent = $element['attribs']['data-depends-on'] ?? null;
		if($parent === null) return true;
		if(!$this->isFieldActive($parent, $data)) return false;
		$trigger = $data[$parent] ?? $this->elements[$parent]['default'] ?? '';
		return is_scalar($trigger) && (string)$trigger === (string)$element['attribs']['data-depends-value'];
	}

	public function getActiveValues(array $data): array
	{
		$values = [];
		foreach($this->elements as $name => $element) {
			if(!$this->isFieldActive($name, $data)) continue;
			if(array_key_exists($name, $data)) $values[$name] = $data[$name];
			elseif($element['default'] !== null) $values[$name] = $element['default'];
		}
		return $values;
	}

	public function getFilteredValues(): array
	{
		return $this->getActiveValues(parent::getFilteredValues());
	}
}
