<?php

class Items_VariantController extends DEEC_Controller_Action
{
	public function addAction()
	{
		if(!$this->getRequest()->isPost()) {
			throw new Exception(
				'MESSAGES_INVALID_REQUEST'
			);
		}

		$id = $this->getVariantService()->create(
			(int)$this->_getParam('parentid', 0)
		);

		$this->_redirect(
			'/items/item/edit/id/' . $id
		);
	}

	public function addOptionAction()
	{
		if(!$this->getRequest()->isPost()) {
			throw new Exception(
				'MESSAGES_INVALID_REQUEST'
			);
		}

		$data = $this->getVariantService()->addOption(
			(int)$this->_getParam('id', 0),
			(int)$this->_getParam('optionid', 0)
		);

		return $this->_helper->json([
			'ok' => true,
			'data' => $data,
		]);
	}

	public function deleteOptionAction()
	{
		if(!$this->getRequest()->isPost()) {
			throw new Exception(
				'MESSAGES_INVALID_REQUEST'
			);
		}

		$data = $this->getVariantService()->deleteOption(
			(int)$this->_getParam('id', 0),
			(int)$this->_getParam('optionid', 0)
		);

		return $this->_helper->json([
			'ok' => true,
			'data' => $data,
		]);
	}

	protected function getVariantService(): Items_Service_Variant
	{
		return new Items_Service_Variant();
	}
}
