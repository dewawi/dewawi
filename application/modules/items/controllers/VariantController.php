<?php

class Items_VariantController extends DEEC_Controller_Action
{
	public function indexAction()
	{
		$this->_redirect(
			'/items/item'
		);
	}

	public function addAction()
	{
		if(!$this->getRequest()->isPost()) {
			throw new Exception(
				'MESSAGES_INVALID_REQUEST'
			);
		}

		try {
			$id = $this->getVariantService()->create(
				(int)$this->_getParam(
					'parentid',
					0
				),
				(array)$this->_getParam(
					'options',
					[]
				)
			);

			return $this->_helper->json([
				'ok' => true,
				'id' => $id,
			]);
		} catch(Throwable $exception) {
			return $this->_helper->json([
				'ok' => false,
				'message' => $exception->getMessage(),
			]);
		}
	}

	protected function getVariantService(): Items_Service_Variant
	{
		return new Items_Service_Variant();
	}
}
