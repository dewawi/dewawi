<?php

class Items_LedgerController extends Zend_Controller_Action
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
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$ledger = $get->ledger($params, $options);

		$this->view->ledger = $ledger;
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
		$ledger = $get->ledger($params, $options);

		$this->view->ledger = $ledger;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function selectAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$ledger = $get->ledger($params, $options);

		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$catid = $this->_getParam('catid', 0);

		$form = new Items_Form_Ledger();
		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($form);

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data)) {
		        // normalize quantity
		        if (isset($data['quantity'])) {
		            $data['quantity'] = Zend_Locale_Format::getNumber(
		                $data['quantity'],
		                array('precision' => 4, 'locale' => $locale)
		            );
		        }

				$data['docid'] = 0;
				$data['doctype'] = '';
				$data['comment'] = '';
				$data['language'] = '';
				$data['warehouseid'] = 0;

	            $itemDb = new Items_Model_DbTable_Item();
				$ledgerDb = new Items_Model_DbTable_Ledger();
				$ledgerDb->addLedger($data);

				if($item = $itemDb->getItemBySKU($data['sku'])) {
		            // calculate delta and new quantity
		            $delta = (float)$data['quantity'];
		            if ($type === 'outflow') {
		                $delta = -$delta;
		            }
		            $newQty = ((float)$item['quantity']) + $delta;

					$itemDb->updateItem($item['id'], array(
                    	'quantity' => $newQty
                	));
				}

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

		$ledgerDb = new Items_Model_DbTable_Ledger();
		$ledger = $ledgerDb->getLedger($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'ledger', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $ledger['locked'], $ledger['lockedtime']);

			$form = new Items_Form_Ledger();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					if(isset($data['ledgerdate'])) {
						if(Zend_Date::isDate($data['ledgerdate'])) {
							$ledgerdate = new Zend_Date($data['ledgerdate'], Zend_Date::DATES, 'de');
							$data['ledgerdate'] = $ledgerdate->get('yyyy-MM-dd');
						} else {
							$data['ledgerdate'] = NULL;
						}
					}
					if(array_key_exists('quantity', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['quantity'] = Zend_Locale_Format::getNumber($data['quantity'],array('precision' => 2,'locale' => $locale));
					}

        			$itemDb = new Items_Model_DbTable_Item();
					$oldLedger = $ledger;

		            // update ledger
					$ledgerDb->updateLedger($id, $data);
					$newLedger = $ledgerDb->getLedger($id);

				    $oldSku = $oldLedger['sku'];
				    $newSku = $newLedger['sku'];

				    // signed quantities: inflow = +qty, outflow = -qty
				    $oldSigned = (float)$oldLedger['quantity'];
				    if($oldLedger['type'] === 'outflow') $oldSigned = -$oldSigned;

				    $newSigned = (float)$newLedger['quantity'];
				    if($newLedger['type'] === 'outflow') $newSigned = -$newSigned;


					if($oldSku !== $newSku) {
                		// SKU changed: revert old effect on old item, apply new effect on new item
						$oldItem = $itemDb->getItemBySKU($oldSku);
						$newItem = $itemDb->getItemBySKU($newSku);

						if($oldItem) {
						    // remove old effect from old item
						    $oldItemQty = ((float)$oldItem['quantity']) - $oldSigned;

						    $itemDb->updateItem($oldItem['id'], array('quantity' => $oldItemQty));
						}

						if($newItem) {
						    // apply new effect to new item
						    $newItemQty = ((float)$newItem['quantity']) + $newSigned;
						    $itemDb->updateItem($newItem['id'], array('quantity' => $newItemQty));
						}
					} else {
                		// same SKU: apply only the difference
				        $delta = $newSigned - $oldSigned;

				        if($delta != 0.0) {
				            if($item = $itemDb->getItemBySKU($oldSku)) {
						        $qty = ((float)$item['quantity']) + $delta;

						        $itemDb->updateItem($item['id'], array('quantity' => $qty));
				            }
				        }
					}

					echo Zend_Json::encode($ledgerDb->getLedger($id));
				} else {
					throw new Exception('Form is invalid');
				}
			} else {
				if($id > 0) {
					$data = $ledger;

					//Convert dates to the display format
					$ledgerdate = new Zend_Date($data['ledgerdate']);
					if($data['ledgerdate']) $data['ledgerdate'] = $ledgerdate->get('dd.MM.yyyy');

					// format quantity for display
					if(isset($data['quantity'])) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['quantity'] = Zend_Locale_Format::toNumber(
							$data['quantity'],
							array(
								'precision' => 2,
								'locale'    => $locale
							)
						);
					}

					$form->populate($data);

					//Toolbar
					$toolbar = new Items_Form_Toolbar();

					$this->view->form = $form;
					$this->view->ledger = $ledger;
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
		$item = new Items_Model_DbTable_Item();
		$data = $item->getItem($id);
		unset($data['id']);
		$data['quantity'] = 0;
		$data['inventory'] = 1;
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		echo $itemid = $item->addItem($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
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
