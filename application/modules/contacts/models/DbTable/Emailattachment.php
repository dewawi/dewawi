<?php

class Contacts_Model_DbTable_Emailattachment extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'emailattachment';

	public function getParentContext($documentId, $module, $controller, string $action = 'view'): ?array
	{
		if(!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) return null;
		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		$id = filter_var($documentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if(!$id || empty($user['id']) || empty($user['clientid']) || !is_string($module) || !is_string($controller)) return null;
		$contactClientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$user['clientid'];
		if($contactClientId <= 0) return null;
		if(!DEEC_Permission::isDocument($module, $controller) && !in_array([$module, $controller], [
			['contacts', 'contact'], ['contacts', 'contactperson'], ['processes', 'process'], ['tasks', 'task'], ['campaigns', 'campaign'],
		], true)) return null;

		$permission = new DEEC_Permission($user);
		$permissionController = $module === 'contacts' ? 'contact' : $controller;
		if($permission->hasModule($module) && (!$permission->hasActionPermission($module, $permissionController, 'view') || !$permission->hasActionPermission($module, $permissionController, $action))) return null;
		$ownerClientId = $module === 'contacts' ? $contactClientId : (int)$user['clientid'];
		$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
		$parentDb = new $class();
		$parentDb->setClientId($ownerClientId);
		$parent = $parentDb->getById($id);
		if(!$parent) return null;
		if($module === 'contacts' && $controller === 'contactperson') {
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contactDb->setClientId($ownerClientId);
			if(!$contactDb->getById((int)$parent['parentid'])) return null;
		}

		return ['parent' => $parent, 'clientid' => $ownerClientId, 'storageClients' => array_values(array_unique([$ownerClientId, $contactClientId]))];
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
