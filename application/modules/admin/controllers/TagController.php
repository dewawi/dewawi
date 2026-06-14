<?php

class Admin_TagController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$form = new Admin_Form_Tag();
		$toolbar = new Admin_Form_Toolbar();
		$toolbarInline = new Admin_Form_ToolbarInline();

		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$tagsDb = new Admin_Model_DbTable_Tag();

		if ($params['type'] == 'shop') {
			$tags = $tagsDb->getTags($params['type'], null, $params['shopid']);
		} else {
			$tags = $tagsDb->getTags($params['type']);
		}

		$slugs = [];

		if ($params['type'] == 'shop') {
			$slugDb = new Admin_Model_DbTable_Slug();

			foreach ($tags as $tag) {
				try {
					$slug = $slugDb->getSlug('shops', 'tag', $tag['shopid'], $tag['id']);
					$slugs[$tag['id']] = $slug['slug'] ?? '';
				} catch (Exception $e) {
					$slugs[$tag['id']] = '';
				}
			}
		}

		$list = new Admin_Model_List_Tags();

		$list->configure([
			'items' => $tags,
			'options' => $options,
			'view' => $this->view,
			'module' => 'admin',
			'controller' => 'tag',
			'toolbarInline' => $toolbarInline,
			'context' => [
				'user' => $this->_user,
				'slugs' => $slugs,
				'type' => $params['type'],
			],
		]);

		$this->view->tags = $list;
		$this->view->form = $form;
		$this->view->toolbar = $toolbar;
		$this->view->toolbarInline = $toolbarInline;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Tag();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			//if($form->isValid($data)) {
				$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
				if(!isset($data['shopid'])) $data['shopid'] = 0;
				//$data['parentid'] = $params['parentid'];

				$tagDb = new Admin_Model_DbTable_Tag();
				$id = $tagDb->addTag($data);

				if($data['shopid']) {
					$slugDb = new Admin_Model_DbTable_Slug();
					$slugDb->addSlug('shops', 'tag', $data['shopid'], $data['parentid'], $id, $id);
				}

				//echo Zend_Json::encode($data);
				echo Zend_Json::encode($tagDb->getTag($id));
			//} else {
			//	echo Zend_Json::encode($data);
				//echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			//}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$tagDb = new Admin_Model_DbTable_Tag();
		$tag = $tagDb->getTag($id);

		/*if($this->isLocked($tag['locked'], $tag['lockedtime'])) {
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_LOCKED')));
			} else {
				$this->_flashMessenger->addMessage('MESSAGES_LOCKED');
				$this->_helper->redirector('index');
			}
		} else {
			$tagDb->lock($id);*/

			$form = new Admin_Form_Tag();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$tagsDb = new Admin_Model_DbTable_Tag();
			$tags = $tagsDb->getTags($params['type']);
			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				//if(isset($form->$element) && $form->isValidPartial($data)) { // to do add options for parentid before form validation
				if(true) {
					$tagDb = new Admin_Model_DbTable_Tag();
					if($element == 'parentid') {
						$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
						$tagArray = $tagDb->getTag($id);
						$tagDb->updateTag($id, $data);

						//sort old parent tag
						$this->setOrdering($tagArray['clientid'], $tagArray['type'], $tagArray['parentid']);

						$slugDb = new Admin_Model_DbTable_Slug();
						$slugDb->updateSlug('shops', 'tag', $tagArray['shopid'], $data['parentid'], $id);

						/*$tags = $this->_helper->Tags->getTags(null, $params['clientid'], $params['type'], $tagArray['parentid']);
						$i = 1;
						foreach($tags as $tag) {
							if(isset($tag['id'])) {
								//if($tag['ordering'] != $i)
								$tagDb->updateTag($tag['id'], array('ordering' => $i));
								++$i;
							}
						}*/
						echo Zend_Json::encode($tagDb->getTag($id));
					} elseif($element == 'slug') {
						$slugDb = new Admin_Model_DbTable_Slug();
						$slugDb->updateSlug('shops', 'tag', $tag['shopid'], $tag['parentid'], $id, $data['slug']);
						echo Zend_Json::encode(array('slug' => $data['slug']));
					} else {
						$tagDb->updateTag($id, $data);
						echo Zend_Json::encode($tagDb->getTag($id));
					}
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$form->populate($tag);

					//Toolbar
					$toolbar = new Admin_Form_Toolbar();

					//Tags
					$get = new Shops_Model_Get();
					$tags = $get->tags('shops', 'tag', $tag['id']);

					//Get slug
					$slugDb = new Admin_Model_DbTable_Slug();
					$slug = $slugDb->getSlug('shops', 'tag', $tag['shopid'], $id);
					$form->slug->setValue($slug['slug']);

					$this->view->form = $form;
					$this->view->tags = $tags;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
				}
			}
		//}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	protected function afterCreate(int $id, array $data): void
	{
		if (empty($data['shopid'])) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slugDb->addSlug(
			'shops',
			'tag',
			(int)$data['shopid'],
			(int)($data['parentid'] ?? 0),
			$id,
			$id
		);
	}

	protected function prepareEditRow(array $row): array
	{
		if (empty($row['shopid']) || empty($row['id'])) {
			$row['slug'] = '';
			return $row;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slug = $slugDb->getSlug(
			'shops',
			'tag',
			(int)$row['shopid'],
			(int)$row['id']
		);

		$row['slug'] = $slug['slug'] ?? '';

		return $row;
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		if (
			array_key_exists('parentid', $values)
			&& (int)$values['parentid'] !== (int)$row['parentid']
		) {
			$db = new Admin_Model_DbTable_Tag();

			$values['ordering'] = $db->getNextOrdering([
				'shopid' => (int)$row['shopid'],
				'parentid' => (int)$values['parentid'],
			]);
		}

		return $values;
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (array_key_exists('parentid', $values) && (int)$values['parentid'] !== (int)$oldRow['parentid']) {
			$db = new Admin_Model_DbTable_Tag();
			$db->normalizeOrderingByRow($oldRow);
		}

		if (empty($oldRow['shopid'])) {
			return;
		}

		if (!array_key_exists('slug', $values) && !array_key_exists('parentid', $values)) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();

		$slugDb->saveSlug(
			'shops',
			'tag',
			(int)$oldRow['shopid'],
			(int)($values['parentid'] ?? $oldRow['parentid']),
			$id,
			(string)($values['slug'] ?? '')
		);
	}

	protected function canDeleteRow(array $row): bool
	{
		$tagEntityDb = new Admin_Model_DbTable_Tagentity();
		$tagentities = $tagEntityDb->getTagentitiesByTag((int)$row['id']);

		if (!empty($tagentities)) {
			$this->_flashMessenger->addMessage('MESSAGES_TAG_CANNOT_BE_DELETED_NOT_EMPTY');
			return false;
		}

		return true;
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		if (!empty($newRow['shopid'])) {
			$slugDb = new Admin_Model_DbTable_Slug();

			$slugDb->addSlug(
				'shops',
				'tag',
				(int)$newRow['shopid'],
				(int)($newRow['parentid'] ?? 0),
				$newId,
				$newId
			);
		}

		$this->copyChilds($oldId, $newId, 'shops', 'tag');
	}

	protected function afterDelete(int $id, array $row): void
	{
		if (empty($row['shopid'])) {
			return;
		}

		$slugDb = new Admin_Model_DbTable_Slug();
		$slugDb->deleteSlug('shops', 'tag', (int)$row['shopid'], $id);
	}
}
