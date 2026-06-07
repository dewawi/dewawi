<?php

class Admin_Service_EditViewModel
{
	public function build(int $id, array $user, array $row, array $context = []): array
	{
		$module = (string)($context['module'] ?? '');
		$controller = (string)($context['controller'] ?? '');

		if ($module === '' || $controller === '') {
			return [];
		}

		$vm = [];

		$this->appendTags($vm, $id, $module, $controller);
		$this->appendMedia($vm, $id, $user, $module, $controller);
		$this->appendSlug($vm, $id, $row, $module, $controller);

		return $vm;
	}

	protected function appendTags(array &$vm, int $id, string $module, string $controller): void
	{
		$get = new Admin_Model_Get();
		$tags = $get->tags($module, $controller, $id);

		if ($tags instanceof Zend_Db_Table_Rowset_Abstract) {
			$tags = $tags->toArray();
		}

		if (!is_array($tags) || count($tags) === 0) {
			return;
		}

		$vm['tags'] = $tags;
		$vm['module'] = $module;
	}

	protected function appendMedia(array &$vm, int $id, array $user, string $module, string $controller): void
	{
		$mediaDb = new Application_Model_DbTable_Media();
		$mediaRows = $mediaDb->getMediaByParentID($id, $module, $controller);

		if ($mediaRows instanceof Zend_Db_Table_Rowset_Abstract) {
			$mediaRows = $mediaRows->toArray();
		}

		if (!is_array($mediaRows) || count($mediaRows) === 0) {
			return;
		}

		$mediaPath = $this->buildClientMediaPath((int)($user['clientid'] ?? 0));

		$vm['media'] = $mediaRows;
		$vm['imageForms'] = $this->buildImageForms($mediaRows);
		$vm['mediaPath'] = $mediaPath;
		$vm['subfolders'] = $this->buildSubfolders($mediaPath);
	}

	protected function appendSlug(array &$vm, int $id, array $row, string $module, string $controller): void
	{
		if ((int)($row['shopid'] ?? 0) <= 0) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();
		$slug = $slugDb->getSlug($module, $controller, (int)$row['shopid'], $id);

		if (!$slug) {
			return;
		}

		$vm['slug'] = $slug;
	}

	protected function buildImageForms(array $mediaRows): array
	{
		$imageForms = [];

		foreach ($mediaRows as $mediaRow) {
			$imageId = (int)($mediaRow['id'] ?? 0);

			if ($imageId <= 0) {
				continue;
			}

			$imageForms[$imageId] = new Admin_Form_Image();
			$imageForms[$imageId]->setValue((string)($mediaRow['title'] ?? ''), 'title');
		}

		return $imageForms;
	}

	protected function buildClientMediaPath(int $clientId): string
	{
		$clientIdString = (string)$clientId;
		$dir1 = substr($clientIdString, 0, 1);
		$dir2 = strlen($clientIdString) > 1 ? substr($clientIdString, 1, 1) : '0';

		return $dir1 . '/' . $dir2 . '/' . $clientIdString;
	}

	protected function buildSubfolders(string $mediaPath): array
	{
		$paths = ['category', 'slide', 'downloads'];
		$subfolders = [];

		foreach ($paths as $path) {
			$subfolders[$path] = $this->getSubfolders(
				BASE_PATH . '/media/' . $mediaPath . '/' . $path . '/'
			);
		}

		return $subfolders;
	}

	protected function getSubfolders(string $path): array
	{
		if (!is_dir($path)) {
			return [];
		}

		$folders = [];

		foreach (scandir($path) as $folder) {
			if ($folder === '.' || $folder === '..') {
				continue;
			}

			if (is_dir($path . '/' . $folder)) {
				$folders[] = $folder;
			}
		}

		return $folders;
	}
}
