<?php

class Zend_View_Helper_DocumentChanges extends Zend_View_Helper_Abstract
{
	public function DocumentChanges(string $type, array $document): string
	{
		if (!in_array($type, ['quote', 'salesorder'], true) || empty($document['id'])) return '';
		$service = new Sales_Service_DocumentRevisionService();
		$document = $service->context((int)$document['id'], $type);
		$history = $service->history((int)$document['id'], $type); $canCreate = false;
		try { $service->context((int)$document['id'], $type, 'create'); $canCreate = !empty($document[$type . 'id']) && empty($document['cancelled']); } catch (RuntimeException $error) {}
		$this->view->addScriptPath(dirname(__DIR__) . '/scripts');
		return $this->view->partial('partials/document-revisions.phtml', ['document' => $document, 'type' => $type, 'history' => $history, 'canCreate' => $canCreate]);
	}
}
