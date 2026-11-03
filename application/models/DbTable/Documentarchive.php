<?php

class Application_Model_DbTable_Documentarchive extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'documentarchive';
	protected ?string $orderingField = null;
	protected ?string $deletedField = null;

	public function getForDocument(int $documentId, string $module, string $controller, string $format = 'pdf'): ?array
	{
		$context = (new Contacts_Model_DbTable_Emailattachment())->getParentContext($documentId, $module, $controller);
		if (!$context || !DEEC_DocumentArchive::validDocumentType($module, $controller)) return null;
		$row = $this->fetchRow($this->select()->where('documentid = ?', $documentId)->where('module = ?', $module)
			->where('controller = ?', $controller)->where('clientid = ?', (int)$context['clientid'])->where('format = ?', strtolower($format))
			->where('version = ?', 1)->limit(1));
		if (!$row) return null;
		$data = $row->toArray();
		if (strtolower($format) === 'pdf' && !hash_equals((string)($context['parent']['filename'] ?? ''), (string)$data['filename'])) throw new RuntimeException('Document archive filename differs');
		$this->assertStoredFile($data);
		return $data;
	}

	public function getArchive(int $id): ?array
	{
		$row = $this->fetchRow($this->select()->where('id = ?', $id)->limit(1));
		if (!$row) return null;
		$data = $row->toArray();
		$context = (new Contacts_Model_DbTable_Emailattachment())->getParentContext($data['documentid'], $data['module'], $data['controller']);
		if (!$context || (int)$data['clientid'] !== (int)$context['clientid']) return null;
		$this->assertStoredFile($data);
		return $data;
	}

	public function registerOriginal(array $document, string $module, string $controller, string $format = 'pdf', ?string $filename = null, ?array $parameters = null): array
	{
		$context = (new Contacts_Model_DbTable_Emailattachment())->getParentContext($document['id'] ?? 0, $module, $controller);
		if (!$context || !DEEC_Permission::isDocumentReadonly($module, $controller, $context['parent'])) throw new RuntimeException('Document archive access denied');
		$document = $context['parent'];
		$filename = $filename ?? (string)($document['filename'] ?? '');
		if (!DEEC_Emailattachment::validFilename($filename)) throw new RuntimeException('Invalid archive filename');
		$archive = new DEEC_DocumentArchive(BASE_PATH);
		$relative = $archive->relativePath((int)$context['clientid'], $module, $controller, (int)$document['id'], $format);
		$file = $archive->inspect(BASE_PATH . '/' . $relative, $format);
		if ($file['status'] !== 'ok') throw new RuntimeException('Document archive file is unavailable or invalid');
		$existing = $this->fetchAll($this->select()->where('documentid = ?', (int)$document['id'])->where('module = ?', $module)
			->where('controller = ?', $controller)->where('clientid = ?', (int)$context['clientid'])->where('format = ?', strtolower($format))
			->where('version = ?', 1))->toArray();
		if (count($existing) > 1) throw new RuntimeException('Document archive metadata is ambiguous');
		$expected = [
			'documentid' => (int)$document['id'], 'module' => $module, 'controller' => $controller, 'clientid' => (int)$context['clientid'],
			'filename' => $filename, 'format' => strtolower($format), 'path' => $relative, 'filesize' => (int)$file['size'], 'sha256' => $file['sha256'], 'version' => 1,
		];
		if ($existing) {
			foreach ($expected as $field => $value) if ((string)$existing[0][$field] !== (string)$value) throw new RuntimeException('Document archive metadata differs');
			return $existing[0];
		}
		$this->insert($expected + [
			'parameters' => $parameters === null ? null : json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
			'correctionarchiveid' => null, 'archived' => $this->_date, 'archivedby' => (int)($this->_user['id'] ?? 0),
		]);
		$expected['id'] = (int)$this->getAdapter()->lastInsertId();
		return $expected;
	}

	public function archiveBytes(array $document, string $module, string $controller, string $format, string $filename, string $bytes, array $parameters = []): array
	{
		$existing = $this->getForDocument((int)($document['id'] ?? 0), $module, $controller, $format);
		if ($existing) return $existing;
		$context = (new Contacts_Model_DbTable_Emailattachment())->getParentContext($document['id'] ?? 0, $module, $controller);
		if (!$context || !DEEC_Permission::isDocumentReadonly($module, $controller, $context['parent'])) throw new RuntimeException('Document archive access denied');
		(new DEEC_DocumentArchive(BASE_PATH))->store($bytes, (int)$context['clientid'], $module, $controller, (int)$document['id'], $format);
		return $this->registerOriginal($document, $module, $controller, $format, $filename, $parameters);
	}

	public function getPath(array $archive): string
	{
		$this->assertStoredFile($archive);
		return BASE_PATH . '/' . $archive['path'];
	}

	private function assertStoredFile(array $row): void
	{
		$archive = new DEEC_DocumentArchive(BASE_PATH);
		$expected = $archive->relativePath((int)$row['clientid'], (string)$row['module'], (string)$row['controller'], (int)$row['documentid'], (string)$row['format']);
		if (!hash_equals($expected, (string)$row['path'])) throw new RuntimeException('Document archive path differs');
		$file = $archive->inspect(BASE_PATH . '/' . $expected, (string)$row['format']);
		if ($file['status'] !== 'ok' || (int)$row['filesize'] !== (int)$file['size'] || !hash_equals((string)$row['sha256'], (string)$file['sha256'])) {
			throw new RuntimeException('Document archive integrity check failed');
		}
	}
}
