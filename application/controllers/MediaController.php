<?php

class MediaController extends Zend_Controller_Action
{
	protected $_date = null;
	protected $_user = null;
	protected $_flashMessenger = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');

		$this->view->client = Zend_Registry::get('Client');
		$this->view->user = $this->_user = Zend_Registry::get('User');

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
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

		if ($module === '' || $controller === '' || $parentId <= 0 || $type === '' || $path === '') {
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
			$fileName = $this->makeUniqueFileName($uploadDir, $fileName);

			$uploadFile = $uploadDir . $fileName;
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

		$id = (int)$this->_getParam('id', 0);
		$parentId = (int)$this->_getParam('parentid', 0);
		$path = trim((string)$this->_getParam('path', ''), '/');

		$mediaModel = new Application_Model_DbTable_Media();
		$media = $mediaModel->fetchRow(
			$mediaModel->getAdapter()->quoteInto('id = ?', $id)
		);

		if (!$media || (int)$media->clientid !== (int)$this->view->client['id']) {
			$this->_flashMessenger->addMessage('Media not found.');
			return $this->redirectFromTarget($parentId);
		}

		if ($path === '') {
			$path = trim((string)$media->controller, '/');
		}

		$filePath = BASE_PATH
			. '/media/'
			. $this->buildClientMediaPath()
			. '/'
			. $path
			. '/'
			. ltrim((string)$media->url, '/');

		if (is_file($filePath)) {
			unlink($filePath);
		}

		$mediaModel->deleteMedia($id);
		$this->reorderMedia($mediaModel, (array)$media->toArray());

		$this->_flashMessenger->addMessage('Media deleted successfully.');

		return $this->redirectFromTarget($parentId);
	}

	public function sortAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return;
		}

		$order = $this->_getParam('order', []);

		if (!is_array($order)) {
			return;
		}

		$mediaModel = new Application_Model_DbTable_Media();

		foreach ($order as $index => $id) {
			$mediaModel->update(
				[
					'ordering' => (int)$index + 1,
					'modified' => $this->_date,
					'modifiedby' => (int)$this->_user['id'],
				],
				[
					$mediaModel->getAdapter()->quoteInto('id = ?', (int)$id),
					$mediaModel->getAdapter()->quoteInto('clientid = ?', (int)$this->view->client['id']),
				]
			);
		}
	}

	protected function disableView(): void
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
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
