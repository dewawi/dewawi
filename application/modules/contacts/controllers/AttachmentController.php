<?php

class Contacts_AttachmentController extends DEEC_Controller_Action
{
	public function init()
	{
		// The base initializer creates directories before the parent has been authorized.
		$this->_helper->getHelper('layout')->setLayout('plain');
	}

	protected function getUploadAdapter()
	{
		return new Zend_File_Transfer_Adapter_Http();
	}

	private function reject(int $status = 404): void
	{
		$this->disableView();
		$this->getResponse()->setHttpResponseCode($status)->setHeader('Cache-Control', 'private, no-store', true)->setHeader('X-Content-Type-Options', 'nosniff', true);
	}

	public function uploadAction()
	{
		$id = $this->_getParam('id', 0);
		$module = $this->_getParam('cmodule', '');
		$controller = $this->_getParam('ccontroller', '');
		$db = new Contacts_Model_DbTable_Emailattachment();
		$context = $db->getParentContext($id, $module, $controller, 'upload');
		if(!$context) return $this->reject();

		$this->view->form = new Application_Form_Upload();
		$this->view->uploads = [];
		$this->view->documentUrl = (new DEEC_Directory())->getUrl((string)$id, (string)$context['clientid']);
		if(!$this->getRequest()->isPost()) return;

		$staging = null;
		try {
			$destination = DEEC_Emailattachment::getDirectory($module, $controller, (int)$id, $context['clientid']);
			$upload = $this->getUploadAdapter();
			$upload->addValidator('Count', false, 10);
			$upload->addValidator('Size', false, ['min' => 1, 'max' => 10240000]);
			$upload->addValidator('Extension', false, 'pdf,jpg,jpeg,png,gif,csv,zip');
			$files = $upload->getFileInfo();
			if(!$files || count($files) > 10) return $this->reject(400);
			$names = [];
			foreach($files as $file => $info) {
				if(!$upload->isUploaded($file) || !$upload->isValid($file) || !DEEC_Emailattachment::validUpload((string)$info['name'], (string)$info['tmp_name'])) return $this->reject(400);
				$path = $destination . '/' . $info['name'];
				DEEC_Emailattachment::checkPath($path);
				if(file_exists($path) || isset($names[$info['name']])) return $this->reject(409);
				$names[$info['name']] = true;
			}

			if(!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) throw new RuntimeException('Attachment directory is not available');
			$staging = sys_get_temp_dir() . '/dewawi-attachment-' . bin2hex(random_bytes(16));
			if(!mkdir($staging, 0700)) throw new RuntimeException('Attachment staging is not available');
			$upload->setDestination($staging);
			foreach($files as $file => $info) {
				if(!$upload->receive($file)) throw new RuntimeException('Attachment upload failed');
				$source = $staging . '/' . $info['name'];
				if(!DEEC_Emailattachment::validUpload($info['name'], $source)) throw new RuntimeException('Invalid attachment upload');
				$path = $destination . '/' . $info['name'];
				DEEC_Emailattachment::checkPath($path);
				$output = @fopen($path, 'x');
				if(!$output) throw new RuntimeException('Attachment already exists');
				$input = fopen($source, 'rb');
				try {
					if(!$input || stream_copy_to_stream($input, $output) !== filesize($source)) throw new RuntimeException('Attachment upload failed');
				} catch(Exception $e) {
					unlink($path);
					throw $e;
				} finally {
					if($input) fclose($input);
					fclose($output);
				}
				try {
					$data = [
						'documentid' => (int)$id, 'module' => $module, 'controller' => $controller, 'clientid' => $context['clientid'],
						'filename' => $info['name'], 'location' => $destination, 'filetype' => mime_content_type($path),
						'filesize' => filesize($path), 'ordering' => $db->setClientId($context['clientid'])->getNextOrdering(['documentid' => (int)$id, 'module' => $module, 'controller' => $controller]),
					];
					$data['id'] = $db->addEmailattachment($data);
				} catch(Exception $e) {
					unlink($path);
					throw $e;
				}
				$this->view->uploads[$data['id']] = $data;
			}
		} catch(Exception $e) {
			$this->reject(400);
		} finally {
			if($staging !== null && is_dir($staging)) {
				foreach(new FilesystemIterator($staging) as $file) unlink($file->getPathname());
				rmdir($staging);
			}
		}
	}

	public function downloadAction()
	{
		$this->disableView();
		if(!in_array($this->getRequest()->getMethod(), ['GET', 'HEAD'], true)) return $this->reject(405);
		$db = new Contacts_Model_DbTable_Emailattachment();
		try {
			if($this->_getParam('attachmentPath') !== null) {
				$path = parse_url($this->getRequest()->getRequestUri(), PHP_URL_PATH);
				$prefix = rtrim($this->getRequest()->getBaseUrl(), '/') . '/files/attachments/';
				if(!is_string($path) || !str_starts_with($path, $prefix)) return $this->reject();
				$parts = explode('/', rawurldecode(substr($path, strlen($prefix))));
				if(count($parts) !== 9) return $this->reject();
				[$module, $controller, $c1, $c2, $clientId, $d1, $d2, $documentId, $filename] = $parts;
				$attachment = null;
				foreach($db->getEmailattachments($documentId, $module, $controller) as $row) {
					$relative = $row['module'] . '/' . $row['controller'] . '/' . (new DEEC_Directory())->getUrl((string)$row['documentid'], (string)$row['clientid']) . '/' . $row['filename'];
					if($relative === implode('/', $parts)) { $attachment = $row; break; }
				}
			} elseif($this->_getParam('messageid') !== null) {
				$filename = $this->_getParam('filename', '');
				$attachment = is_string($filename) ? $db->getForMessage($this->_getParam('messageid'), $filename) : null;
			} else {
				$attachment = $db->getEmailattachment($this->_getParam('id', 0));
			}
			if(!$attachment) return $this->reject();
			$path = DEEC_Emailattachment::getPath($attachment);
		} catch(Exception $e) {
			return $this->reject();
		}

		$stream = @fopen($path, 'rb');
		if(!$stream) return $this->reject();
		$stat = fstat($stream);
		$response = $this->getResponse();
		$response->setHeader('Content-Type', 'application/octet-stream', true);
		$response->setHeader('Content-Disposition', 'attachment; filename="' . rawurlencode($attachment['filename']) . '"; filename*=UTF-8\'\'' . rawurlencode($attachment['filename']), true);
		$response->setHeader('Content-Length', (string)$stat['size'], true);
		$response->setHeader('X-Content-Type-Options', 'nosniff', true);
		$response->setHeader('Cache-Control', 'private, no-store', true);
		$this->sendAttachmentFile($stream);
	}

	public function fileAction()
	{
		$this->disableView();
		$request = $this->getRequest();
		if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) return $this->reject(405);
		$uri = parse_url($request->getRequestUri(), PHP_URL_PATH);
		$prefix = rtrim($request->getBaseUrl(), '/') . '/';
		if (!is_string($uri) || !str_starts_with($uri, $prefix)) return $this->reject();
		$relative = rawurldecode(substr($uri, strlen($prefix)));
		try {
			$db = new Contacts_Model_DbTable_Emailattachment();
			$publishedPath = $db->getPublishedFilePath($relative);
			$public = $publishedPath !== null;
			if (!$public) {
				$session = new Zend_Session_Namespace('MultiStepForm');
				$grant = $session->downloadFile ?? [];
				$site = Zend_Registry::isRegistered('SiteContext') ? Zend_Registry::get('SiteContext') : null;
				$inquiry = $site && ($grant['siteid'] ?? 0) === (int)$site->getSiteId() && ($grant['clientid'] ?? 0) === (int)$site->getClientId() && ($grant['relative'] ?? '') === $relative;
				if ($inquiry) $inquiry = (new Shops_Model_DbTable_Quote())->getById((int)($grant['quoteid'] ?? 0)) !== null;
				if (!$inquiry && !$db->canReadStoredFile($relative)) return $this->reject();
			}
			$path = DEEC_Directory::storedPath($publishedPath ?? $relative);
		} catch (Exception $exception) {
			if ($exception instanceof Zend_Db_Exception) error_log((string)$exception);
			return $this->reject();
		}
		$stream = @fopen($path, 'rb');
		if (!$stream) return $this->reject();
		$type = (new finfo(FILEINFO_MIME_TYPE))->file($path);
		$inline = $type === 'application/pdf' || preg_match('~^(?:image|audio|video)/~', $type);
		$filename = rawurlencode(basename($path));
		$response = $this->getResponse();
		$response->setHeader('Content-Type', $inline ? $type : 'application/octet-stream', true);
		$response->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"; filename*=UTF-8\'\'' . $filename, true);
		$response->setHeader('Content-Length', (string)fstat($stream)['size'], true);
		$response->setHeader('X-Content-Type-Options', 'nosniff', true);
		$response->setHeader('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'", true);
		$response->setHeader('Cache-Control', $public ? 'public, max-age=3600' : 'private, no-store', true);
		$this->sendAttachmentFile($stream);
	}

	protected function sendAttachmentFile($stream): void
	{
		$this->getResponse()->sendHeaders();
		if($this->getRequest()->getMethod() !== 'HEAD') fpassthru($stream);
		fclose($stream);
		exit;
	}

	public function deleteAction()
	{
		$this->disableView();
		if(!$this->getRequest()->isPost()) return $this->reject(405);
		$data = json_decode($this->getRequest()->getRawBody(), true);
		$ids = is_array($data) ? ($data['id'] ?? null) : null;
		if(!is_array($ids) || !$ids) return $this->reject(400);
		$db = new Contacts_Model_DbTable_Emailattachment();
		try {
			// Validate the complete batch before deleting any attachment.
			foreach($ids as $id) {
				$attachment = $db->getEmailattachment($id, 'delete');
				if(!$attachment) return $this->reject();
				DEEC_Emailattachment::getPath($attachment, false);
			}
			foreach(array_unique($ids) as $id) $db->deleteEmailattachment($id);
		} catch(Exception $e) {
			return $this->reject();
		}
		return $this->_helper->json(['ok' => true, 'deletedIds' => array_values(array_unique(array_map('intval', $ids)))]);
	}
}
