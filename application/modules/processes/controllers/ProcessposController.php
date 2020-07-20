<?php

class Processes_ProcessposController extends Zend_Controller_Action
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

		$processid = $this->_getParam('processid', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		//Get process
		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($processid);

		//Get positions
		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($processid);

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethods = $shippingmethodDb->getShippingmethods();

        //Get currency
		$currency = $this->_helper->Currency->getCurrency($process['currency']);

		$forms = array();
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$position->ordering] = $position->ordering;
		}
		foreach($positions as $position) {
			$position->price =  $currency->toCurrency($position->price);
			$position->supplierinvoicetotal =  $currency->toCurrency($position->supplierinvoicetotal);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));

            //Convert dates to the display format
            $deliverydate = new Zend_Date($position->deliverydate);
            if($position->deliverydate == '0000-00-00') $position->deliverydate = '';
            else $position->deliverydate = $deliverydate->get('dd.MM.yyyy');
            $deliveryorderdate = new Zend_Date($position->deliveryorderdate);
            if($position->deliveryorderdate == '0000-00-00') $position->deliveryorderdate = '';
            else $position->deliveryorderdate = $deliveryorderdate->get('dd.MM.yyyy');
            $purchaseorderdate = new Zend_Date($position->purchaseorderdate);
            if($position->purchaseorderdate == '0000-00-00') $position->purchaseorderdate = '';
            else $position->purchaseorderdate = $purchaseorderdate->get('dd.MM.yyyy');
            $suppliersalesorderdate = new Zend_Date($position->suppliersalesorderdate);
            if($position->suppliersalesorderdate == '0000-00-00') $position->suppliersalesorderdate = '';
            else $position->suppliersalesorderdate = $suppliersalesorderdate->get('dd.MM.yyyy');
            $supplierpaymentdate = new Zend_Date($position->supplierpaymentdate);
            if($position->supplierpaymentdate == '0000-00-00') $position->supplierpaymentdate = '';
            else $position->supplierpaymentdate = $supplierpaymentdate->get('dd.MM.yyyy');

			$form = new Processes_Form_Processpos();
			$forms[$position->id] = $form->populate($position->toArray());
			$forms[$position->id]->uom->addMultiOptions($uoms);
            if($position->uom) {
                $uom = array_search($position->uom, $uoms);
                if($uom) $forms[$position->id]->uom->setValue($uom);
            }
			$forms[$position->id]->ordering->addMultiOptions($orderings);
			$forms[$position->id]->shippingmethod->addMultiOptions($shippingmethods);
			foreach($forms[$position->id] as $element) {
				$id = $element->getId();
				$forms[$position->id]->$id->setAttrib('id', $id.$position->id);
				if(!$process['editpositionsseparately']) $element->setAttrib('disabled', 'disabled');
			}
		}
		$this->view->forms = $forms;
		$this->view->toolbar = new Processes_Form_ToolbarPositions();
	}

	public function applyAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$itemid = $this->_getParam('itemid', 0);
		$processid = $this->_getParam('processid', 0);

		$form = new Items_Form_Item();

		if($request->isPost()) {
		    header('Content-type: application/json');
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			$data = array();
			if($itemid && $processid) {
				$item = new Items_Model_DbTable_Item();
				$item = $item->getItem($itemid);
				$data['processid'] = $processid;
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
                if($item['uomid']) {
		            $uomDb = new Application_Model_DbTable_Uom();
				    $uom = $uomDb->getUom($item['uomid']);
				    $data['uom'] = $uom['title'];
                } else {
                    $data['uom'] = '';
                }
				$data['ordering'] = $this->getLatestOrdering($processid) + 1;
				$position = new Processes_Model_DbTable_Processpos();
				$position->addPosition($data);

				//Calculate
				//$calculations = $this->_helper->Calculate($processid, $this->_date, $this->_user['id']);
			    //echo Zend_Json::encode($calculations['locale']);
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

		$processid = (int)$this->_getParam('processid', 0);

		if($this->getRequest()->isPost()) {
			$data = array();
			$data['processid'] = $processid;
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
			$data['deliverystatus'] = 'deliveryIsWaiting';
			$data['supplierorderstatus'] = 'supplierNotOrdered';
			$data['ordering'] = $this->getLatestOrdering($processid) + 1;
			$position = new Processes_Model_DbTable_Processpos();
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
		$processid = $this->_getParam('processid', 0);

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethods = $shippingmethodDb->getShippingmethods();

		$form = new Processes_Form_Processpos();
		$form->uom->addMultiOptions($uoms);
		$form->shippingmethod->addMultiOptions($shippingmethods);
		$form->ordering->addMultiOptions($this->getOrdering($processid));

		if($request->isPost()) {
		    header('Content-type: application/json');
			$data = $request->getPost();
			$element = key($data);
			if(isset($form->$element) && $form->isValidPartial($data)) {
				if(($element == 'price') || ($element == 'quantity') || ($element == 'supplierinvoicetotal'))
					$data[$element] = Zend_Locale_Format::getNumber($data[$element],array('precision' => 2,'locale' => $locale));
				if(($element == 'uom') && ($data[$element] != 0))
					$data['uom'] = $uoms[$data[$element]];

				if(isset($data['deliverydate'])) {
                    if(Zend_Date::isDate($data['deliverydate'])) {
                        $deliverydate = new Zend_Date($data['deliverydate'], Zend_Date::DATES, 'de');
                        $data['deliverydate'] = $deliverydate->get('yyyy-MM-dd');
				    }
				}
				if(isset($data['deliveryorderdate'])) {
                    if(Zend_Date::isDate($data['deliveryorderdate'])) {
                        $deliveryorderdate = new Zend_Date($data['deliveryorderdate'], Zend_Date::DATES, 'de');
                        $data['deliveryorderdate'] = $deliveryorderdate->get('yyyy-MM-dd');
				    }
				}
				if(isset($data['purchaseorderdate'])) {
                    if(Zend_Date::isDate($data['purchaseorderdate'])) {
                        $purchaseorderdate = new Zend_Date($data['purchaseorderdate'], Zend_Date::DATES, 'de');
                        $data['purchaseorderdate'] = $purchaseorderdate->get('yyyy-MM-dd');
				    }
				}
				if(isset($data['suppliersalesorderdate'])) {
                    if(Zend_Date::isDate($data['suppliersalesorderdate'])) {
                        $suppliersalesorderdate = new Zend_Date($data['suppliersalesorderdate'], Zend_Date::DATES, 'de');
                        $data['suppliersalesorderdate'] = $suppliersalesorderdate->get('yyyy-MM-dd');
				    }
				}
				if(isset($data['supplierpaymentdate'])) {
                    if(Zend_Date::isDate($data['supplierpaymentdate'])) {
                        $supplierpaymentdate = new Zend_Date($data['supplierpaymentdate'], Zend_Date::DATES, 'de');
                        $data['supplierpaymentdate'] = $supplierpaymentdate->get('yyyy-MM-dd');
				    }
				}

				$position = new Processes_Model_DbTable_Processpos();
				$position->updatePosition($id, $data);
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
			$position = new Processes_Model_DbTable_Processpos();
			$data = $position->getPosition($id);
			$orderings = $this->getOrdering($data['processid']);
			foreach($orderings as $ordering => $positionId) {
				if($ordering > $data['ordering']) $position->updatePosition($positionId, array('ordering' => ($ordering+1)));
			}
			$data['ordering'] += 1;
			$data['modified'] = '0000-00-00';
			$data['modifiedby'] = 0;
			unset($data['id']);
			$position->addPosition($data);

			//Calculate
			//$calculations = $this->_helper->Calculate($data['processid'], $this->_date, $this->_user['id']);
	        //echo Zend_Json::encode($calculations['locale']);
	        echo Zend_Json::encode(true);
		}
	}

	public function sortAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$data = $request->getPost();
			$orderings = $this->getOrdering($data['processid']);
			$currentOrdering = array_search($data['id'], $orderings); 
			$position = new Processes_Model_DbTable_Processpos();
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
			$this->setOrdering($data['processid']);
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
				$positionDb = new Processes_Model_DbTable_Processpos();
				$positionDb->deletePositions($data['id']);

				//Reorder and calculate
				$this->setOrdering($data['processid']);
				//$calculations = $this->_helper->Calculate($data['processid'], $this->_date, $this->_user['id']);
	            //echo Zend_Json::encode($calculations['locale']);
	            echo Zend_Json::encode(true);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Processes_Form_Processpos();

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethods = $shippingmethodDb->getShippingmethods();

		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->getOrdering($processid));
		$form->shippingmethod->addMultiOptions($shippingmethods);

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function setOrdering($processid)
	{
		$i = 1;
		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($processid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new Processes_Model_DbTable_Processpos();
				$positionsDb->sortPosition($position->id, $i);
			}
			++$i;
		}
	}

	protected function getOrdering($processid)
	{
		$i = 1;
		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->getPositions($processid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	protected function getLatestOrdering($processid)
	{
		$ordering = $this->getOrdering($processid);
		end($ordering);
		return key($ordering);
	}
}
