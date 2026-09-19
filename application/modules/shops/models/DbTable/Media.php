<?php

class Shops_Model_DbTable_Media extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'media';
	protected ?string $siteField = null;

	public function getItemMedia($items): array
	{
		$images = [];

		foreach ($items as $item) {
			$images[$item->id] = $this->getMedia((int)$item->id, 'items', 'item');
		}

		return $images;
	}

	public function getCategoryMedia(array $categories): array
	{
		$images = [];

		foreach ($categories as $category) {
			$images[$category['id']] = $this->getMedia((int)$category['id'], 'shops', 'category');
		}

		return $images;
	}

	public function getCategoryMediaById(int $id): array
	{
		return $this->getMedia($id, 'shops', 'category');
	}

	public function getMedia(int $parentId, string $module, string $controller): array
	{
		$rows = $this->getByParentId($parentId, $module, $controller);
		$media = [];

		foreach ($rows as $row) {
			$media[] = [
				'url' => $row['url'],
				'title' => $row['title'],
				'type' => $row['type'],
			];
		}

		return $media;
	}

	public function getSlideImages(int $slideId): array
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->where('parentid = ?', $slideId)
				->where('module = ?', 'shops')
				->where('controller = ?', 'slide')
				->where('type = ?', 'image')
				->order('ordering ASC')
				->order('id ASC')
		)->toArray();
	}
}
