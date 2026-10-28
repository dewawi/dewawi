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
			return $this->_helper->json(['ok' => false, 'message' => 'MESSAGES_INVALID_REQUEST']);
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
			return $this->sendVariantError($exception);
		}
	}

	public function editAction()
	{
		if(!$this->getRequest()->isPost()) {
			return $this->_helper->json(['ok' => false, 'message' => 'MESSAGES_INVALID_REQUEST']);
		}

		try {
			$data = $this->getVariantService()->updateOptions(
				(int)$this->_getParam(
					'id',
					0
				),
				(array)$this->_getParam(
					'options',
					[]
				)
			);

			return $this->_helper->json([
				'ok' => true,
				'data' => $data,
			]);
		} catch(Throwable $exception) {
			return $this->sendVariantError($exception);
		}
	}

	protected function sendVariantError(Throwable $exception)
	{
		$message = $exception->getMessage();
		if (get_class($exception) !== Exception::class || !in_array($message, [
			'MESSAGES_ITEM_NOT_FOUND', 'MESSAGES_ITEM_VARIANT_INVALID', 'MESSAGES_ITEM_VARIANT_OPTIONS_REQUIRED',
			'MESSAGES_ITEM_OPTION_INVALID', 'MESSAGES_ITEM_VARIANT_EXISTS',
		], true)) {
			error_log((string)$exception);
			$message = 'save_failed';
		}
		return $this->_helper->json(['ok' => false, 'message' => $message]);
	}

	protected function getVariantService(): Items_Service_Variant
	{
		return new Items_Service_Variant();
	}
}
