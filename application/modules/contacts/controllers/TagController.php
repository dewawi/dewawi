<?php

class Contacts_TagController extends DEEC_Controller_MultiEntityAction
{
	protected function getParentFormClass(): string
	{
		return Contacts_Form_Contact::class;
	}

	protected function getCreateDefaults(
		array $post
	): array {
		return [
			'tagid' => 0,
		];
	}

	protected function beforeMultiUpdate(
		array $values,
		array $row,
		array $post
	): array {
		if (!array_key_exists('tag', $values)) {
			return $values;
		}

		$title = trim(
			(string)$values['tag']
		);

		unset($values['tag']);

		if ($title === '') {
			$values['tagid'] = 0;

			return $values;
		}

		$tagDb = new Application_Model_DbTable_Tag();

		$values['tagid'] = $tagDb->findOrCreate(
			$title,
			'contacts',
			'contact'
		);

		return $values;
	}

	protected function validateMultiUpdate(
		int $id,
		array $values,
		array $row,
		array $post
	): ?string {
		$tagId = (int)(
			$values['tagid']
			?? $row['tagid']
			?? 0
		);

		if ($tagId <= 0) {
			return null;
		}

		$tagEntityDb = $this->getDb();

		if (!$tagEntityDb instanceof Contacts_Model_DbTable_Tag) {
			return 'save_failed';
		}

		$parentId = (int)(
			$row['entityid'] ?? 0
		);

		$module = (string)(
			$row['module'] ?? ''
		);

		$controller = (string)(
			$row['controller'] ?? ''
		);

		if (
			$tagEntityDb->existsForParent(
				$parentId,
				$module,
				$controller,
				$tagId,
				$id
			)
		) {
			return 'TAG_ALREADY_EXISTS';
		}

		return null;
	}
}
