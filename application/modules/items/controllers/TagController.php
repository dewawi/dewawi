<?php

class Items_TagController extends Zend_Controller_Action
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

	public function indexAction()
	{
		$request = $this->getRequest();
		$itemid = $this->_getParam('itemid', 0);
		$data = $request->getPost();
		$module = isset($data['module']) ? $data['module'] : 0;
		$controller = isset($data['controller']) ? $data['controller'] : 0;
		$documentid = isset($data['documentid']) ? $data['documentid'] : 0;

		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();
		$this->_helper->getHelper('layout')->disableLayout();

		//$toolbar = new Items_Form_Toolbar();
		//$options = $this->_helper->Options->getOptions($toolbar);
		//$params = $this->_helper->Params->getParams($toolbar, $options);

		$userDb = new Users_Model_DbTable_User();
		$users = $userDb->getUsers();

		$this->view->module = $module;
		$this->view->controller = $controller;
		$this->view->url = $this->_helper->Directory->getUrl($documentid);
		$this->view->users = $users;
		//$this->view->options = $options;
		//$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if(!$request->isPost()) return;

		$data = $request->getPost();
		$itemId = filter_var($data['itemid'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		$tagId = $data['tagid'] ?? 0;
		$tagId = filter_var($tagId === '' ? 0 : $tagId, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));

		if($itemId === false || $tagId === false) {
			$this->getResponse()->setHttpResponseCode(400);
			return $this->_helper->json(array('ok' => false, 'message' => 'invalid_request'));
		}

		$itemDb = new Items_Model_DbTable_Item();
		if(!$itemDb->getById($itemId)) {
			$this->getResponse()->setHttpResponseCode(404);
			return $this->_helper->json(array('ok' => false, 'message' => 'not_found'));
		}

		if($tagId) {
			$tagDb = new Application_Model_DbTable_Tag();
			$tags = $tagDb->getTags('items', 'item');
			if(!array_key_exists($tagId, $tags)) {
				$this->getResponse()->setHttpResponseCode(404);
				return $this->_helper->json(array('ok' => false, 'message' => 'not_found'));
			}
		}

		$tagEntityDb = new Application_Model_DbTable_Tagentity();
		$tagEntities = $tagEntityDb->getTagEntities('items', 'item', $itemId);
		$latestOrdering = 0;
		foreach($tagEntities as $tagEntity) {
			$latestOrdering = max($latestOrdering, (int)$tagEntity['ordering']);
			if($tagId && (int)$tagEntity['tagid'] === $tagId) {
				return $this->_helper->json(array('message' => $this->view->translate('TAG_ALREADY_EXISTS')));
			}
		}

		$id = $tagEntityDb->addTagEntity(array('tagid' => $tagId, 'entityid' => $itemId, 'module' => 'items', 'controller' => 'item', 'ordering' => $latestOrdering+1));
		$tagEntities = array_column($tagEntityDb->getTagEntities('items', 'item', $itemId), null, 'id');
		$tagEntity = $tagEntities[$id];

		if($tagId) {
			return $this->_helper->json($tagEntity);
		}

		echo $this->view->MultiForm('items', 'tag', $tagEntity);
	}

	public function editAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if(!$request->isPost()) return;

		$id = filter_var($this->_getParam('id', 0), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		$tag = $request->getPost('tag');
		if($id === false || !is_string($tag)) {
			$this->getResponse()->setHttpResponseCode(400);
			return $this->_helper->json(array('ok' => false, 'message' => 'invalid_request'));
		}

		if(!$this->getItemTagEntity($id)) {
			$this->getResponse()->setHttpResponseCode(404);
			return $this->_helper->json(array('ok' => false, 'message' => 'not_found'));
		}

		$form = new Items_Form_Tag();
		if(!$form->isValid(array('tag' => $tag))) {
			$this->getResponse()->setHttpResponseCode(400);
			return $this->_helper->json(array('ok' => false, 'message' => 'invalid_request'));
		}

		$tag = (string)$form->getValue('tag');
		$tagDb = new Application_Model_DbTable_Tag();
		$tags = $tagDb->getTags('items', 'item');
		$tagId = array_search($tag, $tags, true);
		if($tagId === false) {
			$tagId = $tagDb->addTag(array('title' => $tag, 'module' => 'items', 'controller' => 'item'));
		}

		$data = array('tagid' => $tagId);
		$tagEntityDb = new Application_Model_DbTable_Tagentity();
		$tagEntityDb->updateTagEntity($id, $data);
		return $this->_helper->json($data);
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = filter_var($this->_getParam('id', 0), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
			if($id === false) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(array('ok' => false, 'message' => 'invalid_request'));
			}

			if(!$this->getItemTagEntity($id)) {
				$this->getResponse()->setHttpResponseCode(404);
				return $this->_helper->json(array('ok' => false, 'message' => 'not_found'));
			}

			$tagEntityDb = new Application_Model_DbTable_Tagentity();
			$tagEntityDb->deleteTagEntity($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	protected function getItemTagEntity(int $id): ?array
	{
		$tagEntityDb = new Application_Model_DbTable_Tagentity();
		$row = $tagEntityDb->getById($id);
		if(!$row || $row['module'] !== 'items' || $row['controller'] !== 'item') return null;

		$itemDb = new Items_Model_DbTable_Item();
		return $itemDb->getById((int)$row['entityid']) ? $row : null;
	}
}
