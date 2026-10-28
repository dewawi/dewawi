<?php

class Shops_Model_FormConfig
{
	protected Shops_Model_DbTable_Inquiryform $_inquiryformDb;

	public function __construct(?Shops_Model_DbTable_Inquiryform $inquiryformDb = null)
	{
		$this->_inquiryformDb = $inquiryformDb ?? new Shops_Model_DbTable_Inquiryform();
	}

	public function getConfig(int $formId): array
	{
		$row = $this->_inquiryformDb->getInquiryform($formId);

		if (!$row) {
			return [];
		}

		$config = json_decode((string)($row['fields'] ?? ''), true);

		if (!is_array($config)) {
			return [];
		}

		$steps = [];
		$reservedFields = ['csrf_token', 'inquiry_token', 'step', 'back', 'next'];

		foreach ($config as $step => $fields) {
			if (!is_array($fields) || !$fields) continue;

			$validFields = array_values(array_filter($fields, static function($field) use ($reservedFields): bool {
				return is_array($field)
					&& is_string($field['name'] ?? null)
					&& trim($field['name']) !== ''
					&& !in_array($field['name'], $reservedFields, true)
					&& is_string($field['type'] ?? null)
					&& trim($field['type']) !== '';
			}));

			if ($validFields) {
				$steps[(string)$step] = $validFields;
			}
		}

		return $steps;
	}
}
