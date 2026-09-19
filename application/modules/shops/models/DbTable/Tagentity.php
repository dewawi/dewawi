<?php

class Shops_Model_DbTable_Tagentity extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'tagentity';
	protected ?string $siteField = null;

	public function getByTagId(int $tagId, string $module, string $controller): array
	{
		return $this->fetchAll(
			$this->getPublicSelect()
				->where('tagid = ?', $tagId)
				->where('module = ?', $module)
				->where('controller = ?', $controller)
				->order('ordering ASC')
		)->toArray();
	}

	public function getByEntityId(int $entityId, string $module, string $controller): array
	{
		$select = $this->select()
			->setIntegrityCheck(false)
			->from(['t' => 'tagentity'], [
				'id',
				'tagid',
				'entityid',
				'ordering',
			])
			->join(
				['tag' => 'tag'],
				'tag.id = t.tagid',
				[
					'tag' => 'title',
				]
			)
			->where('t.entityid = ?', $entityId)
			->where('t.module = ?', $module)
			->where('t.controller = ?', $controller)
			->where('t.clientid = ?', $this->getClientId())
			->where('t.deleted = ?', 0)
			->where('tag.shopid = ?', $this->getSiteId())
			->where('tag.clientid = ?', $this->getClientId())
			->where('tag.module = ?', $module)
			->where('tag.controller = ?', $controller)
			->where('tag.deleted = ?', 0)
			->order('t.ordering ASC');

		return $this->fetchAll($select)->toArray();
	}
}
