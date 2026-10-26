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

	protected function validateMultiCreate(
		int $parentId,
		string $parentModule,
		string $parentController,
		array $data,
		array $post
	): ?string {
		return $this->validateContactParent(
			$parentId,
			$parentModule,
			$parentController
		);
	}

	protected function validateMultiRow(
		int $id,
		array $row,
		array $post
	): ?string {
		return $this->validateContactParent(
			(int)($row['entityid'] ?? 0),
			(string)($row['module'] ?? ''),
			(string)($row['controller'] ?? '')
		);
	}

	protected function beforeMultiUpdate(
		array $values,
		array $row,
		array $post
	): array {
		if (!array_key_exists('tag', $values)) {
			return $values;
		}

		$title = trim(strip_tags(
			(string)$values['tag']
		));

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

		$tagDb = new Application_Model_DbTable_Tag();
		$tag = $tagDb->getById($tagId);

		if (
			!$tag
			|| (string)$tag['module'] !== 'contacts'
			|| (string)$tag['controller'] !== 'contact'
			|| (int)$tag['shopid'] !== 0
		) {
			return $this->view->translate('MESSAGES_TAG_INVALID');
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
			return $this->view->translate('MESSAGES_TAG_ALREADY_ASSIGNED');
		}

		return null;
	}

	protected function validateContactParent(int $parentId, string $module, string $controller): ?string
	{
		if ($module !== 'contacts' || $controller !== 'contact') {
			return $this->view->translate('MESSAGES_TAG_CONTEXT_INVALID');
		}

		$contactDb = new Contacts_Model_DbTable_Contact();

		return $contactDb->getById($parentId) ? null : $this->view->translate('MESSAGES_CONTACT_NOT_FOUND');
	}
}
