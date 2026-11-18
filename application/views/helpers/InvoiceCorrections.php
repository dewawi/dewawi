<?php

class Zend_View_Helper_InvoiceCorrections extends Zend_View_Helper_Abstract
{
	public function InvoiceCorrections(array $invoice): string
	{
		$service = new Sales_Service_InvoiceCorrectionService(); $history = $service->history((int)$invoice['id']); $canCreate = false; $unavailable = '';
		try { $service->context((int)$invoice['id'], 'create'); $service->original((int)$invoice['id']); $canCreate = true; }
		catch (RuntimeException $error) { $unavailable = $error->getMessage(); }
		$this->view->addScriptPath(dirname(__DIR__) . '/scripts');
		return $this->view->partial('partials/invoice-corrections.phtml', ['invoice' => $invoice, 'history' => $history, 'canCreate' => $canCreate, 'unavailable' => $unavailable]);
	}
}
