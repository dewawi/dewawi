<?php

class Admin_Service_EditViewModel
{
	public function build(int $id, array $user, array $row): array
	{
		$vm = [];

		if ($this->hasTags($row)) {
			$module = $this->resolveModule((string)($row['type'] ?? ''));
			$controller = 'category';

			$get = new Admin_Model_Get();
			$vm['tags'] = $get->tags($module, $controller, $id);
			$vm['module'] = $module;
		}

		if ($this->hasMedia($row)) {
			$module = $this->resolveModule((string)($row['type'] ?? ''));
			$controller = 'category';

			$mediaDb = new Application_Model_DbTable_Media();
			$mediaRows = $mediaDb->getMediaByParentID($id, $module, $controller);

			if ($mediaRows instanceof Zend_Db_Table_Rowset_Abstract) {
				$mediaRows = $mediaRows->toArray();
			}

			if (!is_array($mediaRows)) {
				$mediaRows = [];
			}

			$vm['media'] = $mediaRows;
			$vm['imageForms'] = $this->buildImageForms($mediaRows);
			$vm['mediaPath'] = $this->buildClientMediaPath((int)($user['clientid'] ?? 0));
			$vm['subfolders'] = [
				'category' => $this->getSubfolders(BASE_PATH . '/media/' . $vm['mediaPath'] . '/category/'),
				'downloads' => $this->getSubfolders(BASE_PATH . '/media/' . $vm['mediaPath'] . '/downloads/'),
			];
		}

		if ($this->hasSlug($row)) {
			$slugDb = new Admin_Model_DbTable_Slug();

			$vm['slug'] = $slugDb->getSlug(
				'shops',
				'category',
				(int)($row['shopid'] ?? 0),
				$id
			);
		}

		return $vm;
	}

	protected function hasTags(array $row): bool
	{
		return ($row['type'] ?? '') !== '';
	}

	protected function hasMedia(array $row): bool
	{
		return ($row['type'] ?? '') !== '';
	}

	protected function hasSlug(array $row): bool
	{
		return ($row['type'] ?? '') === 'shop';
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
			$imageForms[$imageId]->title->setValue((string)($mediaRow['title'] ?? ''));
			$imageForms[$imageId]->title->setName('imagetitle' . $imageId);
		}

		return $imageForms;
	}

	protected function resolveModule(string $type): string
	{
		switch ($type) {
			case 'item':
				return 'items';
			case 'contact':
				return 'contacts';
			case 'shop':
				return 'shops';
			default:
				return 'admin';
		}
	}

	protected function buildClientMediaPath(int $clientId): string
	{
		$clientIdString = (string)$clientId;
		$dir1 = substr($clientIdString, 0, 1);
		$dir2 = strlen($clientIdString) > 1 ? substr($clientIdString, 1, 1) : '0';

		return $dir1 . '/' . $dir2 . '/' . $clientIdString;
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
