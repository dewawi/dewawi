<?php

class Contacts_Model_DbTable_Emailattachment extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'emailattachment';

	public function getPublishedFilePath(string $relative): ?string
	{
		if (!Zend_Registry::isRegistered('SiteContext')) return null;
		$site = Zend_Registry::get('SiteContext');
		$thumb = str_starts_with($relative, 'cache/thumbs/media/');
		$relative = preg_replace('~^cache/thumbs/media/~', 'media/', $relative);
		if (!preg_match('~^media/(?:(?:([0-9])/([0-9])/([1-9][0-9]*)/)?)(images|header|category|slide|downloads|download)/(.+)$~D', $relative, $parts)) return null;
		$owner = (int)$site->getClientId();
		if ($parts[3] !== '' && ($owner !== (int)$parts[3] || implode('/', array_slice($parts, 1, 3)) !== (new DEEC_Directory())->getShortUrl($owner))) return null;
		[$kind, $filename] = [$parts[4], $parts[5]];
		$prefix = ($thumb ? 'cache/thumbs/media/' : 'media/') . (new DEEC_Directory())->getShortUrl($owner) . '/';
		if ($kind === 'header' && $site->get('logo') === $filename) return $prefix . 'header/' . $filename;
		$db = new Shops_Model_DbTable_Media();
		$rows = $db->fetchAll($db->select()->where('clientid = ?', $owner)->where('url = ?', $filename)->where('deleted = ?', 0));
		foreach ($rows as $row) {
			if (in_array($kind, ['downloads', 'download'], true) ? !in_array($row['type'], ['file', 'download'], true) : $row['type'] !== 'image') continue;
			if ($row['module'] === 'items' && $row['controller'] === 'item' && in_array($kind, ['images', 'downloads', 'download'], true)) {
				if ((new Shops_Model_DbTable_Item())->getItem((int)$row['parentid'])) return $prefix . ($row['type'] === 'image' ? 'images' : ($row['type'] === 'download' ? 'download' : 'downloads')) . '/' . $filename;
			}
			if ($row['module'] === 'shops' && in_array($row['controller'], ['category', 'slide'], true) && in_array($kind, [$row['controller'], 'downloads', 'download'], true)) {
				$class = 'Shops_Model_DbTable_' . ucfirst($row['controller']);
				if ((new $class())->getPublicById((int)$row['parentid'])) return $prefix . ($row['type'] === 'image' ? $row['controller'] : ($row['type'] === 'download' ? 'download' : 'downloads')) . '/' . $filename;
			}
		}
		return in_array($kind, ['downloads', 'download'], true) && (new Shops_Model_DbTable_Item())->hasPublicDownload($filename) ? $prefix . 'downloads/' . $filename : null;
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
				$matches = [];
				foreach (['sales' => ['quote', 'salesorder', 'invoice', 'deliveryorder', 'creditnote', 'reminder'], 'purchases' => ['quoterequest', 'purchaseorder']] as $module => $controllers) {
					foreach ($controllers as $controller) {
						$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
						$db = new $class();
						$rows = $db->fetchAll($db->select()->where('clientid = ?', (int)$owner)->where('contactid = ?', (int)$id)->where('filename = ?', basename($filename)));
						foreach ($rows as $row) $matches[] = [$module, $controller, (int)$row['id']];
					}
				}
				if ($matches) {
					if (count($matches) !== 1) return false;
					[$module, $controller, $documentId] = $matches[0];
					$context = $this->getParentContext($documentId, $module, $controller, $action);
					return $context && $context['clientid'] === (int)$owner;
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
		$contactUrl = $directory->getUrl((string)(int)$document['contactid'], (string)$context['clientid']);
		$documentUrl = $directory->getUrl((string)$document['id'], (string)$context['clientid']);
		$filename = (string)($document['filename'] ?? '');
		$attachments = $this->getEmailattachments($document['id'], $module, $controller);
		$exists = false;
		foreach($attachments as $attachment) {
			if($attachment['filename'] === $filename) $exists = true;
		}
		if(!$exists && (int)$document['contactid'] > 0 && DEEC_Emailattachment::validFilename($filename)) {
			$source = realpath(BASE_PATH) . '/files/contacts/' . $contactUrl . '/' . $filename;
			$target = DEEC_Emailattachment::getDirectory($module, $controller, (int)$document['id'], $context['clientid']);
			DEEC_Emailattachment::checkPath($source);
			if(is_file($source)) {
				if(!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) throw new RuntimeException('Attachment directory is not available');
				$path = $target . '/' . $filename;
				DEEC_Emailattachment::checkPath($path);
				if(file_exists($path)) return ['contactUrl' => $contactUrl, 'documentUrl' => $documentUrl, 'attachments' => $attachments];
				$output = @fopen($path, 'x');
				if(!$output) throw new RuntimeException('Attachment could not be stored');
				$input = fopen($source, 'rb');
				try {
					if(!$input || stream_copy_to_stream($input, $output) !== filesize($source)) throw new RuntimeException('Attachment could not be stored');
				} catch(Exception $e) {
					unlink($path);
					throw $e;
				} finally {
					if($input) fclose($input);
					fclose($output);
				}
				// Register the stored document copy using its verified parent, not posted upload data.
				try {
					$this->insert([
						'documentid' => $document['id'], 'module' => $module, 'controller' => $controller, 'clientid' => $context['clientid'],
						'filename' => $filename, 'location' => $target, 'filetype' => mime_content_type($path), 'filesize' => filesize($path),
						'ordering' => 1, 'uploaded' => $this->_date, 'uploadedby' => $this->_user['id'],
					]);
				} catch(Exception $e) {
					unlink($path);
					throw $e;
				}
				$attachments = $this->getEmailattachments($document['id'], $module, $controller);
			}
		}
		return ['contactUrl' => $contactUrl, 'documentUrl' => $documentUrl, 'attachments' => $attachments];
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
		$path = DEEC_Emailattachment::getPath($attachment, false);
		$this->update(['deleted' => 1], $this->attachmentWhere($attachment));
		$references = $this->fetchAll($this->select()->where('clientid = ?', (int)$attachment['clientid'])
			->where('documentid = ?', (int)$attachment['documentid'])->where('module = ?', $attachment['module'])
			->where('controller = ?', $attachment['controller'])->where('filename = ?', $attachment['filename'])->where('deleted = ?', 0));
		if(count($references) === 0 && is_file($path) && !unlink($path)) throw new RuntimeException('Attachment could not be deleted');
	}
}
