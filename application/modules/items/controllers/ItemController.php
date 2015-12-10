<?php

class Items_ItemController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	protected $_currency = null;

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

		$this->_currency = new Zend_Currency();
		if(($this->view->action != 'index') && ($this->view->action != 'select') && ($this->view->action != 'search') && ($this->view->action != 'download') && ($this->view->action != 'save') && ($this->view->action != 'preview') && ($this->view->action != 'get'))
			$this->_currency->setFormat(array('display' => Zend_Currency::NO_SYMBOL));

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->client = Zend_Registry::get('Client');
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$items = $this->search($params, $options['categories']);

		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->uoms = $this->_helper->Uom->getUoms();
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$items = $this->search($params, $options['categories']);

		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->uoms = $this->_helper->Uom->getUoms();
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function selectAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar, $this->_user['clientid']);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$items = $this->search($params, $options['categories']);

		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->uoms = $this->_helper->Uom->getUoms();
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$catid = $this->_getParam('catid', 0);

		$form = new Items_Form_Item();
		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data)) {
				$data['cost'] = $form->getValue('cost') ? Zend_Locale_Format::getNumber($form->getValue('cost'),array('precision' => 2,'locale' => $locale)) : 0;
				$data['price'] = $form->getValue('price') ? Zend_Locale_Format::getNumber($form->getValue('price'),array('precision' => 2,'locale' => $locale)) : 0;
				$data['margin'] = $form->getValue('margin') ? Zend_Locale_Format::getNumber($form->getValue('margin'),array('precision' => 2,'locale' => $locale)) : 0;
				$data['created'] = $this->_date;
				$data['createdby'] = $this->_user['id'];
				$data['clientid'] = $this->_user['clientid'];
				$item = new Items_Model_DbTable_Item();
				$item->addItem($data);
				$this->_helper->redirector('index');
			} else {
				$form->populate($data);
			}
		}
		$this->view->form = $form;
		$this->view->toolbar = $toolbar;
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$itemDb = new Items_Model_DbTable_Item();
		$item = $itemDb->getItem($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'item', null, array('id' => $id));
		} elseif($this->isLocked($item['locked'], $item['lockedtime'])) {
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
			$itemDb->lock($id, $this->_user['id'], $this->_date);

			$form = new Items_Form_Item();
			$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$data['modified'] = $this->_date;
					$data['modifiedby'] = $this->_user['id'];
					if(array_key_exists('price', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['price'] = Zend_Locale_Format::getNumber($data['price'],array('precision' => 2,'locale' => $locale));
					}
					if(array_key_exists('quantity', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['quantity'] = Zend_Locale_Format::getNumber($data['quantity'],array('precision' => 2,'locale' => $locale));
					}
					if(array_key_exists('margin', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['margin'] = Zend_Locale_Format::getNumber($data['margin'],array('precision' => 2,'locale' => $locale));
					}
					if(array_key_exists('weight', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['weight'] = Zend_Locale_Format::getNumber($data['weight'],array('precision' => 4,'locale' => $locale));
					}
					$item = new Items_Model_DbTable_Item();
					$item->updateItem($id, $data);
				} else {
					throw new Exception('Form is invalid');
				}
			} else {
				if($id > 0) {
					$item['cost'] = $this->_currency->toCurrency($item['cost']);
					$item['price'] = $this->_currency->toCurrency($item['price']);
					$item['margin'] = $this->_currency->toCurrency($item['margin']);
					$locale = Zend_Registry::get('Zend_Locale');
					$item['weight'] = $this->_currency->toCurrency($item['weight'],array('precision' => 4,'locale' => $locale));
					$form->populate($item);

					//Toolbar
					$toolbar = new Items_Form_Toolbar();

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$item = new Items_Model_DbTable_Item();
		$data = $item->getItem($id);
		unset($data['id']);
		$data['quantity'] = 0;
		$data['title'] = $data['title'].' 2';
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$data['modified'] = '0000-00-00';
		$data['modifiedby'] = 0;
		echo $itemid = $item->addItem($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	protected function uploadAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$form = new Application_Form_Upload();
		//$form->file->setDestination('/var/www/dewawi/files/');

		if ($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if ($form->isValid($formData)) {
				$contactid = $this->_getParam('contactid', 0);

				if(!file_exists(BASE_PATH.'/files/images/')) {
					mkdir(BASE_PATH.'/files/images/');
					chmod(BASE_PATH.'/files/images/', 0777);
				}

				/* Uploading Document File on Server */
				$upload = new Zend_File_Transfer_Adapter_Http();
				$upload->setDestination(BASE_PATH.'/files/images/');
				try {
					// upload received file(s)
					$upload->receive();
				} catch (Zend_File_Transfer_Exception $e) {
					$e->getMessage();
				}
			} else {
				$form->populate($formData);
			}
		}

		$this->view->form = $form;
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$item = new Items_Model_DbTable_Item();
			$item->deleteItem($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function lockAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$itemDb = new Items_Model_DbTable_Item();
		$item = $itemDb->getProcess($id);
		if($this->isLocked($item['locked'], $item['lockedtime'])) {
			$userDb = new Users_Model_DbTable_User();
			$user = $userDb->getUser($item['locked']);
			echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_ACCESS_DENIED_%1$s', $user['name'])));
		} else {
			$itemDb->lock($id, $this->_user['id'], $this->_date);
		}
	}

	public function unlockAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$itemDb = new Items_Model_DbTable_Item();
		$itemDb->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$itemDb = new Items_Model_DbTable_Item();
		$itemDb->lock($id, $this->_user['id'], $this->_date);
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Items_Form_Item();
		$options = $this->_helper->Options->getOptions($form, $this->_user['clientid']);

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function search($params, $categories)
	{
		$itemsDb = new Items_Model_DbTable_Item();

		$columns = array('title', 'sku', 'description');

		$query = '';
		if($params['keyword']) $query = $this->_helper->Query->getQueryKeyword($query, $params['keyword'], $columns);
		if($params['catid']) $query = $this->_helper->Query->getQueryCategory($query, $params['catid'], $categories);

		$items = $itemsDb->fetchAll(
			$itemsDb->select()
				->where($query ? $query : 1)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		foreach($items as $item) {
			if(strlen($item->description) > 43) $item->description = substr($item->description, 0, 40).'...';
			$item->cost = $this->_currency->toCurrency($item->cost);
			$item->price = $this->_currency->toCurrency($item->price);
		}

		return $items;
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
}
