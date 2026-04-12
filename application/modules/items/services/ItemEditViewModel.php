<?php

class Items_Service_ItemEditViewModel
{
	public function build(int $itemId, array $user, array $itemRow): array
	{
		$get = new Items_Model_Get();
		$tags = $get->tags('items', 'item', $itemRow['id']);

		// Files in /files/items/{id}
		$files = $this->getItemFiles($itemId);

		return [
			'tags' => $tags,
			'files' => $files,
		];
	}

	protected function getItemFiles(int $itemId): array
	{
		$files = [];
		$path = BASE_PATH . '/files/items/' . $itemId;

		if (file_exists($path) && is_dir($path)) {
			$files['itemSpecific'] = [];

			if ($handle = opendir($path)) {
				while (false !== ($entry = readdir($handle))) {
					if (substr($entry, 0, 1) !== '.') {
						$files['itemSpecific'][] = $entry;
					}
				}
				closedir($handle);
			}
		}

		return $files;
	}
}
