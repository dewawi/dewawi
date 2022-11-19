<?php

class Items_ItemlistController extends Zend_Controller_Action
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
		$this->view->client = Zend_Registry::get('Client');
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'item', $this->_flashMessenger);
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'media', $this->_flashMessenger);
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'export', $this->_flashMessenger);
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$params['order'] = 'id';
		$itemlists = $get->itemlists($params, $options);

		$this->view->itemlists = $itemlists;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$params['order'] = 'id';
		$itemlists = $get->itemlists($params, $options);

		$this->view->itemlists = $itemlists;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$catid = $this->_getParam('catid', 0);

		$data = array();
		$data['title'] = $this->view->translate('ITEM_LISTS_NEW_ITEM_LIST');
		$data['templateid'] = 0;
		$data['language'] = '';

		$itemlist = new Items_Model_DbTable_Itemlist();
		$id = $itemlist->addItemlist($data);

		$this->_helper->redirector->gotoSimple('edit', 'itemlist', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$itemlistDb = new Items_Model_DbTable_Itemlist();
		$itemlist = $itemlistDb->getItemlist($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'itemlist', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $itemlist['locked'], $itemlist['lockedtime']);

			$form = new Items_Form_Itemlist();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$itemlistDb->updateItemlist($id, $data);
					echo Zend_Json::encode($itemlistDb->getItemlist($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$form->populate($itemlist);

					//Toolbar
					$toolbar = new Items_Form_Toolbar();

					$this->view->form = $form;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
				}
			}
		}
		$this->view->messages = array_merge(
			$this->_helper->flashMessenger->getMessages(),
			$this->_helper->flashMessenger->getCurrentMessages()
		);
		$this->_helper->flashMessenger->clearCurrentMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$itemlist = new Items_Model_DbTable_Itemlist();
		$data = $itemlist->getItemlist($id);
		unset($data['id']);
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		echo $itemlistid = $itemlist->addItemlist($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);

		if($id) {
			$this->_helper->viewRenderer->setRender('pdf');
			//$this->_helper->viewRenderer->setNoRender();

			$itemlistDb = new Items_Model_DbTable_Itemlist();
			$itemlist = $itemlistDb->getItemlist($id);

			if($itemlist['templateid']) {
				$templateDb = new Application_Model_DbTable_Template();
				$template = $templateDb->getTemplate($itemlist['templateid']);
				$this->view->template = $template;
			}

			//Set language
			if($itemlist['language']) {
				$translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$itemlist['language']);
				Zend_Registry::set('Zend_Translate', $translate);
			}

			//Get footers
			$footerDb = new Application_Model_DbTable_Footer();
			$footers = $footerDb->getFooters($itemlist['templateid']);

			$params = json_decode($itemlist['params'], true);

			$toolbar = new Items_Form_Toolbar();
			$options = $this->_helper->Options->getOptions($toolbar);

			/*$items = array();
			$table = array();
			$attributes = array();
			$get = new Items_Model_Get();
			$attributesDb = new Items_Model_DbTable_Itematr();
			foreach($params as $catid => $data) {
				$items = $get->items(array('catid' => $catid, 'keyword' => '', 'tagid' => 0, 'order' => 'sku', 'sort' => 'asc', 'limit' => 0), $options)->toArray();
				$table[$catid]['title'] = $options['categories'][$catid]['title'];
				$table[$catid]['description'] = $options['categories'][$catid]['description'];
				foreach($items as $item) {
					foreach($data->rows as $row) {
						$table[$item['catid']]['title'] = $options['categories'][$item['catid']]['title'];
						$table[$item['catid']]['description'] = $options['categories'][$item['catid']]['description'];
						$table[$item['catid']][$item['id']][$row->name] = $item[$row->name];
					}
					$attributes[$item['id']] = $attributesDb->getPositions($item['id'])->toArray();
					if(isset($attributes[$item['id']]) && count($attributes[$item['id']])) {
						foreach($data->attributes as $attribute) {
							foreach($attributes[$item['id']] as $test) {
								if($test['title'] == $attribute->name) {
									$table[$item['catid']][$item['id']]['attributes'] = array($test['title'] => $test['description']);
								}
							}
						}
					}
				}
			}*/

			//print_r($attributes);
			//print_r($table);
			//print_r($options['categories']);

			$itemlists = array();
			$get = new Items_Model_Get();
			$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
			$currency = $currencyHelper->getCurrency();
			foreach($params as $catid => $data) {
				$items = $get->items(array('catid' => $catid, 'keyword' => '', 'tagid' => 0, 'order' => 'id', 'sort' => 'asc', 'limit' => 0), $options, true)->toArray();
				foreach($items as $item) {
					$currency = $currencyHelper->setCurrency($currency, $item['currency'], 'USE_SYMBOL');
					$item['cost'] = $currency->toCurrency($item['cost']);
					$item['price'] = $currency->toCurrency($item['price']);
					$itemlists[$catid][$item['id']] = $item;
				}
			}

			$itemAttributes = array();
			$attributesDb = new Items_Model_DbTable_Itematr();
			foreach($itemlists as $itemlistObject) {
				foreach($itemlistObject as $item) {
					$itemAttributes[$item['id']] = $attributesDb->getPositions($item['id'])->toArray();
				}
			}

			$itemOptions = array();
			$itemOptionSets = array();
			$optionsDb = new Items_Model_DbTable_Itemopt();
			$optionSetsDb = new Items_Model_DbTable_Itemoptset();
			foreach($itemlists as $itemlistObject) {
				foreach($itemlistObject as $item) {
					$positions = $optionsDb->getPositions($item['id'])->toArray();
					foreach($positions as $position) {
						$itemOptions[$item['catid']][$item['id']][$position['id']]['sku'] = $position['sku'];
						$itemOptions[$item['catid']][$item['id']][$position['id']]['title'] = $position['title'];
						$itemOptions[$item['catid']][$item['id']][$position['id']]['optsetid'] = $position['optsetid'];
						$itemOptions[$item['catid']][$item['id']][$position['id']]['price'] = str_replace('.0000', '', $position['price']);
					}
					$positionSets = $optionSetsDb->getPositionSets($item['id'])->toArray();
					foreach($positionSets as $positionSet) {
						$itemOptionSets[$item['catid']][md5($positionSet['title'])]['ids'][$item['id']] = $positionSet['id'];
						if(isset($itemOptionSets[$item['catid']][0]['title'])) {
							if(array_search($positionSet['title'], $itemOptionSets[$item['catid']][0]) === false) {
								$itemOptionSets[$item['catid']][md5($positionSet['title'])]['title'] = $positionSet['title'];
							}
						} else {
							$itemOptionSets[$item['catid']][md5($positionSet['title'])]['title'] = $positionSet['title'];
						}
					}
				}
			}

			$this->view->itemlist = $itemlist;
			$this->view->itemlists = $itemlists;
			$this->view->itemAttributes = $itemAttributes;
			$this->view->itemOptions = $itemOptions;
			$this->view->itemOptionSets = $itemOptionSets;
			$this->view->options = $options;
			$this->view->params = $params;
			$this->view->toolbar = $toolbar;
			$this->view->footers = $footers;
		}
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
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->lock($id, $this->_user['id']);
	}

	public function unlockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->keepalive($id);
	}

	public function validateAction()
	{
		$this->_helper->Validate();
	}
}
