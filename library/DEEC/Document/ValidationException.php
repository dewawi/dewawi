<?php

class DEEC_Document_ValidationException extends RuntimeException
{
	protected array $validation;

	public function __construct(array $validation)
	{
		parent::__construct('Document finalization validation failed');
		$this->validation = $validation;
	}

	public function getValidation(): array
	{
		return $this->validation;
	}
}
