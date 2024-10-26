<?php

class Admin_TagController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function getAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$element = $this->_getParam('element', null);
		$form = new Admin_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($form);
		if(isset($form->$element)) {
			$options = $form->$element->getMultiOptions();
			echo Zend_Json::encode($options);
		} else {
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS')));
		}
	}

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

	public function sortAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$data = $request->getPost();
			$tagDb = new Admin_Model_DbTable_Tag();
			$tag = $tagDb->getTag($data['id']);
			$orderings = $this->getOrdering($tag['clientid'], $tag['type'], $tag['parentid']);
			$currentOrdering = array_search($data['id'], $orderings);
			if(($data['ordering'] == 'down') && (isset($orderings[$currentOrdering+1]))) {
				$tagDb->sortTag($data['id'], $currentOrdering+1);
				$tagDb->sortTag($orderings[$currentOrdering+1], $currentOrdering);
			} elseif(($data['ordering'] == 'up') && (isset($orderings[$currentOrdering-1]))) {
				$tagDb->sortTag($data['id'], $currentOrdering-1);
				$tagDb->sortTag($orderings[$currentOrdering-1], $currentOrdering);
			} elseif($data['ordering'] > 0) {
				if($data['ordering'] < $currentOrdering) {
					$tagDb->sortTag($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering < $currentOrdering) && ($ordering >= $data['ordering'])) $tagDb->sortTag($id, $ordering+1);
					}
				} elseif($data['ordering'] > $currentOrdering) {
					$tagDb->sortTag($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $id) {
						if(($ordering > $currentOrdering) && ($ordering <= $data['ordering'])) $tagDb->sortTag($id, $ordering-1);
					}
				}
			}
			$this->setOrdering($tag['clientid'], $tag['type'], $tag['parentid']);
		}
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

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$tagDb = new Admin_Model_DbTable_Tag();
		$tag = $tagDb->getTag($id);
		if($this->isLocked($tag['locked'], $tag['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($tag['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$tagDb->lock($id);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$tagDb = new Admin_Model_DbTable_Tag();
		$tagDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$tagDb = new Admin_Model_DbTable_Tag();
		$tagDb->lock($id);
	}


	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Admin_Form_Tag();

		$form->isValid($this->_getAllParams());
		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function isLocked($locked, $lockedtime)
	{
		if($locked && ($locked != $this->_user['id'])) {
			$timeout = strtotime($lockedtime) + 300; // 5 minutes
			$timestamp = strtotime($this->_date);
			if($timeout < $timestamp) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	protected function setOrdering($clientid, $type, $parentid)
	{
		$i = 1;
		$tagsDb = new Admin_Model_DbTable_Tag();
		$tags = $tagsDb->getTags($type, $parentid);
		foreach($tags as $tag) {
			if(isset($tag['ordering'])) {
				//if($tag['ordering'] != $i) {
					if(!isset($tagsDb)) $tagsDb = new Admin_Model_DbTable_Tag();
					//print_r($tag);
					//print_r($i);
					$tagsDb->sortTag($tag['id'], $i);
					++$i;
				//}
			}
		}
	}

	protected function getOrdering($clientid, $type, $parentid)
	{
		$i = 1;
		$tagsDb = new Admin_Model_DbTable_Tag();
		$tags = $tagsDb->getTags($type, $parentid);
		$orderings = array();
		foreach($tags as $tag) {
			if(isset($tag['id'])) {
				$orderings[$i] = $tag['id'];
				++$i;
			}
		}
		return $orderings;
	}

	protected function getLatestOrdering($clientid, $type, $parentid)
	{
		$ordering = $this->getOrdering($clientid, $type, $parentid);
		end($ordering);
		return key($ordering);
	}

	protected function copyChilds($oldId, $tags, $newId)
	{
		foreach($tags[$oldId]['childs'] as $child) {
			$tagDb = new Admin_Model_DbTable_Tag();
			$data = $tagDb->getTag($child);
			unset($data['id']);
			$data['parentid'] = $newId;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			$data['locked'] = 0;
			$data['lockedtime'] = NULL;
			$newChildId = $tagDb->addTag($data);

			$childTags = $tagDb->getTags($data['type'], $child);
			if(isset($childTags[$child]['childs'])) $this->copyChilds($child, $childTags, $newChildId);
		}
	}
}
