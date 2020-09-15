<?php

class Sales_DeliveryorderposController extends Zend_Controller_Action
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
		$this->_user = Zend_Registry::get('User');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function indexAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();

		$deliveryorderid = $this->_getParam('deliveryorderid', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		//Get deliveryorder
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($deliveryorderid);

		//Get positions
		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($deliveryorderid);

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get price rule actions
		$priceruleactionDb = new Application_Model_DbTable_Priceruleaction();
		$priceruleactions = $priceruleactionDb->getPriceruleactions();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		//Get currency
		$currency = $this->_helper->Currency->getCurrency($deliveryorder['currency']);

		$forms = array();
		$taxes = array();
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$position->ordering] = $position->ordering;
		}
		if($deliveryorder['taxfree']) {
			$taxes[] = array('value' => 0, 'title' => 0);
		} else {
			foreach($positions as $position) {
				if(!isset($taxes[$position->taxrate]) && array_search($position->taxrate, $taxrates)) {
					$taxes[$position->taxrate] = array();
					$taxes[$position->taxrate]['value'] = 0;
					$taxes[$position->taxrate]['title'] = Zend_Locale_Format::toNumber($position->taxrate,array('precision' => 1,'locale' => $locale)).' %';
				}
			}
		}
		foreach($positions as $position) {
			if(!$deliveryorder['taxfree'] && array_search($position->taxrate, $taxrates))
				$taxes[$position->taxrate]['value'] += ($position->price*$position->quantity*$position->taxrate/100);

			$price = $position->price;
			if($position->priceruleamount && $position->priceruleaction) {
				if($position->priceruleaction == 'bypercent')
					$price = $price*(100-$position->priceruleamount)/100;
				elseif($position->priceruleaction == 'byfixed')
					$price = ($price-$position->priceruleamount);
				elseif($position->priceruleaction == 'topercent')
					$price = $price*(100+$position->priceruleamount)/100;
				elseif($position->priceruleaction == 'tofixed')
					$price = ($price+$position->priceruleamount);
			}
			$position->total = $currency->toCurrency($price*$position->quantity);
			$position->price = $currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
			$position->priceruleamount = $currency->toCurrency($position->priceruleamount);

			$form = new Sales_Form_Deliveryorderpos();
			$forms[$position->id] = $form->populate($position->toArray());
			$forms[$position->id]->uom->addMultiOptions($uoms);
			if($position->uom) {
				$uom = array_search($position->uom, $uoms);
				if($uom) $forms[$position->id]->uom->setValue($uom);
			}
			$forms[$position->id]->priceruleaction->addMultiOptions($priceruleactions);
			$forms[$position->id]->ordering->addMultiOptions($orderings);
			$forms[$position->id]->taxrate->setValue(array_search($position->taxrate, $taxrates));
			foreach($taxrates as $id => $value)
				$forms[$position->id]->taxrate->addMultiOption($id, Zend_Locale_Format::toNumber($value,array('precision' => 1,'locale' => $locale)).' %');
		}

		$deliveryorder['subtotal'] = $currency->toCurrency($deliveryorder['subtotal']);
		$deliveryorder['total'] = $currency->toCurrency($deliveryorder['total']);
		foreach($taxes as $rate => $data) {
			$taxes[$rate]['value'] = $currency->toCurrency($data['value']);
		}
		$deliveryorder['taxes'] = $taxes;

		$this->view->forms = $forms;
		$this->view->deliveryorder = $deliveryorder;
		$this->view->toolbar = new Sales_Form_ToolbarPositions();
	}

	public function applyAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$itemid = $this->_getParam('itemid', 0);
		$deliveryorderid = $this->_getParam('deliveryorderid', 0);

		$form = new Items_Form_Item();

		if($request->isPost()) {
			header('Content-type: application/json');
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			$data = array();
			if($itemid && $deliveryorderid) {
				//Get item
				$item = new Items_Model_DbTable_Item();
				$item = $item->getItem($itemid);

				//Get delivery order
				$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
				$deliveryorder = $deliveryorderDb->getDeliveryorder($deliveryorderid);

				//Check price rules
				$data = $this->_helper->PriceRule($deliveryorder['contactid'], $item, $data, $this->_helper);

				//Check currency
				if($deliveryorder['currency'] == $item['currency']) {
					$data['price'] = $item['price'];
				} else {
					$data['price'] = $this->_helper->Currency($item['currency'], $deliveryorder['currency'], $item['price'], $this->_helper);
				}
				$data['currency'] = $deliveryorder['currency'];

				$data['deliveryorderid'] = $deliveryorderid;
				$data['itemid'] = $itemid;
				$data['sku'] = $item['sku'];
				$data['title'] = $item['title'];
				$data['image'] = $item['image'];
				$data['description'] = $item['description'];
				if($item['taxid']) {
					$taxrateDb = new Application_Model_DbTable_Taxrate();
					$taxrate = $taxrateDb->getTaxrate($item['taxid']);
					$data['taxrate'] = $taxrate['rate'];
				} else {
					$data['taxrate'] = 0;
				}
				$data['quantity'] = 1;
				$data['total'] = $data['price']*$data['quantity'];
				if($item['uomid']) {
					$uomDb = new Application_Model_DbTable_Uom();
					$uom = $uomDb->getUom($item['uomid']);
					$data['uom'] = $uom['title'];
				} else {
					$data['uom'] = '';
				}
				$data['ordering'] = $this->getLatestOrdering($deliveryorderid) + 1;

				$position = new Sales_Model_DbTable_Deliveryorderpos();
				$position->addPosition($data);

				//Calculate
				$calculations = $this->_helper->Calculate($deliveryorderid, $this->_date, $this->_user['id']);
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

		//Get delivery order data
		$deliveryorderid = $this->_getParam('deliveryorderid', 0);
		$deliveryorderDb = new Sales_Model_DbTable_Deliveryorder();
		$deliveryorder = $deliveryorderDb->getDeliveryorder($deliveryorderid);

		//Get primary tax rate
		$taxrates = new Application_Model_DbTable_Taxrate();
		$taxrate = $taxrates->getPrimaryTaxrate();

		if($this->getRequest()->isPost()) {
			$data = array();
			$data['deliveryorderid'] = $deliveryorderid;
			$data['itemid'] = 0;
			$data['sku'] = '';
			$data['title'] = '';
			$data['image'] = '';
			$data['description'] = '';
			$data['price'] = 0;
			$data['taxrate'] = $taxrate['rate'];
			$data['quantity'] = 1;
			$data['total'] = 0;
			$data['currency'] = $deliveryorder['currency'];
			$data['uom'] = '';
			$data['ordering'] = $this->getLatestOrdering($deliveryorderid) + 1;
			$position = new Sales_Model_DbTable_Deliveryorderpos();
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
		$deliveryorderid = $this->_getParam('deliveryorderid', 0);

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		$form = new Sales_Form_Deliveryorderpos();
		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->getOrdering($deliveryorderid));
		$form->taxrate->addMultiOptions($taxrates);

		if($request->isPost()) {
			header('Content-type: application/json');
			$data = $request->getPost();
			$element = key($data);
			if(isset($form->$element) && $form->isValidPartial($data)) {
				if(($element == 'taxrate') && ($data[$element] != 0))
					$data['taxrate'] = $taxrates[$data['taxrate']];
				if(($element == 'price') || ($element == 'quantity') || ($element == 'priceruleamount'))
					$data[$element] = Zend_Locale_Format::getNumber($data[$element],array('precision' => 2,'locale' => $locale));
				if(($element == 'uom') && ($data[$element] != 0))
					$data['uom'] = $uoms[$data[$element]];

				$position = new Sales_Model_DbTable_Deliveryorderpos();
				$position->updatePosition($id, $data);

				if(($element == 'price') || ($element == 'quantity') || ($element == 'taxrate') || ($element == 'priceruleamount') || ($element == 'priceruleaction')) {
					$calculations = $this->_helper->Calculate($deliveryorderid, $this->_date, $this->_user['id']);
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
			$position = new Sales_Model_DbTable_Deliveryorderpos();
			$data = $position->getPosition($id);
			$orderings = $this->getOrdering($data['deliveryorderid']);
			foreach($orderings as $ordering => $positionId) {
				if($ordering > $data['ordering']) $position->updatePosition($positionId, array('ordering' => ($ordering+1)));
			}
			$data['ordering'] += 1;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			unset($data['id']);
			$position->addPosition($data);

			//Calculate
			$calculations = $this->_helper->Calculate($data['deliveryorderid'], $this->_date, $this->_user['id']);
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
			$orderings = $this->getOrdering($data['deliveryorderid']);
			$currentOrdering = array_search($data['id'], $orderings); 
			$position = new Sales_Model_DbTable_Deliveryorderpos();
			if($data['ordering'] == 'down') {
				$position->sortPosition($data['id'], $currentOrdering+1);
				$position->sortPosition($orderings[$currentOrdering+1], $currentOrdering);
			} elseif($data['ordering'] == 'up') {
				$position->sortPosition($data['id'], $currentOrdering-1);
				$position->sortPosition($orderings[$currentOrdering-1], $currentOrdering);
			} elseif($data['ordering'] > 0) {
				if($data['ordering'] < $currentOrdering) {
					$position->sortPosition($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $positionId) {
						if(($ordering < $currentOrdering) && ($ordering >= $data['ordering'])) $position->sortPosition($positionId, $ordering+1);
					}
				} elseif($data['ordering'] > $currentOrdering) {
					$position->sortPosition($data['id'], $data['ordering']);
					foreach($orderings as $ordering => $positionId) {
						if(($ordering > $currentOrdering) && ($ordering <= $data['ordering'])) $position->sortPosition($positionId, $ordering-1);
					}
				}
			}
			$this->setOrdering($data['deliveryorderid']);
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
				$positionDb = new Sales_Model_DbTable_Deliveryorderpos();
				$positionDb->deletePositions($data['id']);

				//Reorder and calculate
				$this->setOrdering($data['deliveryorderid']);
				$calculations = $this->_helper->Calculate($data['deliveryorderid'], $this->_date, $this->_user['id']);
				echo Zend_Json::encode($calculations['locale']);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$locale = Zend_Registry::get('Zend_Locale');

		$form = new Sales_Form_Deliveryorderpos();

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

	protected function setOrdering($deliveryorderid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($deliveryorderid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
				$positionsDb->sortPosition($position->id, $i);
			}
			++$i;
		}
	}

	protected function getOrdering($deliveryorderid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Deliveryorderpos();
		$positions = $positionsDb->getPositions($deliveryorderid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	protected function getLatestOrdering($deliveryorderid)
	{
		$ordering = $this->getOrdering($deliveryorderid);
		end($ordering);
		return key($ordering);
	}
}
