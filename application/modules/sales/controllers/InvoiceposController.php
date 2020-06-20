<?php

class Sales_InvoiceposController extends Zend_Controller_Action
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

		$invoiceid = $this->_getParam('invoiceid', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		//Get invoice
		$invoiceDb = new Sales_Model_DbTable_Invoice();
		$invoice = $invoiceDb->getInvoice($invoiceid);

		//Get positions
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
		$positions = $positionsDb->getPositions($invoiceid);

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
        if($invoice['taxfree']) {
            $taxes[] = array('value' => 0, 'title' => 0);
        } else {
		    foreach($positions as $position) {
                if(!isset($taxes[$position->taxrate]) && array_search($position->taxrate, $taxrates)) {
                    $taxes[$position->taxrate] = array();
                    $taxes[$position->taxrate]['value'] = $position->taxrate;
                    $taxes[$position->taxrate]['title'] = Zend_Locale_Format::toNumber($position->taxrate,array('precision' => 1,'locale' => $locale)).' %';
                }
            }
        }
		foreach($positions as $position) {
            if(array_search($position->taxrate, $taxrates)) $taxes[$position->taxrate]['value'] += ($position->price*$position->quantity*$position->taxrate/100);

			$position->total =  $this->_currency->toCurrency($position->price*$position->quantity);
			$position->price =  $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));

			$form = new Sales_Form_Invoicepos();
			$forms[$position->id] = $form->populate($position->toArray());
			$forms[$position->id]->uom->addMultiOptions($uoms);
			$forms[$position->id]->ordering->addMultiOptions($orderings);
			$forms[$position->id]->taxrate->setValue(array_search($position->taxrate, $taxrates));
		    foreach($taxrates as $id => $value)
			    $forms[$position->id]->taxrate->addMultiOption($id, Zend_Locale_Format::toNumber($value,array('precision' => 1,'locale' => $locale)).' %');
		}

		$invoice['subtotal'] = $this->_currency->toCurrency($invoice['subtotal']);
		$invoice['total'] = $this->_currency->toCurrency($invoice['total']);
        foreach($taxes as $rate => $data) {
		    $taxes[$rate]['value'] = $this->_currency->toCurrency($data['value']);
        }
		$invoice['taxes'] = $taxes;

		$this->view->forms = $forms;
		$this->view->invoice = $invoice;
		$this->view->toolbar = new Sales_Form_ToolbarPositions();
	}

	public function applyAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$itemid = $this->_getParam('itemid', 0);
		$invoiceid = $this->_getParam('invoiceid', 0);

		$form = new Items_Form_Item();

		if($request->isPost()) {
		    header('Content-type: application/json');
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			$data = array();
			if($itemid && $invoiceid) {
				$item = new Items_Model_DbTable_Item();
				$item = $item->getItem($itemid);
				$data['invoiceid'] = $invoiceid;
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
				$data['ordering'] = $this->getLatestOrdering($invoiceid) + 1;
				$position = new Sales_Model_DbTable_Invoicepos();
				$position->addPosition($data);

				//Calculate
				$calculations = $this->_helper->Calculate($invoiceid, $this->_currency, $this->_date, $this->_user['id']);
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

		$invoiceid = $this->_getParam('invoiceid', 0);

		if($this->getRequest()->isPost()) {
			$data = array();
			$data['invoiceid'] = $invoiceid;
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
			$data['ordering'] = $this->getLatestOrdering($invoiceid) + 1;
			$position = new Sales_Model_DbTable_Invoicepos();
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
		$invoiceid = $this->_getParam('invoiceid', 0);

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		$form = new Sales_Form_Invoicepos();
		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->getOrdering($invoiceid));
		$form->taxrate->addMultiOptions($taxrates);

		if($request->isPost()) {
		    header('Content-type: application/json');
			$data = $request->getPost();
			$element = key($data);
			if(isset($form->$element) && $form->isValidPartial($data)) {
				if(($element == 'taxrate') && ($data[$element] != 0))
					$data['taxrate'] = $taxrates[$data['taxrate']];
				if(($element == 'price') || ($element == 'quantity'))
					$data[$element] = Zend_Locale_Format::getNumber($data[$element],array('precision' => 2,'locale' => $locale));

				$position = new Sales_Model_DbTable_Invoicepos();
				$position->updatePosition($id, $data);

				if(($element == 'price') || ($element == 'quantity') || ($element == 'taxrate')) {
					$calculations = $this->_helper->Calculate($invoiceid, $this->_currency, $this->_date, $this->_user['id']);
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
			$position = new Sales_Model_DbTable_Invoicepos();
			$data = $position->getPosition($id);
			$orderings = $this->getOrdering($data['invoiceid']);
			foreach($orderings as $ordering => $positionId) {
				if($ordering > $data['ordering']) $position->updatePosition($positionId, array('ordering' => ($ordering+1)));
			}
			$data['ordering'] += 1;
			$data['modified'] = '0000-00-00';
			$data['modifiedby'] = 0;
			unset($data['id']);
			$position->addPosition($data);

			//Calculate
			$calculations = $this->_helper->Calculate($data['invoiceid'], $this->_currency, $this->_date, $this->_user['id']);
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
			$orderings = $this->getOrdering($data['invoiceid']);
			$currentOrdering = array_search($data['id'], $orderings); 
			$position = new Sales_Model_DbTable_Invoicepos();
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
			$this->setOrdering($data['invoiceid']);
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
				$positionDb = new Sales_Model_DbTable_Invoicepos();
				$positionDb->deletePositions($data['id']);

				//Reorder and calculate
				$this->setOrdering($data['invoiceid']);
				$calculations = $this->_helper->Calculate($data['invoiceid'], $this->_currency, $this->_date, $this->_user['id']);
	            echo Zend_Json::encode($calculations['locale']);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$locale = Zend_Registry::get('Zend_Locale');

		$form = new Sales_Form_Invoicepos();

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

	protected function setOrdering($invoiceid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
		$positions = $positionsDb->getPositions($invoiceid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new Sales_Model_DbTable_Invoicepos();
				$positionsDb->sortPosition($position->id, $i);
			}
			++$i;
		}
	}

	protected function getOrdering($invoiceid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Invoicepos();
		$positions = $positionsDb->getPositions($invoiceid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	protected function getLatestOrdering($invoiceid)
	{
		$ordering = $this->getOrdering($invoiceid);
		end($ordering);
		return key($ordering);
	}
}
