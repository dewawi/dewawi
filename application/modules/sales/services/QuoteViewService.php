<?php

class Sales_Service_QuoteViewService
{
	public function build(int $id, string $controller = 'quote'): array
	{
		$locale = Zend_Registry::get('Zend_Locale');

		$quoteDb = new Sales_Model_DbTable_Quote();
		$contactDb = new Contacts_Model_DbTable_Contact();

		$quote = $quoteDb->getQuote($id);
		$contact = $contactDb->getContactWithID((int)$quote['contactid']);

		$emailFormFactory = new Sales_Service_EmailFormFactory();
		$attachmentService = new Sales_Service_AttachmentService();
		$readonlyFormFactory = new Sales_Service_ReadonlyFormFactory();

		$emailForm = $emailFormFactory->build($quote, $contact, $controller);
		$attachmentData = $attachmentService->sync($quote, $contact, $controller);
		$form = $readonlyFormFactory->build('Sales_Form_Quote', $quote, $locale);

		return [
			'quote' => $quote,
			'contact' => $contact,
			'emailForm' => $emailForm,
			'form' => $form,
			'contactUrl' => $attachmentData['contactUrl'],
			'documentUrl' => $attachmentData['documentUrl'],
			'attachments' => $attachmentData['attachments'],
		];
	}
}
