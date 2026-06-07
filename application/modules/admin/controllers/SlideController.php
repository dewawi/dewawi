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
			'shopid' => (int)$this->_getParam('shopid', 0),
		];
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = (int)$this->_getParam('id', 0);

		$slideDb = new Admin_Model_DbTable_Slide();
		$data = $slideDb->getSlide($id);

		unset($data['id']);

		$data['title'] = trim((string)($data['title'] ?? '')) . ' 2';
		$data['ordering'] = ((int)($data['ordering'] ?? 0)) + 1;
		$data['modified'] = null;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = null;

		$newId = $slideDb->addSlide($data);

		$this->copyMedia($id, $newId);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if (!$this->getRequest()->isPost()) {
			return;
		}

		$id = (int)$this->_getParam('id', 0);

		$slideDb = new Admin_Model_DbTable_Slide();
		$slide = $slideDb->getSlide($id);

		$slideDb->deleteSlide($id);

		$this->deleteMedia($id);
		$this->setOrdering((int)$slide['shopid'], '', 'slide');

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
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
