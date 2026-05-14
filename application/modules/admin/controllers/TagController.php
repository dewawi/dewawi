<?php

class Admin_TagController extends DEEC_Controller_AdminAction
{
	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Tag();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$tagsDb = new Admin_Model_DbTable_Tag();

		if($params['type'] == 'shop') {
			$tags = $tagsDb->getTags($params['type'], null, $params['shopid']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($tags));
		} else {
			$tags = $tagsDb->getTags($params['type']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($tags));
		}

		if($params['type'] == 'shop') {
			$slugs = array();
			$slugDb = new Admin_Model_DbTable_Slug();
			foreach($tags as $tag) {
				$slug = $slugDb->getSlug('shops', 'tag', $tag['shopid'], $tag['id']);
				$slugs[$tag['id']] = $slug['slug'];
			}
		}

		$this->view->form = $form;
		$this->view->tags = $tags;
		$this->view->slugs = $slugs;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Tag();
		$toolbar = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$tagsDb = new Admin_Model_DbTable_Tag();

		echo $params['type'];
		if($params['type'] == 'shop') {
			$tags = $tagsDb->getTags($params['type'], null, $params['shopid']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($tags));
		} else {
			$tags = $tagsDb->getTags($params['type']);
			$form->parentid->addMultiOptions($this->_helper->MenuStructure->getMenuStructure($tags));
		}

		$slugs = array();
		$slugDb = new Admin_Model_DbTable_Slug();
		foreach($tags as $tag) {
			$slug = $slugDb->getSlug('shops', 'tag', $tag['shopid'], $tag['id']);
			$slugs[$tag['id']] = $slug['slug'];
		}

		$this->view->form = $form;
		$this->view->tags = $tags;
		$this->view->slugs = $slugs;
		$this->view->toolbar = $toolbar;
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

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$tagDb = new Admin_Model_DbTable_Tag();
		$data = $tagDb->getTag($id);
		unset($data['id']);

		$tagsDb = new Admin_Model_DbTable_Tag();
		$tags = $tagsDb->getTags($data['type'], $data['parentid']);
		foreach($tags as $tag) {
			if(isset($tag['ordering'])) {
				if($tag['ordering'] > $data['ordering']) {
					if(!isset($tagsDb)) $tagsDb = new Admin_Model_DbTable_Tag();
					$tagsDb->sortTag($tag['id'], $tag['ordering'] + 1);
				}
			}
		}

		$data['title'] = $data['title'].' 2';
		$data['ordering'] = $data['ordering'] + 1;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$newId = $tagDb->addTag($data);
		//print_r($data);

		if($data['shopid']) {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slugDb->addSlug('shops', 'tag', $data['shopid'], $data['parentid'], $newId, $newId);
		}

		$childTags = $tagsDb->getTags($data['type'], $id);
		if(isset($childTags[$id]['childs'])) $this->copyChilds($id, $childTags, $newId);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$tagDb = new Admin_Model_DbTable_Tag();
			$tag = $tagDb->getTag($id);

			if(true) {
				$tagEntityDb = new Admin_Model_DbTable_Tagentity();
				$tagentities = $tagEntityDb->getTagentitiesByTag($id);
				if(!empty($tagentities)) {
					//Do not delete the tag if it is not empty
					$this->_flashMessenger->addMessage('MESSAGES_TAG_CANNOT_BE_DELETED_NOT_EMPTY');
				} else {
					$tagDb->deleteTag($id);
					//$this->setOrdering($tag['clientid'], $tag['type'], $tag['parentid']);

					if($category['shopid']) {
						$slugDb = new Admin_Model_DbTable_Slug();
						$slugDb->deleteSlug('shops', 'tag', $tag['shopid'], $id);
					}
					$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
				}
			}
		}
	}
}
