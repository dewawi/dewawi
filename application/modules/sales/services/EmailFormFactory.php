<?php

class Sales_Service_EmailFormFactory
{
	public function build(array $document, array $contact, string $controller): Contacts_Form_Emailmessage
	{
		$emailForm = new Contacts_Form_Emailmessage();

		$emailDb = new Contacts_Model_DbTable_Email();
		$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
		$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();

		$recipientOptions = [];

		// Contact emails
		$contactEmails = $emailDb->getByParentId((int) $contact['id'], 'contacts', 'contact');
		foreach ($contactEmails as $row) {
			if (empty($row['id']) || empty($row['email'])) {
				continue;
			}

			$recipientOptions[(string) $row['id']] = (string) $row['email'];
		}

		// Contact person emails
		$contactpersons = $contactpersonDb->getByParentId((int) $contact['id'], 'contacts', 'contact');
		foreach ($contactpersons as $contactperson) {
			$personEmails = $emailDb->getByParentId((int) $contactperson['id'], 'contacts', 'contactperson');

			foreach ($personEmails as $row) {
				if (empty($row['id']) || empty($row['email'])) {
					continue;
				}

				$personName = trim(((string) ($contactperson['name1'] ?? '')) . ' ' . ((string) ($contactperson['name2'] ?? '')));
				$label = (string) $row['email'];

				if ($personName !== '') {
					$label .= ' (' . $personName . ')';
				}

				$recipientOptions[(string) $row['id']] = $label;
			}
		}

		$emailForm->addOptions('recipient', $recipientOptions, 'merge');

		// Template key = controller
		$emailtemplate = $emailtemplateDb->getEmailtemplate('sales', $controller);
		if ($emailtemplate) {
			if (!empty($emailtemplate['cc'])) {
				$emailForm->setValue('cc', (string) $emailtemplate['cc']);
			}

			if (!empty($emailtemplate['bcc'])) {
				$emailForm->setValue('bcc', (string) $emailtemplate['bcc']);
			}

			if (!empty($emailtemplate['replyto'])) {
				$emailForm->setValue('replyto', (string) $emailtemplate['replyto']);
			}

			$docIdField = $this->getDocumentIdField($controller);

			$search = ['[DOCID]', '[CONTACTID]'];
			$replace = [
				(string) ($document[$docIdField] ?? ''),
				(string) ($document['contactid'] ?? ''),
			];

			$emailForm->setValue(
				'subject',
				str_replace($search, $replace, (string) ($emailtemplate['subject'] ?? ''))
			);

			$emailForm->setValue(
				'body',
				str_replace($search, $replace, (string) ($emailtemplate['body'] ?? ''))
			);
		}

		return $emailForm;
	}

	protected function getDocumentIdField(string $controller): string
	{
		return $controller . 'id';
	}
}
