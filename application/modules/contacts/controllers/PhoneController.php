<?php

class Contacts_PhoneController extends DEEC_Controller_Action
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

		$parentid = (int)$this->_getParam('parent_id', 0);

		if ($parentid <= 0) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'missing_parent',
			]);
		}

		$parentModule = !empty($post['parent_module']) ? (string)$post['parent_module'] : 'contacts';
		$parentController = !empty($post['parent_controller']) ? (string)$post['parent_controller'] : 'contact';

		$client = Zend_Registry::get('Client');

		$data = [
			'type' => !empty($post['type']) ? (string)$post['type'] : 'phone',
		];

		$phoneDb = new Contacts_Model_DbTable_Phone();
		$newId = $phoneDb->createForParent($parentid, $parentModule, $parentController, $data);

		$row = $phoneDb->getById($newId);
		if (!$row) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$rowForm = new Contacts_Form_Phone();
		$this->_helper->Options->applyFormOptions($rowForm);

		$ctx = [
			'module' => 'contacts',
			'controller' => 'phone',
		];

		echo $rowForm->renderMultiItem('phone', $row, $ctx);
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

		$form = new Contacts_Form_Phone();
		$this->_helper->Options->applyFormOptions($form);

		$post = (array)$request->getPost();

		if (!$form->isValidPartial($post)) {
			return $this->_helper->json([
				'ok' => false,
				'errors' => $this->toErrorMessages($form->getErrors(), $form),
			]);
		}

		$values = $form->getFilteredValuesPartial($post);

		$phoneDb = new Contacts_Model_DbTable_Phone();

		try {
			$phoneDb->updateById($id, $values);
		} catch (Exception $e) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$row = $phoneDb->getById($id);
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
