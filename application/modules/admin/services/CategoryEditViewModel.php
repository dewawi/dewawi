<?php

class Admin_Service_CategoryEditViewModel
{
	public function build(int $categoryId, array $user, array $category): array
	{
		$module = $this->resolveModule((string)($category['type'] ?? ''));

		$get = new Admin_Model_Get();
		$tags = $get->tags($module, 'category', $categoryId);

		$mediaDb = new Application_Model_DbTable_Media();
		$mediaRows = $mediaDb->getMediaByParentID($categoryId, $module, 'category');
		$media = $mediaRows ? $mediaRows->toArray() : [];

		$imageForms = [];

		foreach ((array)$media as $image) {
			$imageId = (int)($image['id'] ?? 0);

			if ($imageId <= 0) {
				continue;
			}

			$imageForms[$imageId] = new Admin_Form_Image();
			$imageForms[$imageId]->title->setValue((string)($image['title'] ?? ''));
			$imageForms[$imageId]->title->setName('imagetitle' . $imageId);
		}

		$clientId = (int)($user['clientid'] ?? 0);
		$mediaPath = $this->buildClientMediaPath($clientId);

		$slug = null;

		if (($category['type'] ?? '') === 'shop') {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slug = $slugDb->getSlug(
				'shops',
				'category',
				(int)($category['shopid'] ?? 0),
				$categoryId
			);
		}

		return [
			'module' => $module,
			'tags' => $tags,
			'media' => $media,
			'imageForms' => $imageForms,
			'mediaPath' => $mediaPath,
			'slug' => $slug,
			'subfolders' => [
				'category' => $this->getSubfolders(BASE_PATH . '/media/' . $mediaPath . '/category/'),
				'downloads' => $this->getSubfolders(BASE_PATH . '/media/' . $mediaPath . '/downloads/'),
			],
		];
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
