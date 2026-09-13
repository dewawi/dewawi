<?php

class Contacts_Model_DbTable_Contactperson extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'contactperson';

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
