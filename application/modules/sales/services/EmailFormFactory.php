<?php

class Sales_Service_EmailFormFactory
{
	public function build(array $document, array $contact, string $controller): Contacts_Form_Emailmessage
	{
		$emailForm = new Contacts_Form_Emailmessage();

		$emailDb = new Contacts_Model_DbTable_Email();
		$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
		$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();

		// Contact emails
		$contactEmails = $emailDb->getByParentId((int)$contact['id'], 'contacts', 'contact');
		foreach ($contactEmails as $row) {
			$emailForm->recipient->addMultiOption($row['id'], $row['email']);
		}

		// Contact person emails
		$contactpersons = $contactpersonDb->getByParentId((int)$contact['id'], 'contacts', 'contact');
		foreach ($contactpersons as $contactperson) {
			$personEmails = $emailDb->getByParentId((int)$contactperson['id'], 'contacts', 'contactperson');

			foreach ($personEmails as $row) {
				$label = $row['email'] . ' (' . trim($contactperson['name1'] . ' ' . $contactperson['name2']) . ')';
				$emailForm->recipient->addMultiOption($row['id'], $label);
			}
		}

		// Template key = controller
		$emailtemplate = $emailtemplateDb->getEmailtemplate('sales', $controller);
		if ($emailtemplate) {
			if (!empty($emailtemplate['cc'])) {
				$emailForm->cc->setValue($emailtemplate['cc']);
			}

			if (!empty($emailtemplate['bcc'])) {
				$emailForm->bcc->setValue($emailtemplate['bcc']);
			}

			if (!empty($emailtemplate['replyto'])) {
				$emailForm->replyto->setValue($emailtemplate['replyto']);
			}

			$docIdField = $this->getDocumentIdField($controller);

			$search = ['[DOCID]', '[CONTACTID]'];
			$replace = [
				$document[$docIdField] ?? '',
				$document['contactid'] ?? '',
			];

			$emailForm->subject->setValue(str_replace($search, $replace, (string)$emailtemplate['subject']));
			$emailForm->body->setValue(str_replace($search, $replace, (string)$emailtemplate['body']));
		}

		return $emailForm;
	}

	protected function getDocumentIdField(string $controller): string
	{
		return $controller . 'id';
	}
}
