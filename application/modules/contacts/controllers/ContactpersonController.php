<?php

class Contacts_ContactpersonController extends DEEC_Controller_MultiEntityAction
{
	protected function getParentFormClass(): string
	{
		return Contacts_Form_Contact::class;
	}

	protected function getRowsByParentId(int $parentId)
	{
		$options = $this->getDb()->getSelectOptionsForContact($parentId);
		$items = [];

		foreach ($options as $id => $label) {
			$items[] = [
				'id' => (int)$id,
				'displayname' => $label,
			];
		}

		return $this->_helper->json([
			'ok' => true,
			'items' => $items,
		]);
	}
}
