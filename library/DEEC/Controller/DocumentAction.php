<?php

abstract class DEEC_Controller_DocumentAction extends DEEC_Controller_Action
{
	public function previewAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$templateId = (int)$this->_getParam('templateid', 0);
		$isAjax = $this->getRequest()->isXmlHttpRequest();

		try {
			$result = $this->generatePdfDocument($id, [
				'output' => $isAjax ? 'file' : 'inline',
				'templateid' => $templateId ?: null,
				'storage' => 'cache',
				'overwrite' => true,
			]);
		} catch (RuntimeException $e) {
			return $this->handleDocumentNotFound($isAjax);
		}

		if ($isAjax) {
			$this->disableView();

			return $this->_helper->json([
				'ok' => true,
				'url' => $result['url'] ?? null,
				'filename' => $result['filename'] ?? null,
			]);
		}

		return $this->sendPdfResponse($result);
	}

	public function downloadAction()
	{
		$id = (int)$this->_getParam('id', 0);

		try {
			$result = $this->generatePdfDocument($id, [
				'output' => 'download',
				'storage' => 'cache',
				'overwrite' => true,
			]);
		} catch (RuntimeException $e) {
			return $this->handleDocumentNotFound(false);
		}

		return $this->sendPdfResponse($result);
	}

	protected function generatePdfDocument(int $id, array $options = []): array
	{
		$row = $this->loadRow($id);

		if (!$row) {
			throw new RuntimeException('Document not found');
		}

		if (!empty($options['finalize'])) {
			$row = $this->finalizeDocument($row);
		}

		$pdf = new DEEC_Pdf();

		return $pdf->generate([
			'module' => $this->getRequest()->getModuleName(),
			'controller' => $this->getRequest()->getControllerName(),
			'documentId' => (int)$row['id'],
			'output' => $options['output'] ?? 'file',
			'templateid' => $options['templateid'] ?? null,
			'storage' => $options['storage'] ?? 'cache',
			'overwrite' => !empty($options['overwrite']),
		]);
	}

	protected function ensurePdfDocumentExists(int $id): void
	{
		$row = $this->loadRow($id);

		if (!$row) {
			return;
		}

		if (empty($row['id']) || empty($row['contactid']) || empty($row['clientid'])) {
			return;
		}

		$docIdField = $this->getDocumentNumberField();

		// Do not generate contact PDF before document is finalized
		if (empty($row[$docIdField]) || empty($row['filename'])) {
			return;
		}

		try {
			$this->generatePdfDocument($id, [
				'output' => 'file',
				'storage' => 'contact',
				'overwrite' => false,
			]);
		} catch (RuntimeException $e) {
			// Keep view page working even if PDF generation fails
		}
	}

	protected function sendPdfResponse(array $result)
	{
		if (empty($result['path']) || !is_file($result['path'])) {
			throw new RuntimeException('PDF file not found');
		}

		$mode = $result['output'] ?? 'inline';
		$filename = $result['filename'] ?? basename($result['path']);

		$this->disableView();

		$response = $this->getResponse();
		$response->clearHeaders();
		$response->setHeader('Content-Type', 'application/pdf', true);
		$response->setHeader(
			'Content-Disposition',
			($mode === 'download' ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
			true
		);
		$response->setHeader('Content-Length', (string)filesize($result['path']), true);
		$response->sendHeaders();

		readfile($result['path']);
		exit;
	}

	protected function handleDocumentNotFound(bool $isAjax)
	{
		if ($isAjax) {
			$this->disableView();

			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		$this->_flashMessenger->addMessage($this->getNotFoundMessage());

		return $this->_helper->redirector->gotoSimple(
			'index',
			$this->getRequest()->getControllerName(),
			$this->getRequest()->getModuleName()
		);
	}

	protected function getDocumentNumberField(): string
	{
		return $this->getRequest()->getControllerName() . 'id';
	}

	protected function isReadonlyState(array $row): bool
	{
		return in_array((int)($row['state'] ?? 0), [105, 106], true);
	}

	protected function finalizeDocument(array $row): array
	{
		$service = $this->getDocumentFinalizeService();

		if (!$service) {
			throw new RuntimeException('Document finalize service not available');
		}

		return $service->finalize(
			$row,
			$this->getRequest()->getControllerName()
		);
	}

	protected function getDocumentFinalizeService()
	{
		$className = $this->getDocumentFinalizeServiceClass();

		if (!$className) {
			$module = ucfirst($this->getRequest()->getModuleName());
			$className = $module . '_Service_DocumentFinalizeService';
		}

		if (!class_exists($className)) {
			return null;
		}

		$service = new $className();

		if (!method_exists($service, 'finalize')) {
			throw new RuntimeException($className . ' must provide finalize()');
		}

		return $service;
	}

	protected function getDocumentFinalizeServiceClass(): ?string
	{
		return null;
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$this->copyDocumentPositions($oldId, $newId);
	}

	protected function copyDocumentPositions(int $oldId, int $newId): void
	{
		$positionsDbClass = $this->getDocumentPositionsDbTableClass();

		if (!class_exists($positionsDbClass)) {
			return;
		}

		$positionsDb = new $positionsDbClass();

		if (!method_exists($positionsDb, 'getPositions')) {
			return;
		}

		$positions = $positionsDb->getPositions($oldId);

		$this->_helper->Position->copyPositions(
			$positions,
			$newId,
			$this->getRequest()->getModuleName(),
			$this->getRequest()->getControllerName(),
			$this->_date
		);
	}

	protected function getDocumentPositionsDbTableClass(): string
	{
		return $this->getModuleClassPrefix()
			. '_Model_DbTable_'
			. $this->getControllerClassName()
			. 'pos';
	}
}
