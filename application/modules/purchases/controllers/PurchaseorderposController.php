<?php

class Purchases_PurchaseorderposController extends Zend_Controller_Action
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
		$this->_user = Zend_Registry::get('User');

		$this->_currency = new Zend_Currency();
		if(($this->view->action != "select") && ($this->view->action != "search"))
			$this->_currency->setFormat(array('display' => Zend_Currency::NO_SYMBOL));

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function indexAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();

		$purchaseorderid = $this->_getParam('purchaseorderid', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		//Get purchaseorder
		$purchaseorderDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorder = $purchaseorderDb->getPurchaseorder($purchaseorderid);

		//Get positions
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($purchaseorderid);

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		$forms = array();
        $taxes = array();
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$position->ordering] = $position->ordering;
		}
        if($purchaseorder['taxfree']) {
            $taxes[] = array('value' => 0, 'title' => 0);
        } else {
		    foreach($positions as $position) {
                if(!isset($taxes[$position->taxrate]) && isset($taxrates[$position->taxrate])) {
                    $taxes[$position->taxrate] = array();
                    $taxes[$position->taxrate]['value'] = 0;
                    $taxes[$position->taxrate]['title'] = $taxrates[$position->taxrate];
                }
            }
        }
		foreach($positions as $position) {
            if(isset($taxes[$position->taxrate])) $taxes[$position->taxrate]['value'] += ($position->price*$position->quantity*$position->taxrate/100);

			$position->total =  $this->_currency->toCurrency($position->price*$position->quantity);
			$position->price =  $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));

			$form = new Purchases_Form_Purchaseorderpos();
			$forms[$position->id] = $form->populate($position->toArray());
			$forms[$position->id]->uom->addMultiOptions($uoms);
			$forms[$position->id]->taxrate->addMultiOptions($taxrates);
			$forms[$position->id]->ordering->addMultiOptions($orderings);
		}

		$purchaseorder['subtotal'] = $this->_currency->toCurrency($purchaseorder['subtotal']);
		$purchaseorder['total'] = $this->_currency->toCurrency($purchaseorder['total']);
        foreach($taxes as $rate => $data) {
		    $taxes[$rate]['value'] = $this->_currency->toCurrency($data['value']);
        }
		$purchaseorder['taxes'] = $taxes;

		$this->view->forms = $forms;
		$this->view->purchaseorder = $purchaseorder;
		$this->view->toolbar = new Purchases_Form_ToolbarPositions();
	}

	public function applyAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$itemid = $this->_getParam('itemid', 0);
		$purchaseorderid = $this->_getParam('purchaseorderid', 0);

		$form = new Items_Form_Item();

		if($request->isPost()) {
		    header('Content-type: application/json');
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			$data = array();
			if($itemid && $purchaseorderid) {
				$item = new Items_Model_DbTable_Item();
				$item = $item->getItem($itemid);
				$data['purchaseorderid'] = $purchaseorderid;
				$data['itemid'] = $itemid;
				$data['sku'] = $item['sku'];
				$data['title'] = $item['title'];
				$data['image'] = $item['image'];
				$data['description'] = $item['description'];
				$data['price'] = $item['price'];
                if($item['taxid']) {
		            $taxrateDb = new Application_Model_DbTable_Taxrate();
				    $taxrate = $taxrateDb->getTaxrate($item['taxid']);
				    $data['taxrate'] = $taxrate['rate'];
                } else {
                    $data['taxrate'] = 0;
                }
				$data['quantity'] = 1;
				$data['total'] = $data['price']*$data['quantity'];
                if($item['taxid']) {
		            $uomDb = new Application_Model_DbTable_Uom();
				    $uom = $uomDb->getUom($item['uomid']);
				    $data['uom'] = $uom['title'];
                } else {
                    $data['uom'] = '';
                }
				$data['ordering'] = $this->getLatestOrdering($purchaseorderid) + 1;
				$data['created'] = $this->_date;
				$data['createdby'] = $this->_user['id'];
				$data['clientid'] = $this->_user['clientid'];
				$position = new Purchases_Model_DbTable_Purchaseorderpos();
				$position->addPosition($data);

				//Calculate
				$calculations = $this->_helper->Calculate($purchaseorderid, $this->_currency, $this->_date, $this->_user['id']);
			    echo Zend_Json::encode($calculations['locale']);
			} else {
				$form->populate($request->getPost());
			}
		} else {
			if($itemid > 0) {
				$item = new Items_Model_DbTable_Item();
				$form->populate($item->getItem($itemid));
			}
		}
		$this->view->form = $form;
	}

	public function addAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$purchaseorderid = $this->_getParam('purchaseorderid', 0);

		if($this->getRequest()->isPost()) {
			$data = array();
			$data['purchaseorderid'] = $purchaseorderid;
			$data['itemid'] = 0;
			$data['sku'] = '';
			$data['title'] = '';
			$data['image'] = '';
			$data['description'] = '';
			$data['price'] = 0;
			$data['taxrate'] = 0;
			$data['quantity'] = 1;
			$data['total'] = 0;
			$data['uom'] = '';
			$data['ordering'] = $this->getLatestOrdering($purchaseorderid) + 1;
			$data['created'] = $this->_date;
			$data['createdby'] = $this->_user['id'];
			$data['clientid'] = $this->_user['clientid'];
			$position = new Purchases_Model_DbTable_Purchaseorderpos();
			$position->addPosition($data);
		}
	}

	public function editAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$id = $this->_getParam('id', 0);
		$purchaseorderid = $this->_getParam('purchaseorderid', 0);

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		$form = new Purchases_Form_Purchaseorderpos();
		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->getOrdering($purchaseorderid));
		$form->taxrate->addMultiOptions($taxrates);

		if($request->isPost()) {
		    header('Content-type: application/json');
			$data = $request->getPost();
			$element = key($data);
			if(isset($form->$element) && $form->isValidPartial($data)) {
				$data['modified'] = $this->_date;
				$data['modifiedby'] = $this->_user['id'];
				if(($element == 'price') || ($element == 'quantity'))
					$data[$element] = Zend_Locale_Format::getNumber($data[$element],array('precision' => 2,'locale' => $locale));

				$position = new Purchases_Model_DbTable_Purchaseorderpos();
				$position->updatePosition($id, $data);

				if(($element == 'price') || ($element == 'quantity') || ($element == 'taxrate')) {
					$calculations = $this->_helper->Calculate($purchaseorderid, $this->_currency, $this->_date, $this->_user['id']);
			        echo Zend_Json::encode($calculations['locale']);
                }
			} else {
				throw new Exception('Form is invalid');
			}
		}
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
		    header('Content-type: application/json');
			$id = (int)$this->_getParam('id', 0);
			$position = new Purchases_Model_DbTable_Purchaseorderpos();
			$data = $position->getPosition($id);
			$orderings = $this->getOrdering($data['purchaseorderid']);
			foreach($orderings as $ordering => $positionId) {
				if($ordering > $data['ordering']) $position->updatePosition($positionId, array('ordering' => ($ordering+1)));
			}
			$data['ordering'] += 1;
			$data['created'] = $this->_date;
			$data['createdby'] = $this->_user['id'];
			$data['modified'] = '0000-00-00';
			$data['modifiedby'] = 0;
			unset($data['id']);
			$position->addPosition($data);

			//Calculate
			$calculations = $this->_helper->Calculate($data['purchaseorderid'], $this->_currency, $this->_date, $this->_user['id']);
	        echo Zend_Json::encode($calculations['locale']);
		}
	}

	public function sortAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$data = $request->getPost();
			$orderings = $this->getOrdering($data['purchaseorderid']);
			$currentOrdering = array_search($data['id'], $orderings); 
			$position = new Purchases_Model_DbTable_Purchaseorderpos();
			if($data['ordering'] == 'down') {
				$position->sortPosition($data['id'], $currentOrdering+1);
				$position->sortPosition($orderings[$currentOrdering+1], $currentOrdering);
			} elseif($data['ordering'] == 'up') {
				$position->sortPosition($data['id'], $currentOrdering-1);
				$position->sortPosition($orderings[$currentOrdering-1], $currentOrdering);
			} elseif($data['ordering'] > 0) {
				if($data['ordering'] < $currentOrdering) {
					$position->sortPosition($data['id'], $data['ordering']);
					foreach($orderings as  $ordering => $positionId) {
						if(($ordering < $currentOrdering) && ($ordering >= $data['ordering'])) $position->sortPosition($positionId, $ordering+1);
					}
				} elseif($data['ordering'] > $currentOrdering) {
					$position->sortPosition($data['id'], $data['ordering']);
					foreach($orderings as  $ordering => $positionId) {
						if(($ordering > $currentOrdering) && ($ordering <= $data['ordering'])) $position->sortPosition($positionId, $ordering-1);
					}
				}
			}
			$this->setOrdering($data['purchaseorderid']);
		}
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
		    header('Content-type: application/json');
			$data = $request->getPost();
			if($data['delete'] == 'Yes') {
				if(!is_array($data['id'])) {
					$data['id'] = array($data['id']);
				}
				$positionDb = new Purchases_Model_DbTable_Purchaseorderpos();
				$positionDb->deletePositions($data['id']);

				//Reorder and calculate
				$this->setOrdering($data['purchaseorderid']);
				$calculations = $this->_helper->Calculate($data['purchaseorderid'], $this->_currency, $this->_date, $this->_user['id']);
	            echo Zend_Json::encode($calculations['locale']);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$locale = Zend_Registry::get('Zend_Locale');

		$form = new Purchases_Form_Purchaseorderpos();

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->getOrdering($salesorderid));
		$form->taxrate->addMultiOptions($taxrates);

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function setOrdering($purchaseorderid)
	{
		$i = 1;
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($purchaseorderid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
				$positionsDb->sortPosition($position->id, $i);
			}
			++$i;
		}
	}

	protected function getOrdering($purchaseorderid)
	{
		$i = 1;
		$positionsDb = new Purchases_Model_DbTable_Purchaseorderpos();
		$positions = $positionsDb->getPositions($purchaseorderid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	protected function getLatestOrdering($purchaseorderid)
	{
		$ordering = $this->getOrdering($purchaseorderid);
		end($ordering);
		return key($ordering);
	}
}
