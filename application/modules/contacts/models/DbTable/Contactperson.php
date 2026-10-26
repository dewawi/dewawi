<?php

class Contacts_Model_DbTable_Contactperson extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'contactperson';

	public function getSelectOptionsForContact(int $contactId): array
	{
		$options = [];

		foreach ($this->getByParentId($contactId, 'contacts', 'contact') as $person) {
			$options[(string)$person['id']] = DEEC_ContactpersonFormatter::displayName($person);
		}

		return $options;
	}

	public function belongsToContact(int $contactpersonId, int $contactId): bool
	{
		$person = $this->getById($contactpersonId);

		return $person
			&& (int)$person['parentid'] === $contactId
			&& $person['module'] === 'contacts'
			&& $person['controller'] === 'contact';
	}

	protected function getOrderingContextFields(array $row): array
	{
		return array_values(array_diff(parent::getOrderingContextFields($row), ['position']));
	}

	public function deleteById(int $id): void
	{
		$emailDb = new Contacts_Model_DbTable_Email();
		$emails = $emailDb->getByParentId($id, 'contacts', 'contactperson');

		foreach ($emails as $email) {
			$emailDb->deleteById((int)$email['id']);
		}

		parent::deleteById($id);
	}
}
