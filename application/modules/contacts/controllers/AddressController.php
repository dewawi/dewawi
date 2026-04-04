<?php

class Contacts_AddressController extends DEEC_Controller_Action
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
			'type' => !empty($post['type']) ? (string)$post['type'] : 'billing',
			'country' => $client['country'] ?? '0',
		];

		$addressDb = new Contacts_Model_DbTable_Address();
		$newId = $addressDb->createForParent($parentid, $parentModule, $parentController, $data);

		$row = $addressDb->getById($newId);
		if (!$row) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$rowForm = new Contacts_Form_Address();
		$this->_helper->Options->applyFormOptions($rowForm);

		$ctx = [
			'module' => 'contacts',
			'controller' => 'address',
		];

		echo $rowForm->renderMultiItem('address', $row, $ctx);
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

		$form = new Contacts_Form_Address();
		$this->_helper->Options->applyFormOptions($form);

		$post = (array)$request->getPost();

		if (!$form->isValidPartial($post)) {
			return $this->_helper->json([
				'ok' => false,
				'errors' => $this->toErrorMessages($form->getErrors(), $form),
			]);
		}

		$values = $form->getFilteredValuesPartial($post);

		$addressDb = new Contacts_Model_DbTable_Address();

		try {
			$addressDb->updateById($id, $values);
		} catch (Exception $e) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$row = $addressDb->getById($id);
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
