<?php

class Processes_Service_AttachmentService
{
	public function sync(array $document, array $contact, string $controller): array
	{
		return (new Contacts_Model_DbTable_Emailattachment())->syncDocument($document, 'processes', $controller);
	}
}
