<?php

class Processes_Service_AttachmentService
{
	public function sync(array $document, array $contact, string $controller): array
	{
		$documentId = (int)($document['id'] ?? 0);
		$contactId = (int)($document['contactid'] ?? '');
		$filename = trim((string)($document['filename'] ?? ''));

		$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();

		if ($documentId <= 0 || $contactId <= 0) {
			return [
				'contactUrl' => '',
				'documentUrl' => '',
				'attachments' => [],
			];
		}

		$directoryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory');

		$contactUrl = $directoryHelper->getUrl($contactId);
		$documentUrl = $directoryHelper->getUrl($documentId);

		if ($filename === '') {
			return [
				'contactUrl' => $contactUrl,
				'documentUrl' => $documentUrl,
				'attachments' => $emailattachmentDb->getEmailattachments($documentId, 'processes', $controller),
			];
		}

		$sourceFile = BASE_PATH . '/files/contacts/' . $contactUrl . '/' . $filename;
		$targetDir = BASE_PATH . '/files/attachments/processes/' . $controller . '/' . $documentUrl;
		$targetFile = $targetDir . '/' . $filename;

		if (
			is_dir($targetDir)
			&& file_exists($sourceFile)
			&& !file_exists($targetFile)
		) {
			if (copy($sourceFile, $targetFile)) {
				$emailattachmentDb->addEmailattachment([
					'documentid' => $documentId,
					'filename' => $filename,
					'filetype' => mime_content_type($targetFile),
					'filesize' => filesize($targetFile),
					'location' => $targetDir,
					'module' => 'processes',
					'controller' => $controller,
					'ordering' => 1,
				]);
			}
		}

		return [
			'contactUrl' => $contactUrl,
			'documentUrl' => $documentUrl,
			'attachments' => $emailattachmentDb->getEmailattachments($documentId, 'processes', $controller),
		];
	}
}
