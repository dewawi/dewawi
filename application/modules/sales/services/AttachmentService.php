<?php

class Sales_Service_AttachmentService
{
	public function sync(array $document, array $contact, string $controller): array
	{
		$result = (new Contacts_Model_DbTable_Emailattachment())->syncDocument($document, 'sales', $controller);
		if ($controller === 'invoice') {
			$corrections = new Sales_Service_InvoiceCorrectionService();
			try { $corrections->context((int)$document['id'], 'send'); }
			catch (RuntimeException $error) { return $result; }
			foreach ($corrections->history((int)$document['id']) as $row) if ($row['status'] === 'approved' && !empty($row['consent'])) {
				try { $record = $corrections->get((int)$document['id'], (int)$row['id']); $corrections->path($record); }
				catch (RuntimeException $error) { error_log('Invoice correction attachment unavailable'); continue; }
				$result['attachments'][] = ['id' => 'correction:' . $row['id'], 'selection' => 'correction:' . $row['id'], 'filename' => $record['identity'] . '.pdf', 'filesize' => $row['filesize'], 'filetype' => 'application/pdf', 'deletable' => false, 'selected' => false, 'url' => '/sales/invoice/correctionpdf/id/' . $document['id'] . '/correctionid/' . $row['id']];
			}
		}
		return $result;
	}
}
