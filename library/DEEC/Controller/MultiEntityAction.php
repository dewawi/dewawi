<?php

abstract class DEEC_Controller_MultiEntityAction extends DEEC_Controller_Action
{
	protected function getParentFormClass(): string
	{
		throw new RuntimeException(
			'Parent form class must be configured'
		);
	}

	protected function getMultiElementName(): string
	{
		return $this->getRequest()->getControllerName();
	}

	protected function getCreateDefaults(array $post): array
	{
		return [];
	}

	protected function beforeMultiCreate(
		array $data,
		array $post
	): array {
		return $data;
	}

	protected function afterMultiCreate(
		int $id,
		array $row,
		array $post
	): void {
	}

	protected function beforeMultiUpdate(
		array $values,
		array $row,
		array $post
	): array {
		return $values;
	}

	protected function afterMultiUpdate(
		int $id,
		array $values,
		array $row
	): void {
	}

	public function addAction()
	{
		$this->disableView();

		$request = $this->getRequest();

		if (!$request->isPost()) {
			return $this->jsonError('invalid_request');
		}

		$post = (array)$request->getPost();

		$parentId = (int)$this->_getParam('parentid', 0);
		$parentModule = trim(
			(string)($post['parent_module'] ?? '')
		);
		$parentController = trim(
			(string)($post['parent_controller'] ?? '')
		);

		if ($parentId <= 0) {
			return $this->jsonError('missing_parent');
		}

		if (
			$parentModule === ''
			|| $parentController === ''
		) {
			return $this->jsonError(
				'missing_parent_context'
			);
		}

		$data = $this->getCreateDefaults($post);
		$data = $this->beforeMultiCreate(
			$data,
			$post
		);

		$db = $this->getDb();
		$adapter = $db->getAdapter();

		try {
			$adapter->beginTransaction();

			$newId = $db->createForParent(
				$parentId,
				$parentModule,
				$parentController,
				$data
			);

			$row = $db->getById($newId);

			if (!$row) {
				throw new RuntimeException(
					'Created row could not be loaded'
				);
			}

			$db->normalizeOrderingByRow($row);

			$row = $db->getById($newId);

			if (!$row) {
				throw new RuntimeException(
					'Normalized row could not be loaded'
				);
			}

			$this->afterMultiCreate(
				$newId,
				$row,
				$post
			);

			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollBack();

			return $this->jsonError('save_failed');
		}

		$parentForm = $this->createParentForm();

		echo $parentForm->renderMultiRow(
			$this->getMultiElementName(),
			$row
		);

		return null;
	}

	public function editAction()
	{
		$this->disableView();

		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);

		if (!$request->isPost()) {
			return $this->jsonError('invalid_request');
		}

		if ($id <= 0) {
			return $this->jsonError('not_found');
		}

		$db = $this->getDb();
		$row = $db->getById($id);

		if (!$row) {
			return $this->jsonError('not_found');
		}

		$form = $this->createRowForm();
		$post = (array)$request->getPost();

		if (!$form->isValidPartial($post)) {
			return $this->_helper->json([
				'ok' => false,
				'errors' => $this->toErrorMessages(
					$form->getErrors(),
					$form
				),
			]);
		}

		$values = $form->getFilteredValuesPartial(
			$post
		);

		$values = $this->beforeMultiUpdate(
			$values,
			$row,
			$post
		);

		if (!$values) {
			return $this->_helper->json([
				'ok' => true,
				'id' => $id,
				'values' => [],
				'display' => [],
			]);
		}

		$adapter = $db->getAdapter();

		try {
			$adapter->beginTransaction();

			$db->updateById($id, $values);

			$this->afterMultiUpdate(
				$id,
				$values,
				$row
			);

			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollBack();

			return $this->jsonError('save_failed');
		}

		$newRow = $db->getById($id);

		if (!$newRow) {
			return $this->jsonError('not_found');
		}

		$changedFields = array_keys($values);

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
			'values' => array_intersect_key(
				$newRow,
				array_flip($changedFields)
			),
			'display' => DEEC_Display::fromRow(
				$form,
				$newRow,
				$changedFields
			),
		]);
	}

	protected function createParentForm(): DEEC_Form
	{
		$className = $this->getParentFormClass();

		if (!class_exists($className)) {
			throw new RuntimeException(
				'Parent form class not found: '
				. $className
			);
		}

		$form = new $className();

		if (!$form instanceof DEEC_Form) {
			throw new RuntimeException(
				'Parent form must extend DEEC_Form: '
				. $className
			);
		}

		return $form;
	}

	protected function createRowForm(): DEEC_Form
	{
		$formClass = $this->getFormClass();

		if (!class_exists($formClass)) {
			throw new RuntimeException(
				'Row form class not found: '
				. $formClass
			);
		}

		$form = new $formClass();

		if (!$form instanceof DEEC_Form) {
			throw new RuntimeException(
				'Row form must extend DEEC_Form: '
				. $formClass
			);
		}

		$this->_helper->Options->applyFormOptions(
			$form
		);

		return $form;
	}

	protected function jsonError(string $message)
	{
		return $this->_helper->json([
			'ok' => false,
			'message' => $message,
		]);
	}

	public function deleteAction()
	{
		$this->disableView();

		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);

		if (!$request->isPost()) {
			return $this->jsonError('invalid_request');
		}

		if ($id <= 0) {
			return $this->jsonError('not_found');
		}

		$db = $this->getDb();
		$row = $db->getById($id);

		if (!$row) {
			return $this->jsonError('not_found');
		}

		$adapter = $db->getAdapter();

		try {
			$adapter->beginTransaction();

			$db->deleteById($id);
			$db->normalizeOrderingByRow($row);

			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollBack();

			return $this->jsonError('delete_failed');
		}

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
		]);
	}
}
