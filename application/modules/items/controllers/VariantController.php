<?php

class Items_VariantController extends DEEC_Controller_Action
{
	public function addAction()
	{
		$this->disableView();

		$parentId = (int)$this->_getParam(
			'parentid',
			0
		);

		$itemDb = new Items_Model_DbTable_Item();

		$parent = $itemDb->getById(
			$parentId
		);

		if(!$parent) {
			throw new Exception(
				'MESSAGES_ITEM_NOT_FOUND'
			);
		}

		if(!empty($parent['parentid'])) {
			throw new Exception(
				'MESSAGES_ITEM_VARIANT_INVALID'
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
		$variantId = (int)$this->_getParam(
			'id',
			0
		);

		$optionId = (int)$this->_getParam(
			'optionid',
			0
		);

		$this->getVariantService()->addOption(
			$variantId,
			$optionId
		);

		$this->getVariantService()->update(
			$variantId
		);

		return $this->_helper->json([
			'ok' => true,
		]);
	}

	public function deleteOptionAction()
	{
		$variantId = (int)$this->_getParam(
			'id',
			0
		);

		$optionId = (int)$this->_getParam(
			'optionid',
			0
		);

		$this->getVariantService()->deleteOption(
			$variantId,
			$optionId
		);

		$this->getVariantService()->update(
			$variantId
		);

		return $this->_helper->json([
			'ok' => true,
		]);
	}
}
