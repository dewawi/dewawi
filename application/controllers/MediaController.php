<?php

class MediaController extends DEEC_Controller_Action
{
	public function init()
	{
		if ($this->getRequest()->getActionName() === 'manager') return;
		parent::init();
	}

	public function managerAction()
	{
		// The legacy manager includes share configuration and translation globals.
		global $config, $session, $lang_vars, $ftp, $descending;
		$this->disableView();
		$request = $this->getRequest();
		$manager = $this->_getParam('managerName');
		$script = $this->_getParam('managerScript');
		if (!in_array($manager, ['FileManager', 'MediaManager'], true) || !in_array($script, ['dialog', 'upload', 'execute', 'ajax_calls', 'force_download'], true)) return $this->rejectManager(404);
		if (!Zend_Auth::getInstance()->hasIdentity() || !Zend_Registry::isRegistered('User')) return $this->rejectManager(403);

		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		$managerSession = new Zend_Session_Namespace($manager);
		$context = $managerSession->context ?? [];
		if (($context['userid'] ?? 0) !== (int)$user['id'] || ($context['clientid'] ?? 0) !== (int)$user['clientid']) return $this->rejectManager(403);
		$module = $context['module'] ?? '';
		$controller = $context['controller'] ?? '';
		$owner = $client['modules'][$module] ?? $client['id'];
		if (($context['ownerid'] ?? 0) !== (int)$owner || ($manager === 'MediaManager' && $module !== 'admin')) return $this->rejectManager(403);
		$permission = new DEEC_Permission($user);
		$route = new Zend_Controller_Request_Simple($context['action'] ?? '', $controller, $module);
		$controller = $permission->resolveController($route);
		if ($module === 'admin' ? empty($user['admin']) : ($permission->hasModule($module) && !$permission->hasRoutePermission($route))) return $this->rejectManager(403);
		$writable = !empty($managerSession->writable) && ($module === 'admin' || !$permission->hasModule($module) || $permission->hasPermission($module, $controller, 'edit'));
		$managerSession->writable = $writable;

		$method = $request->getMethod();
		$action = $_GET['action'] ?? '';
		if (!in_array($method, ['GET', 'HEAD', 'POST'], true) || (in_array($script, ['upload', 'execute', 'force_download'], true) && $method !== 'POST')) {
			$this->getResponse()->setHeader('Allow', in_array($script, ['upload', 'execute', 'force_download'], true) ? 'POST' : 'GET, HEAD, POST', true);
			return $this->rejectManager(405);
		}
		if ($method === 'POST') {
			$token = $request->getHeader('X-CSRF-Token') ?: ($_POST['csrf_token'] ?? '');
			if (!is_string($token) || empty($managerSession->csrf_token) || !hash_equals($managerSession->csrf_token, $token)) return $this->rejectManager(403);
		}
		$writes = in_array($script, ['upload', 'execute'], true) || ($script === 'dialog' && (isset($_POST['submit']) || isset($_POST['url']))) || ($script === 'ajax_calls' && in_array($action, ['save_img', 'extract'], true));
		if ($writes && ($method !== 'POST' || !$writable)) return $this->rejectManager(403);
		if ($script === 'ajax_calls' && $method !== 'POST' && !in_array($action, ['new_file_form', 'media_preview', 'cad_preview', 'get_file'], true)) return $this->rejectManager(405);

		$folder = trim((string)($managerSession->subfolder ?? ''), '/');
		$base = BASE_PATH . ($manager === 'FileManager' ? '/files/' : '/media/');
		if ($manager === 'FileManager' && str_starts_with($folder, 'uploads/') && $module !== 'admin'
			&& ($module !== 'users' || $controller !== 'user' || ($context['id'] ?? 0) !== (int)$user['id'] || $folder !== 'uploads/' . (new DEEC_Directory())->getShortUrl($user['clientid']) . '/users/' . (int)$user['id'])) return $this->rejectManager(403);
		if ($folder === '' || $folder === 'uploads' || !$this->isManagerPath($base, '', $folder)) return $this->rejectManager(403);
		$scope = $base . $folder;
		if (!is_dir($scope)) {
			if ($script !== 'dialog' || !$writable) return $this->rejectManager(404);
			if (!mkdir($scope, 0775, true)) throw new RuntimeException('Unable to create file manager directory.');
		}
		$thumbs = BASE_PATH . '/cache/thumbs/' . ($manager === 'FileManager' ? 'files/' : 'media/') . $folder;
		DEEC_Emailattachment::checkPath($thumbs);
		if ($script === 'dialog' && !is_dir($thumbs) && !mkdir($thumbs, 0775, true)) throw new RuntimeException('Unable to create file manager thumbnail directory.');
		foreach (['path', 'fldr', 'paths'] as $field) {
			foreach ((array)($_POST[$field] ?? []) as $path) {
				if (!is_string($path) || !$this->isManagerPath($base, $folder, $path, $writes ? 'edit' : 'view')) return $this->rejectManager(403);
			}
		}
		if (isset($_GET['file']) && (!is_string($_GET['file']) || !$this->isManagerPath($base, $folder, $_GET['file']))) return $this->rejectManager(403);
		foreach (['name', 'names'] as $field) {
			foreach ((array)($_POST[$field] ?? []) as $name) {
				if (!is_string($name) || !DEEC_Directory::validUploadName(rawurldecode($name))) return $this->rejectManager(403);
				if (is_string($_POST['path'] ?? null) && !$this->isManagerPath($base, $folder, rtrim($_POST['path'], '/') . '/' . $name, $writes ? 'edit' : 'view')) return $this->rejectManager(403);
			}
		}
		foreach ((array)($_FILES['files']['name'] ?? []) as $key => $name) {
			if (!is_string($name) || !DEEC_Directory::validStoredUpload($name, (string)($_FILES['files']['tmp_name'][$key] ?? '')) || !is_string($_POST['fldr'] ?? null) || !$this->isManagerPath($base, $folder, rtrim($_POST['fldr'], '/') . '/' . $name, 'edit')) return $this->rejectManager(403);
		}
		if (!empty($managerSession->clipboard['path']) && !$this->isManagerPath($base, $folder, $managerSession->clipboard['path'])) unset($managerSession->clipboard, $managerSession->clipboard_action);
		$directories = $script === 'execute' && in_array($action, ['delete_files', 'delete_folder', 'rename_folder', 'chmod'], true) ? array_merge((array)($_POST['path'] ?? []), (array)($_POST['paths'] ?? [])) : [];
		if ($script === 'execute' && $action === 'paste_clipboard' && !empty($managerSession->clipboard['path'])) $directories[] = $managerSession->clipboard['path'];
		foreach ($directories as $path) {
			if (!is_dir($base . $path)) continue;
			$entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . $path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
			$count = 0;
			foreach ($entries as $entry) {
				$relative = substr($entry->getPathname(), strlen($base));
				$required = $action === 'delete_folder' ? 'delete' : ($action === 'paste_clipboard' && $managerSession->clipboard_action === 'copy' ? 'view' : 'edit');
				if (++$count > 1000 || !$this->isManagerPath($base, $folder, $relative, $required)) return $this->rejectManager(403);
			}
		}
		if ($script === 'dialog') {
			$relative = $_GET['fldr'] ?? ($managerSession->fldr ?? '');
			if (!is_string($relative) || !$this->isManagerPath($base, $folder, $folder . '/' . $relative) || !is_dir($scope . '/' . $relative)) $relative = '';
			$_GET['fldr'] = $managerSession->fldr = $relative;
			unset($_COOKIE['last_position']);
		}

		define('DEWAWI_FILE_MANAGER', $manager);
		$managerSession->verify = 'RESPONSIVEfilemanager';
		$directory = getcwd();
		chdir(BASE_PATH . '/library/' . $manager);
		$bufferLevel = ob_get_level();
		$download = null;
		$server = array_intersect_key($_SERVER, array_flip(['SCRIPT_FILENAME', 'SCRIPT_NAME', 'PHP_SELF']));
		$_SERVER['SCRIPT_FILENAME'] = getcwd() . '/' . $script . '.php';
		$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $request->getBaseUrl() . '/library/' . $manager . '/' . $script . '.php';
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true)->setHeader('X-Content-Type-Options', 'nosniff', true)->sendHeaders();
		try {
			if (in_array($script, ['upload', 'dialog'], true) && isset($_POST['url'])) {
				try {
					if (!is_string($_POST['url'])) throw new InvalidArgumentException('Invalid download URL');
					$download = $this->getUrlDownload()->receive($_POST['url']);
					register_shutdown_function(static function () use ($download): void { if (is_file($download['path'])) unlink($download['path']); });
					if (!DEEC_Directory::validStoredUpload($download['name'], $download['path']) || !is_string($_POST['fldr'] ?? null) || !$this->isManagerPath($base, $folder, rtrim($_POST['fldr'], '/') . '/' . $download['name'], 'edit')) throw new InvalidArgumentException('Invalid downloaded file');
					define('DEWAWI_URL_UPLOAD', $download['path']);
					$_FILES['files'] = ['name' => [$download['name']], 'tmp_name' => [$download['path']], 'size' => [$download['size']], 'type' => [(new finfo(FILEINFO_MIME_TYPE))->file($download['path'])], 'error' => [UPLOAD_ERR_OK]];
					unset($_POST['url']);
				} catch (Throwable $exception) {
					error_log((string)$exception);
					$this->getResponse()->setHttpResponseCode(400)->setHeader('Content-Type', 'application/json; charset=UTF-8', true)->setBody(json_encode(['error' => 'The file could not be downloaded.']));
					return;
				}
			}
			if ($script === 'dialog') ob_start();
			require $script . '.php';
			if ($script === 'dialog') {
				while (ob_get_level() > $bufferLevel + 1) ob_end_flush();
				$html = ob_get_clean();
				$scripts = '<script>window.dewawiFileManagerToken=' . json_encode($managerSession->csrf_token) . ';</script><script src="' . htmlspecialchars($request->getBaseUrl(), ENT_QUOTES, 'UTF-8') . '/js/filemanager.js"></script>';
				$this->getResponse()->setBody(str_replace('</head>', $scripts . '</head>', $html));
			} else {
				while (ob_get_level() > $bufferLevel) ob_end_flush();
			}
		} finally {
			if ($download && is_file($download['path'])) unlink($download['path']);
			$_SERVER = array_replace($_SERVER, $server);
			chdir($directory);
		}
	}

	protected function rejectManager(int $status)
	{
		$this->getResponse()->setHttpResponseCode($status)->setHeader('Content-Type', 'text/plain; charset=UTF-8', true)->setHeader('Cache-Control', 'no-store', true)->setBody($status === 404 ? 'Not found' : 'Access denied');
	}

	protected function getUrlDownload(): DEEC_UrlDownload
	{
		return new DEEC_UrlDownload();
	}

	protected function isManagerPath(string $base, string $folder, string $path, string $action = 'view'): bool
	{
		$path = rawurldecode($path);
		if (strpbrk($path, "\\\0") !== false || str_starts_with($path, '/') || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) return false;
		if ($folder !== '' && $path !== $folder && !str_starts_with($path, $folder . '/')) return false;
		$target = $base . $path;
		try { DEEC_Emailattachment::checkPath(rtrim($target, '/')); } catch (Exception $exception) { return false; }
		if ($base === BASE_PATH . '/files/' && is_file($target) && preg_match('~^(contacts|items)/~', $path)
			&& !(new Contacts_Model_DbTable_Emailattachment())->canReadStoredFile('files/' . $path, $action)) return false;
		while (!file_exists($target) && !is_link($target)) $target = dirname($target);
		$real = realpath($target);
		$root = realpath($base . $folder);
		return $real !== false && $root !== false && ($real === $root || str_starts_with($real, rtrim($root, '/') . '/'));
	}

	protected function getDbTableClass(): string
	{
		return Application_Model_DbTable_Media::class;
	}

	public function editAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isXmlHttpRequest() || !$this->getRequest()->isPost()) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'Invalid request.',
			]);
		}

		$id = (int)$this->_getParam('id', 0);

		$mediaModel = new Application_Model_DbTable_Media();
		$media = $mediaModel->fetchRow(
			$mediaModel->getAdapter()->quoteInto('id = ?', $id)
		);

		if (!$media || (int)$media->clientid !== (int)$this->view->client['id'] || !$this->getMediaFolder($media->module, $media->controller, (int)$media->parentid, $media->type)) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'Media not found.',
			]);
		}

		$data = [
			'title' => trim((string)$this->_getParam('title', '')),
			'description' => trim((string)$this->_getParam('description', '')),
			'target' => trim((string)$this->_getParam('target', '')),
			'ordering' => (int)$this->_getParam('ordering', 0),
			'modified' => $this->_date,
			'modifiedby' => (int)$this->_user['id'],
		];

		$mediaModel->update(
			$data,
			[
				$mediaModel->getAdapter()->quoteInto('id = ?', $id),
				$mediaModel->getAdapter()->quoteInto('clientid = ?', (int)$this->view->client['id']),
			]
		);

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
			'values' => $data,
		]);
	}

	public function uploadAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return;
		}

		$data = $this->getRequest()->getPost();

		$module = trim((string)($data['module'] ?? ''));
		$controller = trim((string)($data['controller'] ?? ''));
		$parentId = (int)($data['parentid'] ?? 0);
		$type = trim((string)($data['type'] ?? 'image'));
		$path = trim((string)($data['path'] ?? $type), '/');
		$subfolder = trim((string)($data['subfolder'] ?? ''), '/');

		if ($parentId <= 0 || $path !== $this->getMediaFolder($module, $controller, $parentId, $type)) {
			$this->_flashMessenger->addMessage('Invalid media context.');
			return $this->redirectBack($data, $parentId);
		}

		if (empty($_FILES['media']['name'][0])) {
			$this->_flashMessenger->addMessage('No media selected for upload.');
			return $this->redirectBack($data, $parentId);
		}

		$mediaPath = $this->buildClientMediaPath();
		$targetPath = $path;

		if ($subfolder !== '' && $subfolder !== '0') {
			$targetPath .= '/' . $subfolder;
		}

		$uploadDir = BASE_PATH . '/media/' . $mediaPath . '/' . $targetPath . '/';
		try { DEEC_Emailattachment::checkPath(rtrim($uploadDir, '/')); } catch (Exception $exception) { return $this->rejectManager(403); }

		if (!is_dir($uploadDir)) {
			mkdir($uploadDir, 0775, true);
		}

		if (!is_writable($uploadDir)) {
			$this->_flashMessenger->addMessage('Upload directory is not writable.');
			return $this->redirectBack($data, $parentId);
		}

		$mediaModel = new Application_Model_DbTable_Media();
		$uploaded = 0;

		foreach ($_FILES['media']['name'] as $key => $originalName) {
			if ((string)$originalName === '') {
				continue;
			}

			if ((int)($_FILES['media']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				$this->_flashMessenger->addMessage('Failed to upload media: ' . $originalName);
				continue;
			}

			$fileName = $this->sanitizeFileName($originalName);
			if (!DEEC_Directory::validStoredUpload($fileName, (string)($_FILES['media']['tmp_name'][$key] ?? ''))
				|| ($type === 'image' && !str_starts_with((string)(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['media']['tmp_name'][$key]), 'image/'))) {
				$this->_flashMessenger->addMessage('Invalid media file.');
				continue;
			}
			$fileName = $this->makeUniqueFileName($uploadDir, $fileName);

			$uploadFile = $uploadDir . $fileName;
			DEEC_Emailattachment::checkPath($uploadFile);
			$mediaUrl = $fileName;

			if ($subfolder !== '' && $subfolder !== '0') {
				$mediaUrl = $subfolder . '/' . $fileName;
			}

			if (!move_uploaded_file($_FILES['media']['tmp_name'][$key], $uploadFile)) {
				$this->_flashMessenger->addMessage('Failed to upload media: ' . $originalName);
				continue;
			}

			$ordering = $mediaModel->getMaxOrderingByContext($module, $controller, $parentId, $type) + 1;

			$mediaModel->addMedia([
				'parentid' => $parentId,
				'module' => $module,
				'controller' => $controller,
				'type' => $type,
				'title' => $originalName,
				'url' => $mediaUrl,
				'ordering' => $ordering,
			]);

			$uploaded++;
		}

		$this->_flashMessenger->addMessage(
			$uploaded > 0 ? 'Media uploaded successfully.' : 'No media uploaded.'
		);

		return $this->redirectBack($data, $parentId);
	}

	public function deleteAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isXmlHttpRequest() || !$this->getRequest()->isPost()) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'Invalid request.',
			]);
		}

		$id = (int)$this->_getParam('id', 0);
		$path = trim((string)$this->_getParam('path', ''), '/');

		$mediaModel = new Application_Model_DbTable_Media();
		$media = $mediaModel->fetchRow(
			$mediaModel->getAdapter()->quoteInto('id = ?', $id)
		);

		if (!$media || (int)$media->clientid !== (int)$this->view->client['id'] || !$this->getMediaFolder($media->module, $media->controller, (int)$media->parentid, $media->type)) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'Media not found.',
			]);
		}

		$folder = $this->getMediaFolder($media->module, $media->controller, (int)$media->parentid, $media->type);
		if ($path !== '' && $path !== $folder) return $this->rejectManager(403);
		$path = $folder;

		$filePath = BASE_PATH
			. '/media/'
			. $this->buildClientMediaPath()
			. '/'
			. $path
			. '/'
			. ltrim((string)$media->url, '/');

		try { DEEC_Emailattachment::checkPath($filePath); } catch (Exception $exception) { return $this->rejectManager(403); }
		if (is_file($filePath)) {
			unlink($filePath);
		}

		$mediaData = $media->toArray();

		$mediaModel->deleteMedia($id);
		$this->reorderMedia($mediaModel, $mediaData);

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
		]);
	}

	protected function disableView(): void
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
	}

	protected function getMediaFolder(string $module, string $controller, int $parentId, string $type): ?string
	{
		if ($module === 'shops' && in_array($controller, ['category', 'slide'], true) && !empty($this->_user['admin'])) {
			$class = 'Admin_Model_DbTable_' . ucfirst($controller);
			if (!(new $class())->getById($parentId)) return null;
			return $type === 'image' ? $controller : ($controller === 'category' && $type === 'download' ? 'download' : null);
		}
		if ($module === 'items' && $controller === 'item' && (new Contacts_Model_DbTable_Emailattachment())->getParentContext($parentId, $module, $controller, 'edit')) {
			return $type === 'image' ? 'images' : ($type === 'file' ? 'downloads' : null);
		}
		return null;
	}

	protected function buildClientMediaPath(): string
	{
		$clientId = (int)$this->view->client['id'];
		$client = (string)$clientId;

		$dir1 = substr($client, 0, 1);
		$dir2 = strlen($client) > 1 ? substr($client, 1, 1) : '0';

		return $dir1 . '/' . $dir2 . '/' . $client;
	}

	protected function sanitizeFileName(string $fileName): string
	{
		$fileName = basename($fileName);
		$fileName = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'], $fileName);
		$fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName);
		$fileName = trim($fileName, '-');

		return $fileName !== '' ? $fileName : 'media';
	}

	protected function makeUniqueFileName(string $uploadDir, string $fileName): string
	{
		$pathInfo = pathinfo($fileName);
		$name = $pathInfo['filename'] ?? 'media';
		$extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

		$uniqueName = $name . $extension;
		$counter = 1;

		while (file_exists($uploadDir . $uniqueName)) {
			$uniqueName = $name . '-' . $counter . $extension;
			$counter++;
		}

		return $uniqueName;
	}

	protected function reorderMedia(Application_Model_DbTable_Media $mediaModel, array $media): void
	{
		if (
			empty($media['module'])
			|| empty($media['controller'])
			|| empty($media['type'])
			|| empty($media['parentid'])
		) {
			return;
		}

		$rows = $mediaModel->getMediaByContext(
			(string)$media['module'],
			(string)$media['controller'],
			(int)$media['parentid'],
			(string)$media['type']
		);

		foreach ($rows as $index => $row) {
			$mediaModel->update(
				['ordering' => $index + 1],
				$mediaModel->getAdapter()->quoteInto('id = ?', (int)$row['id'])
			);
		}
	}

	protected function redirectBack(array $data, int $parentId)
	{
		$targetModule = isset($data['admin']) ? 'admin' : ($data['module'] ?? 'default');
		$targetController = $data['redirect_controller'] ?? $data['controller'] ?? 'index';
		$targetAction = $data['redirect_action'] ?? 'edit';

		return $this->_helper->redirector->gotoSimple(
			$targetAction,
			$targetController,
			$targetModule,
			['id' => $parentId]
		);
	}

	protected function redirectFromTarget(int $parentId)
	{
		$target = $this->_getParam('url', null);

		if ($target) {
			$url = explode('|', $target);

			if (count($url) === 3) {
				return $this->_helper->redirector->gotoSimple(
					$url[2],
					$url[1],
					$url[0],
					['id' => $parentId]
				);
			}
		}

		return $this->_helper->redirector->gotoSimple('index', 'index', 'default');
	}
}
