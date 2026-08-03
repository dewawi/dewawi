<?php

class Items_PriceruleposController extends DEEC_Controller_Action
{
	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		if (!$request->isPost()) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'invalid_request',
			]);
		}

		$post = (array)$request->getPost();

		$parentId = (int)$this->_getParam(
			'parentid',
			0
		);

		$parentModule = trim(
			(string)$this->_getParam(
				'parent_module',
				''
			)
		);

		$parentController = trim(
			(string)$this->_getParam(
				'parent_controller',
				''
			)
		);

		if ($parentId <= 0) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'missing_parent',
			]);
		}

		if (
			$parentModule === ''
			|| $parentController === ''
		) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'missing_parent_context',
			]);
		}

		$data = [
			'action' => !empty($post['type'])
				? (string)$post['type']
				: 'bypercent',
			'masterid' => 0,
			'possetid' => 0,
		];

		$priceruleposDb =
			new Items_Model_DbTable_Pricerulepos();

		try {
			$newId = $priceruleposDb->createForParent(
				$parentId,
				$parentModule,
				$parentController,
				$data
			);
		} catch (Exception $e) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$row = $priceruleposDb->getById($newId);

		if (!$row) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$rowForm = new Items_Form_Pricerulepos();

		$this->_helper->Options->applyFormOptions(
			$rowForm
		);

		echo $rowForm->renderMultiItem(
			'pricerulepos',
			$row,
			[
				'module' => 'items',
				'controller' => 'pricerulepos',
			]
		);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();

		if (!$request->isPost() || $id <= 0) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		$form = new Items_Form_Pricerulepos();
		$this->_helper->Options->applyFormOptions($form);

		$post = (array)$request->getPost();

		if (!$form->isValidPartial($post)) {
			return $this->_helper->json([
				'ok' => false,
				'errors' => $this->toErrorMessages($form->getErrors(), $form),
			]);
		}

		$values = $form->getFilteredValuesPartial($post);

		$priceruleposDb = new Items_Model_DbTable_Pricerulepos();

		try {
			$priceruleposDb->updateById($id, $values);
		} catch (Exception $e) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$row = $priceruleposDb->getById($id);
		if (!$row) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		$changedFields = array_keys($values);
		$display = DEEC_Display::fromRow($form, $row, $changedFields);

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
			'values' => array_intersect_key($row, array_flip($changedFields)),
			'display' => $display,
		]);
	}
}
