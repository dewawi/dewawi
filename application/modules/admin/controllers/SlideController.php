<?php

class Admin_SlideController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'slides',
			'list' => 'Admin_Model_List_Slides',
			'entity' => Admin_Model_Entity_Slide::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('ADMIN_NEW_SLIDE'),
			'shopid' => (int)$this->_getParam('shopid', 0),
		];
	}

	protected function getEntityContext(array $row): array
	{
		return [
			'module' => 'shops',
			'controller' => 'slide',
		];
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$this->copyMedia($oldId, $newId);
	}

	protected function afterDelete(int $id, array $row): void
	{
		$this->deleteMedia($id);
	}

	protected function copyMedia(int $oldId, int $newId): void
	{
		$mediaDb = new Application_Model_DbTable_Media();
		$media = $mediaDb->getMediaByContext('shops', 'slide', $oldId);

		foreach ($media as $file) {
			unset($file['id']);

			$file['parentid'] = $newId;
			$file['created'] = null;
			$file['createdby'] = 0;
			$file['modified'] = null;
			$file['modifiedby'] = 0;
			$file['locked'] = 0;
			$file['lockedtime'] = null;

			$mediaDb->addMedia($file);
		}
	}

	protected function deleteMedia(int $slideId): void
	{
		$mediaDb = new Application_Model_DbTable_Media();
		$mediaDb->deleteMediaByParentID($slideId, 'shops', 'slide');
	}
}
