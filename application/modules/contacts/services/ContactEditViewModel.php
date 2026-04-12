<?php

class Contacts_Service_ContactEditViewModel
{
	public function build(int $contactId, array $user, array $contactRow): array
	{
		$emailDb = new Contacts_Model_DbTable_Email();
		$phoneDb = new Contacts_Model_DbTable_Phone();
		$internetDb = new Contacts_Model_DbTable_Internet();
		$bankAccountDb = new Contacts_Model_DbTable_Bankaccount();
		$addressDb = new Contacts_Model_DbTable_Address();
		$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
		$commentDb = new Application_Model_DbTable_Comment();
		$downloadsDb = new Contacts_Model_DbTable_Download();
		$downloadtrackingsDb = new Contacts_Model_DbTable_Downloadtracking();

		$phone = $phoneDb->getByParentId($contactId, 'contacts', 'contact');
		$email = $emailDb->getByParentId($contactId, 'contacts', 'contact');
		$internet = $internetDb->getByParentId($contactId, 'contacts', 'contact');
		$bankAccount = $bankAccountDb->getByParentId($contactId, 'contacts', 'contact');
		$address = $addressDb->getByParentId($contactId, 'contacts', 'contact');

		$contactpersons = $contactpersonDb->getByParentId($contactId, 'contacts', 'contact');

		$emailContactPersons = [];
		foreach ((array) $contactpersons as $cp) {
			$cpId = (int) ($cp['id'] ?? 0);
			if ($cpId <= 0) {
				continue;
			}

			$emailContactPersons[$cpId] = $emailDb->getByParentId($cpId, 'contacts', 'contactperson');
		}

		$comments = $commentDb->getComments($contactId, 'contacts', 'contact');

		$get = new Contacts_Model_Get();
		$tags = $get->tags('contacts', 'contact', $contactRow['id']);
		$history = $get->history($contactRow['contactid']);

		$files = $this->getContactFiles($contactId);

		$downloads = $downloadsDb->getDownloads($contactId);
		$downloadtrackings = $downloadtrackingsDb->getDownloadtrackings($contactId);

		$clientid = $user['clientid'] ?? 0;
		$dir1 = substr((string) $clientid, 0, 1);
		$dir2 = (strlen((string) $clientid) > 1) ? substr((string) $clientid, 1, 1) : '0';
		$downloadsurl = $dir1 . '/' . $dir2 . '/' . $clientid . '/';

		$emailForm = new Contacts_Form_Emailmessage();

		$recipientOptions = [];

		foreach ((array) $email as $option) {
			if (empty($option['id']) || empty($option['email'])) {
				continue;
			}

			$recipientOptions[(string) $option['id']] = (string) $option['email'];
		}

		foreach ((array) $contactpersons as $contactperson) {
			$contactpersonId = (int) ($contactperson['id'] ?? 0);
			if ($contactpersonId <= 0) {
				continue;
			}

			$personEmails = $emailDb->getByParentId($contactpersonId, 'contacts', 'contactperson');

			foreach ((array) $personEmails as $row) {
				if (empty($row['id']) || empty($row['email'])) {
					continue;
				}

				$personName = trim(
					((string) ($contactperson['name1'] ?? '')) . ' ' .
					((string) ($contactperson['name2'] ?? ''))
				);

				$label = (string) $row['email'];
				if ($personName !== '') {
					$label .= ' (' . $personName . ')';
				}

				$recipientOptions[(string) $row['id']] = $label;
			}
		}

		$emailForm->addOptions('recipient', $recipientOptions, 'merge');

		$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();
		$emailtemplate = $emailtemplateDb->getEmailtemplate('contacts', 'contact');

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

			$emailForm->setValue('subject', (string) ($emailtemplate['subject'] ?? ''));
			$emailForm->setValue('body', (string) ($emailtemplate['body'] ?? ''));
		}

		$Directory = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory');
		$url = $Directory->getUrl($contactId);

		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
		$attachments = $emailattachmentDb->getEmailattachments($contactId, 'contacts', 'contact');

		return [
			'tags' => $tags,
			'history' => $history,
			'files' => $files,
			'url' => $url,
			'emailForm' => $emailForm,
			'attachments' => $attachments,
			'address' => $address,
			'phone' => $phone,
			'email' => $email,
			'internet' => $internet,
			'bankAccount' => $bankAccount,
			'contactpersons' => $contactpersons,
			'emailContactPersons' => $emailContactPersons,
			'comments' => $comments,
			'downloads' => $downloads,
			'downloadsurl' => $downloadsurl,
			'downloadtrackings' => $downloadtrackings,
		];
	}

	protected function getContactFiles(int $contactId): array
	{
		$files = [];
		$path = BASE_PATH . '/files/contacts/' . $contactId;

		if (file_exists($path) && is_dir($path)) {
			$files['contactSpecific'] = [];

			if ($handle = opendir($path)) {
				while (false !== ($entry = readdir($handle))) {
					if (substr($entry, 0, 1) !== '.') {
						$files['contactSpecific'][] = $entry;
					}
				}
				closedir($handle);
			}
		}

		return $files;
	}
}
