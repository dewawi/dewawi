<?php

class Contacts_Model_DbTable_Emailmessageattachment extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'emailmessageattachment';
	protected ?string $orderingField = 'ordering';
	protected ?string $deletedField = null;

	public function storeCopy(int $messageId, array $source, int $ordering): array
	{
		$message = (new Contacts_Model_DbTable_Emailmessage())->getById($messageId);
		if (!$message || !$this->canReadMessage($message) || $ordering < 1) throw new RuntimeException('Email message is not available');
		$sourcePath = (string)($source['path'] ?? '');
		$filename = (string)($source['filename'] ?? '');
		if (!DEEC_Emailattachment::validFilename($filename) || !is_file($sourcePath) || !is_readable($sourcePath) || is_link($sourcePath)) throw new RuntimeException('Selected email attachment is unavailable');
		$clientId = (int)$message['clientid'];
		$existing = $this->fetchRow($this->select()->where('clientid = ?', $clientId)->where('emailmessageid = ?', $messageId)->where('ordering = ?', $ordering));
		if ($existing) {
			$data = $existing->toArray();
			$this->assertCopy($data);
			if (!hash_equals((string)$data['sha256'], hash_file('sha256', $sourcePath)) || $data['filename'] !== $filename) throw new RuntimeException('Email attachment copy differs');
			return $data;
		}
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if (!preg_match('/^[a-z0-9]{1,16}$/D', $extension)) $extension = 'bin';
		$relative = 'files/attachments/emailmessage/' . $this->shard($clientId) . '/' . intdiv($messageId, 1000) . '/' . $messageId . '/' . $ordering . '.' . $extension;
		$target = BASE_PATH . '/' . $relative;
		DEEC_Emailattachment::checkPath($target);
		$this->ensureDirectory(dirname($target));
		$output = @fopen($target, 'x+b');
		if (!$output) throw new RuntimeException('Email attachment copy target is occupied');
		$input = @fopen($sourcePath, 'rb');
		try {
			$size = filesize($sourcePath);
			if (!$input || stream_copy_to_stream($input, $output) !== $size || !fflush($output) || (function_exists('fsync') && !fsync($output))) throw new RuntimeException('Email attachment copy failed');
		} catch (Throwable $exception) {
			fclose($output); if ($input) fclose($input); unlink($target); throw $exception;
		}
		fclose($output); if ($input) fclose($input);
		$hash = hash_file('sha256', $sourcePath);
		if (filesize($target) !== $size || !hash_equals($hash, hash_file('sha256', $target))) { unlink($target); throw new RuntimeException('Email attachment copy verification failed'); }
		$data = [
			'emailmessageid' => $messageId, 'documentarchiveid' => $source['documentarchiveid'] ?? null, 'emailattachmentid' => $source['emailattachmentid'] ?? null,
			'filename' => $filename, 'filetype' => (string)((new finfo(FILEINFO_MIME_TYPE))->file($target) ?: 'application/octet-stream'),
			'filesize' => $size, 'sha256' => $hash, 'path' => $relative, 'ordering' => $ordering, 'clientid' => $clientId,
			'created' => $this->_date, 'createdby' => (int)($this->_user['id'] ?? 0),
		];
		try { $this->insert($data); }
		catch (Throwable $exception) { unlink($target); throw $exception; }
		$data['id'] = (int)$this->getAdapter()->lastInsertId();
		return $data;
	}

	public function getMessageAttachments(int $messageId): array
	{
		$message = (new Contacts_Model_DbTable_Emailmessage())->getById($messageId);
		if (!$message || !$this->canReadMessage($message)) return [];
		return $this->fetchAll($this->select()->where('clientid = ?', (int)$message['clientid'])->where('emailmessageid = ?', $messageId)->order('ordering ASC')->order('id ASC'))->toArray();
	}

	public function getMessageAttachment(int $id): ?array
	{
		$row = $this->fetchRow($this->select()->where('id = ?', $id));
		if (!$row) return null;
		$data = $row->toArray();
		$message = (new Contacts_Model_DbTable_Emailmessage())->getById((int)$data['emailmessageid']);
		if (!$message || (int)$message['clientid'] !== (int)$data['clientid'] || !$this->canReadMessage($message)) return null;
		$this->assertCopy($data);
		return $data;
	}

	public function getPath(array $copy): string
	{
		$this->assertCopy($copy);
		return BASE_PATH . '/' . $copy['path'];
	}

	private function assertCopy(array $copy): void
	{
		$prefix = 'files/attachments/emailmessage/' . $this->shard((int)$copy['clientid']) . '/' . intdiv((int)$copy['emailmessageid'], 1000) . '/' . (int)$copy['emailmessageid'] . '/';
		if (!str_starts_with((string)$copy['path'], $prefix) || !preg_match('~/[1-9][0-9]*\.[a-z0-9]{1,16}$~D', (string)$copy['path'])) throw new RuntimeException('Invalid email attachment copy path');
		$path = BASE_PATH . '/' . $copy['path'];
		DEEC_Emailattachment::checkPath($path);
		if (!is_file($path) || !is_readable($path) || is_link($path) || (int)$copy['filesize'] !== filesize($path) || !hash_equals((string)$copy['sha256'], hash_file('sha256', $path))) throw new RuntimeException('Email attachment copy integrity check failed');
	}

	private function shard(int $id): string { $value = (string)$id; return $value[0] . '/' . ($value[1] ?? '0') . '/' . $value; }
	private function canReadMessage(array $message): bool
	{
		$module = (string)($message['module'] ?? '');
		$controller = (string)($message['controller'] ?? '');
		$parentId = $module === 'contacts' ? (int)($message['contactid'] ?? 0) : ((int)($message['campaignid'] ?? 0) ?: (int)($message['documentid'] ?? 0));
		return $parentId > 0 && (new Contacts_Model_DbTable_Emailattachment())->getParentContext($parentId, $module, $controller) !== null;
	}
	private function ensureDirectory(string $directory): void
	{
		$base = realpath(BASE_PATH);
		if (!$base || !str_starts_with($directory, $base . '/')) throw new RuntimeException('Invalid email attachment directory');
		$current = $base;
		foreach (explode('/', substr($directory, strlen($base) + 1)) as $part) {
			$current .= '/' . $part;
			if (is_link($current)) throw new RuntimeException('Email attachment directory contains a symlink');
			if (file_exists($current)) { if (!is_dir($current)) throw new RuntimeException('Email attachment directory collision'); continue; }
			if (!mkdir($current, 0775)) throw new RuntimeException('Email attachment directory is unavailable');
		}
	}
}
