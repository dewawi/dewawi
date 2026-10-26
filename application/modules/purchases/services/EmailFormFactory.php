<?php

class Purchases_Service_EmailFormFactory
{
	public function build(array $document, array $contact, string $controller): Contacts_Form_Emailmessage
	{
		$emailForm = new Contacts_Form_Emailmessage();

		$emailDb = new Contacts_Model_DbTable_Email();
		$emailDb->setClientId((int)$contact['clientid']);
		$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();

		$recipientOptions = [];
		$preferredRecipientId = 0;
		$selectedContactpersonId = (int)($document['contactpersonid'] ?? 0);

		foreach ($emailDb->getContactRecipients((int)$contact['id']) as $row) {
			if (empty($row['id']) || empty($row['email'])) {
				continue;
			}

			$recipientOptions[(string)$row['id']] = DEEC_ContactpersonFormatter::recipientLabel($row);

			if ($selectedContactpersonId > 0 && !$preferredRecipientId && (int)($row['contactpersonid'] ?? 0) === $selectedContactpersonId) {
				$preferredRecipientId = (int)$row['id'];
			}
		}

		$emailForm->addOptions('recipient', $recipientOptions, 'merge');
		if ($preferredRecipientId) $emailForm->setValue('recipient', (string)$preferredRecipientId);

		// Template key = controller
		$emailtemplate = $emailtemplateDb->getEmailtemplate('purchases', $controller);
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
