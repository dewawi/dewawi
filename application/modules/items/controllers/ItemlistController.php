<?php

class Items_ItemlistController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'items',
			'list' => 'Items_Model_List_Items',
			'entity' => Items_Model_Entity_Item::listConfig(),
		]);
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
			//$this->_helper->viewRenderer->setRender('pdf');
			$this->_helper->viewRenderer->setNoRender();

			$itemlistDb = new Items_Model_DbTable_Itemlist();
			$itemlist = $itemlistDb->getItemlist($id);

			if($itemlist['templateid']) {
				$templateDb = new Application_Model_DbTable_Template();
				$template = $templateDb->getTemplate($itemlist['templateid']);
				$this->view->template = $template;
			}

			//Get footers
			$footerDb = new Application_Model_DbTable_Footer();
			$footers = $footerDb->getFooters($itemlist['templateid']);

			$params = json_decode($itemlist['params'], true);

			$toolbar = new Items_Form_Toolbar();
			$options = $this->_helper->Options->getOptions($toolbar);

			$itemlists = array();
			$get = new Items_Model_Get();
			$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
			$currency = $currencyHelper->getCurrency();
			foreach($params as $catid => $data) {
				list($items, $records) = $get->items(array('catid' => $catid, 'keyword' => '', 'tagid' => 0, 'order' => 'id', 'sort' => 'asc', 'limit' => 0), $options, true);
				foreach($items as $item) {
					//Get and use price rules
					$pricerules = $this->_helper->PriceRule->getPriceRules($item);
					$item['price'] = $this->_helper->PriceRule->usePriceRules($pricerules, $item['price']);

					$currency = $currencyHelper->setCurrency($currency, $item['currency'], 'USE_SYMBOL');
					$item['cost'] = $currency->toCurrency($item['cost']);
					if(true) $item['price'] = floor($item['price']); //TODO
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
						//Get and use price rules for options
						if($position['price'] > 0) {
							$pricerules = $this->_helper->PriceRule->getPriceRules($item);
							$position['price'] = $this->_helper->PriceRule->usePriceRules($pricerules, $position['price']);
						}

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

			$images = array();
			$imageDb = new Application_Model_DbTable_Media();
			//$images['items'] = $imageDb->getItemMedia($items);
			$images['categories'] = $imageDb->getMediaByParentID($catid, 'items', 'category');
			//print_r($catid);
			//print_r($images['categories']);

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
}
