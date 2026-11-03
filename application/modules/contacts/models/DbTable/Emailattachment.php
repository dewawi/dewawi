<?php

class Contacts_Model_DbTable_Emailattachment extends DEEC_Model_DbTable_Entity
{
	private const DOCUMENTS = [
		'sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'],
		'purchases' => ['quoterequest', 'purchaseorder'],
	];

	protected $_name = 'emailattachment';

	public function getPublishedFilePath(string $relative): ?string
	{
		if (!Zend_Registry::isRegistered('SiteContext')) return null;
		$site = Zend_Registry::get('SiteContext');
		$thumb = str_starts_with($relative, 'cache/thumbs/media/');
		$relative = preg_replace('~^cache/thumbs/media/~', 'media/', $relative);
		if (!preg_match('~^media/(?:([0-9]+)/([0-9]+)/([0-9]+)/)?(.+)$~D', $relative, $parts)) return null;
		$owner = (int)$site->getClientId();
		if ($owner < 1) return null;
		$shard = (new DEEC_Directory())->getShortUrl($owner);
		if ($parts[3] !== '' && implode('/', array_slice($parts, 1, 3)) !== $shard) return null;
		$filename = $parts[4];
		foreach (explode('/', $filename) as $part) {
			if (!DEEC_Directory::validUploadName($part) || preg_match('~\.(?:inc|ini|sql|log)(?:\.|$)~i', $part)) return null;
		}
		$prefix = 'media/' . $shard . '/';
		$candidates = [$filename];
		// Preserve the legacy download/downloads alias without media metadata.
		if (preg_match('~^(downloads?)/(.+)$~D', $filename, $download)) $candidates[] = ($download[1] === 'download' ? 'downloads/' : 'download/') . $download[2];
		foreach ($candidates as $candidate) {
			try {
				$path = DEEC_Directory::storedPath($prefix . $candidate);
			} catch (RuntimeException $exception) {
				continue;
			}
			$paths = [$path];
			$published = ($thumb ? 'cache/thumbs/' : '') . $prefix . $candidate;
			if ($thumb) $paths[] = DEEC_Directory::storedPath($published);
			foreach ($paths as $path) {
				$type = (new finfo(FILEINFO_MIME_TYPE))->file($path);
				if (!$type || in_array($type, ['text/x-php', 'application/x-httpd-php', 'application/x-executable', 'application/x-pie-executable', 'application/x-dosexec', 'application/x-sharedlib', 'application/x-shellscript', 'text/x-shellscript', 'text/html', 'application/xhtml+xml', 'text/javascript', 'application/javascript', 'text/xml', 'application/xml'], true)) return null;
			}
			return $published;
		}
		return null;
	}

	public function canReadStoredFile(string $relative, string $action = 'view'): bool
	{
		if (!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) return false;
		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		$relative = preg_replace(['~^cache/thumbs/files/~', '~^cache/thumbs/media/~'], ['files/', 'media/'], $relative);
		if (str_starts_with($relative, 'files/images/')) {
			$filename = substr($relative, strlen('files/images/'));
			$permission = new DEEC_Permission($user);
			foreach (['items' => ['itemlist' => 'image', 'inventory' => 'image'], 'admin' => ['tag' => 'image', 'template' => 'logo']] as $module => $controllers) {
				if ($module === 'admin' ? empty($user['admin']) : !$permission->hasActionPermission('items', 'item', $action)) continue;
				$owner = (int)($client['modules'][$module] ?? $user['clientid']);
				foreach ($controllers as $controller => $field) {
					$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
					$db = new $class();
					if ($db->fetchRow($db->select()->where('clientid = ?', $owner)->where($field . ' = ?', $filename)->where('deleted = ?', 0))) return true;
				}
			}
			return false;
		}
		if (preg_match('~^cache/document/([a-z-]+)/([a-z-]+)/([1-9][0-9]*)/([1-9][0-9]*)/([^/]+)$~D', $relative, $parts)) {
			$context = $this->getParentContext($parts[4], $parts[1], $parts[2], $action);
			return $context && $context['clientid'] === (int)$parts[3] && $parts[5] === (($context['parent']['filename'] ?? '') ?: $parts[2] . '_' . $parts[4] . '.pdf');
		}
		if (preg_match('~^cache/(item|itemlist)/([1-9][0-9]*)/([1-9][0-9]*)/preview\.pdf$~D', $relative, $parts)) {
			$context = $this->getParentContext($parts[3], 'items', $parts[1], $action);
			return $context && $context['clientid'] === (int)$parts[2];
		}
		if (preg_match('~^files/(contacts|items)/[0-9]/[0-9]/([1-9][0-9]*)/[0-9]/[0-9]/([1-9][0-9]*)/(.+)$~D', $relative, $parts)) {
			[$all, $kind, $owner, $id, $filename] = $parts;
			$prefix = 'files/' . $kind . '/' . (new DEEC_Directory())->getUrl($id, $owner) . '/';
			if (!str_starts_with($relative, $prefix)) return false;
			if ($kind === 'contacts') {
				$matches = $this->getStoredDocumentMatches((int)$owner, (int)$id, basename($filename));
				if ($matches) {
					if (count($matches) !== 1) return false;
					[$module, $controller, $documentId] = array_values($matches[0]);
					$context = $this->getParentContext($documentId, $module, $controller, $action);
					return $context && $context['clientid'] === (int)$owner
						&& ($action === 'view' || !DEEC_Permission::isDocumentReadonly($module, $controller, $context['parent']));
				}
			}
			$context = $this->getParentContext($id, $kind === 'contacts' ? 'contacts' : 'items', $kind === 'contacts' ? 'contact' : 'item', $action);
			return $context && $context['clientid'] === (int)$owner;
		}
		if (preg_match('~^files/(downloads|export)/[0-9]/[0-9]/([1-9][0-9]*)/(.+)$~D', $relative, $parts)) {
			$owner = (int)$parts[2];
			if (!str_starts_with($relative, 'files/' . $parts[1] . '/' . (new DEEC_Directory())->getShortUrl($owner) . '/')) return false;
			if ($parts[1] === 'export') return !empty($user['admin']) && $owner === (int)$user['clientid'];
			if (!in_array($owner, $this->getStorageClients(), true) || str_contains($parts[3], '/') || !(new DEEC_Permission($user))->hasPermission('contacts', 'contact', 'view')) return false;
			$db = (new Contacts_Model_DbTable_Download())->setClientId($owner);
			$rows = $db->fetchAll($db->select()->where('clientid = ?', $owner)->where('filename = ?', $parts[3])->where('deleted = ?', 0));
			return count($rows) === 1;
		}
		if (!preg_match('~^(files|media)/(.+)$~D', $relative, $parts)) return false;
		if (preg_match('~^media/[0-9]/[0-9]/([1-9][0-9]*)/(images|header|category|slide|downloads|download)/(.+)$~D', $relative, $media)) {
			$owner = (int)$media[1];
			if (!str_starts_with($relative, 'media/' . (new DEEC_Directory())->getShortUrl($owner) . '/')) return false;
			if (!empty($user['admin']) && $owner === (int)$user['clientid']) return true;
			$db = new Application_Model_DbTable_Media();
			$rows = $db->fetchAll($db->select()->where('clientid = ?', $owner)->where('url = ?', $media[3])->where('deleted = ?', 0));
			foreach ($rows as $row) {
				$context = $this->getParentContext($row['parentid'], $row['module'], $row['controller']);
				if ($context && $context['clientid'] === $owner) return true;
			}
		}
		$session = new Zend_Session_Namespace($parts[1] === 'files' ? 'FileManager' : 'MediaManager');
		$context = $session->context ?? [];
		$folder = trim((string)($session->subfolder ?? ''), '/');
		if ($folder === '' || $folder === 'uploads' || !str_starts_with($parts[2], $folder . '/')
			|| ($context['userid'] ?? 0) !== (int)$user['id'] || ($context['clientid'] ?? 0) !== (int)$user['clientid']) return false;
		$module = $context['module'] ?? '';
		if (($context['ownerid'] ?? 0) !== (int)($client['modules'][$module] ?? $user['clientid'])) return false;
		if ($module === 'admin') return !empty($user['admin']);
		if ($module === 'users') return ($context['controller'] ?? '') === 'user' && ($context['id'] ?? 0) === (int)$user['id'] && $folder === 'uploads/' . (new DEEC_Directory())->getShortUrl($user['clientid']) . '/users/' . (int)$user['id'];
		if (str_starts_with($parts[2], 'uploads/')) return false;
		return $this->getParentContext($context['id'] ?? 0, $module, $context['controller'] ?? '') !== null;
	}

	private function getStoredDocumentMatches(int $owner, int $pathContactId, string $filename): array
	{
		$matches = [];
		$contactIds = [];
		foreach (self::DOCUMENTS as $module => $controllers) {
			foreach ($controllers as $controller) {
				$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
				$db = new $class();
				$rows = $db->fetchAll($db->select()->where('clientid = ?', $owner)->where('filename = ?', $filename));
				foreach ($rows as $row) {
					$data = $row instanceof Zend_Db_Table_Row_Abstract ? $row->toArray() : (array)$row;
					$contactNumber = (int)($data['contactid'] ?? 0);
					if ($contactNumber !== $pathContactId) {
						if (!array_key_exists($contactNumber, $contactIds)) $contactIds[$contactNumber] = $this->getContactRecordId($contactNumber);
						if ($contactIds[$contactNumber] !== $pathContactId) continue;
					}
					$matches[] = ['module' => $module, 'controller' => $controller, 'id' => (int)$data['id']];
				}
			}
		}
		return $matches;
	}

	private function getContactRecordId(int $contactNumber): int
	{
		if ($contactNumber <= 0 || !Zend_Registry::isRegistered('Client') || !Zend_Registry::isRegistered('User')) return 0;
		$client = Zend_Registry::get('Client');
		$user = Zend_Registry::get('User');
		$owner = (int)($client['modules']['contacts'] ?? $user['clientid']);
		if ($owner <= 0) return 0;
		$contact = (new Contacts_Model_DbTable_Contact())->setClientId($owner)->getContactWithID($contactNumber);
		return (int)($contact['id'] ?? 0);
	}

	public function getContactDocumentFiles(int $contactId): array
	{
		$contactContext = $this->getParentContext($contactId, 'contacts', 'contact');
		if (!$contactContext) return [];
		$contact = $contactContext['parent'];
		$contactNumber = (int)($contact['contactid'] ?? 0);
		if ($contactNumber <= 0) return [];

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		$permission = new DEEC_Permission((array)$user);
		$files = [];
		foreach (self::DOCUMENTS as $module => $controllers) {
			$owner = (int)($client['modules'][$module] ?? $user['clientid']);
			if ($owner <= 0) continue;
			foreach ($controllers as $controller) {
				if (!$permission->hasActionPermission($module, $controller, 'view')) continue;
				$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
				$rows = (new $class())->setClientId($owner)->getByContactId($contactNumber, 'id DESC');
				foreach ($rows as $row) {
					$document = $row instanceof Zend_Db_Table_Row_Abstract ? $row->toArray() : (array)$row;
					if (!DEEC_Permission::isDocumentReadonly($module, $controller, $document)) continue;
					$context = $this->getParentContext((int)$document['id'], $module, $controller);
					if (!$context || $context['clientid'] !== $owner) continue;
					$files[] = $this->resolveContactDocumentFile($context['parent'], $module, $controller, $contactId, $contactNumber, $owner);
				}
			}
		}
		usort($files, static function (array $left, array $right): int {
			return strcmp((string)($right['date'] ?? ''), (string)($left['date'] ?? '')) ?: ((int)$right['id'] <=> (int)$left['id']);
		});
		$paths = [];
		foreach ($files as $index => $file) if ($file['relative'] !== '') $paths[$file['relative']][] = $index;
		foreach ($paths as $indexes) {
			if (count($indexes) < 2) continue;
			foreach ($indexes as $index) {
				if ($files[$index]['fallbackAttachmentId']) {
					$files[$index]['source'] = 'attachment';
					$files[$index]['relative'] = '';
					$files[$index]['attachmentId'] = $files[$index]['fallbackAttachmentId'];
				} else {
					$files[$index]['status'] = 'conflict';
					$files[$index]['source'] = $files[$index]['relative'] = '';
				}
			}
		}
		foreach ($files as &$file) unset($file['fallbackAttachmentId']);
		unset($file);
		return $files;
	}

	public function getContactDocumentFilenames(int $contactId): array
	{
		$contactContext = $this->getParentContext($contactId, 'contacts', 'contact');
		if (!$contactContext) return [];
		$contactNumber = (int)($contactContext['parent']['contactid'] ?? 0);
		if ($contactNumber <= 0) return [];
		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		$filenames = [];
		foreach (self::DOCUMENTS as $module => $controllers) {
			$owner = (int)($client['modules'][$module] ?? $user['clientid']);
			if ($owner <= 0) continue;
			foreach ($controllers as $controller) {
				$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
				foreach ((new $class())->setClientId($owner)->getByContactId($contactNumber) as $row) {
					$document = $row instanceof Zend_Db_Table_Row_Abstract ? $row->toArray() : (array)$row;
					$filename = (string)($document['filename'] ?? '');
					if (DEEC_Permission::isDocumentReadonly($module, $controller, $document) && DEEC_Emailattachment::validFilename($filename)) {
						$filenames[$filename] = true;
					}
				}
			}
		}
		return array_keys($filenames);
	}

	private function resolveContactDocumentFile(array $document, string $module, string $controller, int $contactId, int $contactNumber, int $owner): array
	{
		$filename = (string)($document['filename'] ?? '');
		$result = [
			'id' => (int)$document['id'], 'module' => $module, 'controller' => $controller,
			'number' => (string)($document[$controller . 'id'] ?? ''),
			'date' => (string)($document[$controller . 'date'] ?? ''), 'filename' => $filename,
			'status' => 'missing', 'source' => '', 'relative' => '', 'attachmentId' => 0, 'fallbackAttachmentId' => 0,
		];
		if (!DEEC_Emailattachment::validFilename($filename)) return $result;

		$directory = new DEEC_Directory();
		$originals = [];
		$invalid = false;
		foreach (array_unique([$contactNumber, $contactId]) as $storageId) {
			$relative = 'files/contacts/' . $directory->getUrl((string)$storageId, (string)$owner) . '/' . $filename;
			$file = $this->readStoredPdf($relative);
			if (!empty($file['valid'])) $originals[] = $file + ['source' => $storageId === $contactNumber ? 'current' : 'legacy'];
			elseif (!empty($file['exists'])) $invalid = true;
		}

		$attachments = [];
		foreach ($this->getEmailattachments((int)$document['id'], $module, $controller) as $attachment) {
			if ((string)$attachment['filename'] !== $filename) continue;
			try {
				$path = DEEC_Emailattachment::getPath($attachment);
				DEEC_Pdf::assertCompletePdfFile($path);
				$attachments[] = ['hash' => hash_file('sha256', $path), 'attachmentId' => (int)$attachment['id'], 'source' => 'attachment'];
			} catch (Exception $exception) {
				$invalid = true;
			}
		}

		$candidates = array_merge($originals, $attachments);
		$hashes = array_values(array_unique(array_column($candidates, 'hash')));
		if (count($hashes) > 1) {
			$result['status'] = 'conflict';
			return $result;
		}
		if (!$candidates) {
			if ($invalid) $result['status'] = 'invalid';
			return $result;
		}

		$chosen = $attachments[0] ?? $originals[0];
		$result['source'] = $chosen['source'];
		$result['relative'] = (string)($chosen['relative'] ?? '');
		$result['attachmentId'] = (int)($chosen['attachmentId'] ?? 0);
		$result['fallbackAttachmentId'] = (int)($attachments[0]['attachmentId'] ?? 0);
		$result['status'] = $invalid ? 'invalid' : (!$originals ? 'attachment_only' : (!$attachments ? 'original_only' : 'available'));
		return $result;
	}

	private function readStoredPdf(string $relative): array
	{
		$path = realpath(BASE_PATH) . '/' . $relative;
		if (!file_exists($path) && !is_link($path)) return ['exists' => false, 'valid' => false];
		try {
			$path = DEEC_Directory::storedPath($relative);
			DEEC_Pdf::assertCompletePdfFile($path);
			return ['exists' => true, 'valid' => true, 'relative' => $relative, 'hash' => hash_file('sha256', $path)];
		} catch (Exception $exception) {
			return ['exists' => true, 'valid' => false];
		}
	}

	public function getParentContext($documentId, $module, $controller, string $action = 'view'): ?array
	{
		if(!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) return null;
		if (!is_string($module) || !is_string($controller)) return null;
		if(!DEEC_Permission::isDocument($module, $controller) && !in_array([$module, $controller], [
			['contacts', 'contact'], ['contacts', 'contactperson'], ['items', 'item'], ['items', 'itemlist'], ['processes', 'process'], ['tasks', 'task'], ['campaigns', 'campaign'],
		], true)) return null;
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		if ($action !== 'view' && !$permission->getObjectContext($documentId, $module, $controller)) return null;
		return $permission->getObjectContext($documentId, $module, $controller, $action);
	}

	private function getStorageClients(): array
	{
		if(!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) return [];
		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		if(empty($user['id']) || empty($user['clientid'])) return [];
		$contactClientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$user['clientid'];
		return array_values(array_unique(array_filter([(int)$user['clientid'], $contactClientId])));
	}

	public function getEmailattachment($id, string $action = 'view'): ?array
	{
		$id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if(!$id) return null;
		foreach($this->getStorageClients() as $clientId) {
			$row = $this->fetchRow($this->select()->where('id = ?', $id)->where('clientid = ?', $clientId)->where('deleted = ?', 0));
			if(!$row) continue;
			$attachment = $row->toArray();
			$context = $this->getParentContext($attachment['documentid'], $attachment['module'], $attachment['controller'], $action);
			if(!$context || !in_array((int)$attachment['clientid'], $context['storageClients'], true)) return null;
			return $attachment;
		}
		return null;
	}

	public function getEmailattachments($documentId, $module, $controller): array
	{
		$context = $this->getParentContext($documentId, $module, $controller);
		if(!$context) return [];
		$result = [];
		foreach($context['storageClients'] as $clientId) {
			$select = $this->select()->where('documentid = ?', (int)$documentId)->where('module = ?', $module)
				->where('controller = ?', $controller)->where('clientid = ?', $clientId)->where('deleted = ?', 0);
			foreach($this->fetchAll($select)->toArray() as $row) $result[] = $row;
		}
		return $result;
	}

	public function addEmailattachment($data)
	{
		$context = $this->getParentContext($data['documentid'] ?? 0, $data['module'] ?? '', $data['controller'] ?? '', 'upload');
		if(!$context) throw new RuntimeException('Attachment access denied');
		$this->assertUploadFilename($context, (string)($data['module'] ?? ''), (string)($data['controller'] ?? ''), (string)($data['filename'] ?? ''));
		$data['clientid'] = $context['clientid'];
		DEEC_Emailattachment::getPath($data);
		$data['uploaded'] = $this->_date;
		$data['uploadedby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function getForMessage($messageId, string $filename): ?array
	{
		$id = filter_var($messageId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if(!$id || !DEEC_Emailattachment::validFilename($filename)) return null;
		$messageDb = new Contacts_Model_DbTable_Emailmessage();
		foreach($this->getStorageClients() as $clientId) {
			$messageDb->setClientId($clientId);
			$message = $messageDb->getById($id);
			if(!$message || !in_array($filename, explode(',', (string)($message['attachment'] ?? '')), true)) continue;
			$parentId = $message['campaignid'] ?: ($message['documentid'] ?: ($message['module'] === 'contacts' ? $message['contactid'] : 0));
			foreach($this->getEmailattachments($parentId, $message['module'], $message['controller']) as $attachment) {
				if($attachment['filename'] === $filename) return $attachment;
			}
		}
		return null;
	}

	public function syncDocument(array $document, string $module, string $controller): array
	{
		$context = $this->getParentContext($document['id'] ?? 0, $module, $controller);
		if(!$context) return ['contactUrl' => '', 'documentUrl' => '', 'attachments' => []];
		$document = $context['parent'];
		$directory = new DEEC_Directory();
		$contactIds = [(int)$document['contactid']];
		$contactRecordId = $this->getContactRecordId((int)$document['contactid']);
		if ($contactRecordId > 0 && $contactRecordId !== (int)$document['contactid']) $contactIds[] = $contactRecordId;
		$contactUrls = array_map(static fn(int $contactId): string => $directory->getUrl((string)$contactId, (string)$context['clientid']), $contactIds);
		$contactUrl = $contactUrls[0];
		$documentUrl = $directory->getUrl((string)$document['id'], (string)$context['clientid']);
		$filename = (string)($document['filename'] ?? '');
		$sources = [];
		foreach ($contactUrls as $url) {
			$path = realpath(BASE_PATH) . '/files/contacts/' . $url . '/' . $filename;
			DEEC_Emailattachment::checkPath($path);
			if (!is_file($path)) continue;
			DEEC_Pdf::assertCompletePdfFile($path);
			$sources[] = ['path' => $path, 'url' => $url, 'hash' => hash_file('sha256', $path)];
		}
		if (count(array_unique(array_column($sources, 'hash'))) > 1) throw new RuntimeException('Original PDF locations differ');
		$contactSource = $sources[0]['path'] ?? '';
		if ($sources) $contactUrl = $sources[0]['url'];
		$archive = DEEC_Emailattachment::getDirectory($module, $controller, (int)$document['id'], $context['clientid']) . '/' . $filename;
		DEEC_Emailattachment::checkPath($archive);
		if (is_file($archive)) {
			DEEC_Pdf::assertCompletePdfFile($archive);
			$sources[] = ['path' => $archive, 'url' => $documentUrl, 'hash' => hash_file('sha256', $archive)];
		}
		if (count(array_unique(array_column($sources, 'hash'))) > 1) throw new RuntimeException('Original PDF locations differ');
		$attachments = $this->getEmailattachments($document['id'], $module, $controller);
		$exists = false;
		foreach($attachments as $attachment) {
			if($attachment['filename'] === $filename) {
				$exists = true;
				$attachmentPath = DEEC_Emailattachment::getPath($attachment);
				DEEC_Pdf::assertCompletePdfFile($attachmentPath);
				if ($sources && !hash_equals($sources[0]['hash'], hash_file('sha256', $attachmentPath))) throw new RuntimeException('Attachment differs from original PDF');
			}
		}
		if (!DEEC_Permission::isDocument($module, $controller) && !$exists && $contactSource !== '' && DEEC_Emailattachment::validFilename($filename)) {
			$target = dirname($archive);
			if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) throw new RuntimeException('Attachment directory is not available');
			$output = @fopen($archive, 'x');
			if (!$output) throw new RuntimeException('Attachment could not be stored');
			$input = fopen($contactSource, 'rb');
			try {
				if (!$input || stream_copy_to_stream($input, $output) !== filesize($contactSource)) throw new RuntimeException('Attachment could not be stored');
			} catch (Throwable $exception) {
				unlink($archive);
				throw $exception;
			} finally {
				if ($input) fclose($input);
				fclose($output);
			}
			DEEC_Pdf::assertCompletePdfFile($archive);
			if (!hash_equals(hash_file('sha256', $contactSource), hash_file('sha256', $archive))) throw new RuntimeException('Attachment differs from original PDF');
			try {
				$this->insert([
					'documentid' => $document['id'], 'module' => $module, 'controller' => $controller, 'clientid' => $context['clientid'],
					'filename' => $filename, 'location' => $target, 'filetype' => mime_content_type($archive), 'filesize' => filesize($archive),
					'ordering' => 1, 'uploaded' => $this->_date, 'uploadedby' => $this->_user['id'],
				]);
			} catch (Throwable $exception) {
				unlink($archive);
				throw $exception;
			}
			$attachments = $this->getEmailattachments($document['id'], $module, $controller);
		}
		return ['contactUrl' => $contactUrl, 'documentUrl' => $documentUrl, 'attachments' => $attachments];
	}

	public function registerDocumentOriginal(array $document, string $module, string $controller): array
	{
		$context = $this->getParentContext($document['id'] ?? 0, $module, $controller);
		if (!$context || !DEEC_Permission::isDocumentReadonly($module, $controller, $context['parent'])) {
			throw new RuntimeException('Document archive access denied');
		}
		$document = $context['parent'];
		$filename = (string)($document['filename'] ?? '');
		if (!DEEC_Emailattachment::validFilename($filename)) throw new RuntimeException('Invalid PDF filename');
		$directory = DEEC_Emailattachment::getDirectory($module, $controller, (int)$document['id'], $context['clientid']);
		$path = $directory . '/' . $filename;
		DEEC_Emailattachment::checkPath($path);
		DEEC_Pdf::assertCompletePdfFile($path);
		$hash = hash_file('sha256', $path);
		$matches = [];
		foreach ($this->getEmailattachments($document['id'], $module, $controller) as $attachment) {
			if ((string)$attachment['filename'] !== $filename) continue;
			$attachmentPath = DEEC_Emailattachment::getPath($attachment);
			DEEC_Pdf::assertCompletePdfFile($attachmentPath);
			if ((int)$attachment['clientid'] !== (int)$context['clientid'] || $attachmentPath !== $path
				|| !hash_equals($hash, hash_file('sha256', $attachmentPath))) {
				throw new RuntimeException('Document archive metadata differs');
			}
			$matches[] = $attachment;
		}
		if (count($matches) > 1) throw new RuntimeException('Document archive metadata is ambiguous');
		if ($matches) return $matches[0];

		$this->insert([
			'documentid' => (int)$document['id'], 'module' => $module, 'controller' => $controller, 'clientid' => $context['clientid'],
			'filename' => $filename, 'location' => $directory, 'filetype' => 'application/pdf', 'filesize' => filesize($path),
			'ordering' => 1, 'uploaded' => $this->_date, 'uploadedby' => (int)$this->_user['id'],
		]);
		foreach ($this->getEmailattachments($document['id'], $module, $controller) as $attachment) {
			if ((string)$attachment['filename'] === $filename && (int)$attachment['clientid'] === (int)$context['clientid']) return $attachment;
		}
		throw new RuntimeException('Document archive metadata could not be stored');
	}

	public function updateEmailattachment($id, $data)
	{
		$attachment = $this->getEmailattachment($id, 'edit');
		if(!$attachment) throw new RuntimeException('Attachment access denied');
		$data = array_intersect_key($data, array_flip(['ordering']));
		if($data) $this->update($data, $this->attachmentWhere($attachment));
	}

	private function attachmentWhere(array $attachment): array
	{
		return [
			$this->getAdapter()->quoteInto('id = ?', (int)$attachment['id']),
			$this->getAdapter()->quoteInto('clientid = ?', (int)$attachment['clientid']),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];
	}

	public function deleteEmailattachment($id): void
	{
		$attachment = $this->getEmailattachment($id, 'delete');
		if(!$attachment) throw new RuntimeException('Attachment access denied');
		$this->assertEmailattachmentDeletable($attachment);
		$path = DEEC_Emailattachment::getPath($attachment, false);
		$this->update(['deleted' => 1], $this->attachmentWhere($attachment));
		$references = $this->fetchAll($this->select()->where('clientid = ?', (int)$attachment['clientid'])
			->where('documentid = ?', (int)$attachment['documentid'])->where('module = ?', $attachment['module'])
			->where('controller = ?', $attachment['controller'])->where('filename = ?', $attachment['filename'])->where('deleted = ?', 0));
		if(count($references) === 0 && is_file($path) && !unlink($path)) throw new RuntimeException('Attachment could not be deleted');
	}

	public function assertEmailattachmentDeletable(array $attachment): void
	{
		$context = $this->getParentContext($attachment['documentid'] ?? 0, (string)($attachment['module'] ?? ''), (string)($attachment['controller'] ?? ''), 'delete');
		if (!$context) throw new RuntimeException('Attachment access denied');
		if ($this->isOriginalDocumentFile($context['parent'], (string)$attachment['module'], (string)$attachment['controller'], (string)$attachment['filename'])) {
			throw new RuntimeException('Original document attachment is immutable');
		}
	}

	public function assertUploadFilename(array $context, string $module, string $controller, string $filename): void
	{
		if ($this->isOriginalDocumentFile((array)($context['parent'] ?? []), $module, $controller, $filename)) {
			throw new RuntimeException('Original document attachment is immutable');
		}
	}

	private function isOriginalDocumentFile(array $document, string $module, string $controller, string $filename): bool
	{
		return DEEC_Permission::isDocument($module, $controller) && $filename !== ''
			&& hash_equals((string)($document['filename'] ?? ''), $filename)
			&& DEEC_Permission::isDocumentReadonly($module, $controller, $document);
	}
}
