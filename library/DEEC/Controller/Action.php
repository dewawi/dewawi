<?php

abstract class DEEC_Controller_Action extends Zend_Controller_Action
{
	protected function toErrorMessages(array $errors, DEEC_Form $form): array
	{
		$out = [];

		foreach ($errors as $field => $codes) {
			if (empty($codes) || !is_array($codes)) {
				continue;
			}

			$out[$field] = [];

			foreach ($codes as $code) {
				$out[$field][] = $this->translateFormErrorCode((string)$code, $form, (string)$field);
			}

			$out[$field] = array_values(array_unique(array_filter($out[$field])));
		}

		return $out;
	}

	protected function translateFormErrorCode(string $code, DEEC_Form $form, string $field = ''): string
	{
		switch ($code) {
			case 'required':
				return 'Dieses Feld ist erforderlich.';
			case 'email':
				return 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
			case 'number':
				return 'Bitte geben Sie eine Zahl ein.';
			case 'min':
				return 'Der eingegebene Wert ist zu klein.';
			case 'max':
				return 'Der eingegebene Wert ist zu groß.';
			case 'pattern':
				return 'Das Format ist ungültig.';
			default:
				return 'Ungültige Eingabe.';
		}
	}
}
