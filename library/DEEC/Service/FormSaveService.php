<?php

class DEEC_Service_FormSaveService
{
	public function save(DEEC_Form $form, DEEC_Model_DbTable_Entity $db, int $id, array $post, bool $partial = true): array
	{
		if ($partial) {
			if (!$form->isValidPartial($post)) {
				return [
					'ok' => false,
					'errors' => $form->getErrors(),
				];
			}

			$values = $form->getFilteredValuesPartial($post);
		} else {
			if (!$form->isValid($post)) {
				return [
					'ok' => false,
					'errors' => $form->getErrors(),
				];
			}

			$values = $form->getFilteredValues();
		}

		if (!$values) {
			return [
				'ok' => true,
				'id' => $id,
				'values' => [],
				'display' => [],
				'meta' => [],
			];
		}

		try {
			$db->updateById($id, $values);
		} catch (Exception $e) {
			return [
				'ok' => false,
				'message' => 'save_failed',
			];
		}

		$row = $db->getById($id);

		if (!$row) {
			return [
				'ok' => false,
				'message' => 'not_found',
			];
		}

		$changedFields = array_keys($values);

		return [
			'ok' => true,
			'id' => $id,
			'values' => array_intersect_key($row, array_flip($changedFields)),
			'display' => DEEC_Display::fromRow($form, $row, $changedFields),
			'meta' => [
				'recalc' => [],
			],
		];
	}
}
