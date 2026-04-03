<?php

class Sales_Service_EditAjaxSaveService
{
	public function save(array $cfg): array
	{
		/** @var DEEC_Form $form */
		$form = $cfg['form'];
		$post = (array)$cfg['post'];
		$id = (int)$cfg['id'];
		$db = $cfg['db'];
		$loadMethod = (string)$cfg['loadMethod'];
		$updateMethod = (string)$cfg['updateMethod'];

		if (!$form->isValidPartial($post)) {
			return [
				'ok' => false,
				'errors' => $this->toErrorMessages($form->getErrors(), $form),
			];
		}

		$values = $form->getFilteredValuesPartial($post);

		try {
			$db->$updateMethod($id, $values);
		} catch (Exception $e) {
			return [
				'ok' => false,
				'message' => 'save_failed',
			];
		}

		$row = $db->$loadMethod($id);
		$changedFields = array_keys($values);
		$display = DEEC_Display::fromRow($form, $row, $changedFields);

		return [
			'ok' => true,
			'id' => $id,
			'values' => array_intersect_key($row, array_flip($changedFields)),
			'display' => $display,
			'meta' => [
				'recalc' => [],
			],
		];
	}

	protected function toErrorMessages(array $errors, DEEC_Form $form): array
	{
		$out = [];

		foreach ($errors as $field => $codes) {
			$messages = [];
			foreach ($codes as $code) {
				switch ($code) {
					case 'required':
						$messages[] = 'This field is required.';
						break;
					case 'email':
						$messages[] = 'Please enter a valid email address.';
						break;
					case 'number':
						$messages[] = 'Please enter a number.';
						break;
					case 'min':
						$messages[] = 'The value is too small.';
						break;
					case 'max':
						$messages[] = 'The value is too large.';
						break;
					case 'pattern':
						$messages[] = 'The format is invalid.';
						break;
					default:
						$messages[] = 'Invalid value.';
						break;
				}
			}

			$out[$field] = implode(' ', $messages);
		}

		return $out;
	}
}
