<?php

class Contacts_Model_DbTable_Tag extends Application_Model_DbTable_Tagentity
{
	protected string $parentField = 'entityid';

	public function getByParentId(int $parentid, string $module, string $controller): array
	{
		return $this->getTagEntities($module, $controller, $parentid);
	}

	public function getSelectOptions(string $module = 'contacts', string $controller = 'contact'): array
	{
		$tagDb = new Application_Model_DbTable_Tag();
		$tagDb->setClientId($this->getClientId());

		return $tagDb->getTags($module, $controller, 0);
	}

	public function assignToContacts(array $contactIds, int $tagId): array
	{
		$assignedIds = $this->getAssignedParentIds(
			$contactIds,
			'contacts',
			'contact',
			$tagId,
			true
		);

		$missingIds = array_values(array_diff($contactIds, $assignedIds));

		foreach($missingIds as $contactId) {
			$this->createForParent((int)$contactId, 'contacts', 'contact', [
				'tagid' => $tagId,
			]);
		}

		return [
			'changed' => count($missingIds),
			'unchanged' => count($assignedIds),
		];
	}

	public function removeFromContacts(array $contactIds, int $tagId): array
	{
		$assignedIds = $this->getAssignedParentIds(
			$contactIds,
			'contacts',
			'contact',
			$tagId,
			true
		);

		$this->deleteForParents(
			$assignedIds,
			'contacts',
			'contact',
			$tagId
		);

		return [
			'changed' => count($assignedIds),
			'unchanged' => count($contactIds) - count($assignedIds),
		];
	}
}
