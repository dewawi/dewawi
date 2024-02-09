<?php

class Campaigns_PositionController extends Zend_Controller_Action
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

		$parentid = $this->_getParam('parentid', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		//Get campaign
		$campaignDb = new Campaigns_Model_DbTable_Campaign();
		$campaign = $campaignDb->getCampaign($parentid);

		//Get positions
		$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
		$positions = $positionsDb->getPositions($parentid);

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethods = $shippingmethodDb->getShippingmethods();

		//Get currency
		$currencyHelper = $this->_helper->Currency;
		$currency = $currencyHelper->getCurrency();

		$forms = array();
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$position->ordering] = $position->ordering;
		}
		foreach($positions as $position) {
			// Set editable values without currency symbol
			$currencyHelper->setCurrency($currency, $position->currency, 'NO_SYMBOL');
			$position->price = $currency->toCurrency($position->price);
			$position->supplierinvoicetotal = $currency->toCurrency($position->supplierinvoicetotal);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));

			//Convert dates to the display format
			$deliverydate = new Zend_Date($position->deliverydate);
			if($position->deliverydate) $position->deliverydate = $deliverydate->get('dd.MM.yyyy');
			$deliveryorderdate = new Zend_Date($position->deliveryorderdate);
			if($position->deliveryorderdate) $position->deliveryorderdate = $deliveryorderdate->get('dd.MM.yyyy');
			$purchaseorderdate = new Zend_Date($position->purchaseorderdate);
			if($position->purchaseorderdate) $position->purchaseorderdate = $purchaseorderdate->get('dd.MM.yyyy');
			$suppliersalesorderdate = new Zend_Date($position->suppliersalesorderdate);
			if($position->suppliersalesorderdate) $position->suppliersalesorderdate = $suppliersalesorderdate->get('dd.MM.yyyy');
			$supplierinvoicedate = new Zend_Date($position->supplierinvoicedate);
			if($position->supplierinvoicedate) $position->supplierinvoicedate = $supplierinvoicedate->get('dd.MM.yyyy');
			$supplierpaymentdate = new Zend_Date($position->supplierpaymentdate);
			if($position->supplierpaymentdate) $position->supplierpaymentdate = $supplierpaymentdate->get('dd.MM.yyyy');

			$form = new Campaigns_Form_Campaignpos();
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
				if(!$campaign['editpositionsseparately']) $element->setAttrib('disabled', 'disabled');
			}
		}
		$this->view->forms = $forms;
		$this->view->toolbar = new Campaigns_Form_ToolbarPositions();
	}

	public function applyAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$request = $this->getRequest();
		$params = $this->_getAllParams();
		$locale = Zend_Registry::get('Zend_Locale');

		$form = new Items_Form_Item();

		if($request->isPost()) {
			header('Content-type: application/json');
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			$data = array();
			if($params['itemid'] && $params['parentid']) {
				$item = new Items_Model_DbTable_Item();
				$item = $item->getItem($params['itemid']);
				$data['parentid'] = $params['parentid'];
				$data['itemid'] = $params['itemid'];
				$data['sku'] = $item['sku'];
				$data['title'] = $item['title'];
				//$data['image'] = $item['image'];
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
				$data['ordering'] = $this->_helper->Ordering->getLatestOrdering($params['parent'], $params['type'], $params['parentid'], $params['setid']) + 1;
				$position = new Campaigns_Model_DbTable_Campaignpos();
				$position->addPosition($data);

				//Calculate
				//$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
				//echo Zend_Json::encode($calculations['locale']);
			} else {
				$form->populate($request->getPost());
			}
		} else {
			if($params['itemid'] > 0) {
				$item = new Items_Model_DbTable_Item();
				$form->populate($item->getItem($params['itemid']));
			}
		}
		$this->view->form = $form;
	}

	public function addAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$params = $this->_getAllParams();

		//Define belonging classes
		$parentClass = 'Campaigns_Model_DbTable_'.ucfirst($params['parent']);
		$positionClass = 'Campaigns_Model_DbTable_'.ucfirst($params['parent'].$params['type']);

		//Get parent data
		$parentDb = new $parentClass();
		$parentMethod = 'get'.$params['parent'];
		$parent = $parentDb->$parentMethod($params['parentid']);

		//Get option data
		$params['optionid'] = isset($params['optionid']) ? $params['optionid'] : 0;
		if($params['optionid']) {
			$optionDb = new Items_Model_DbTable_Itemopt();
			$option = $optionDb->getPosition($params['optionid']);
		}

		//Get primary tax rate
		$taxrates = new Application_Model_DbTable_Taxrate();
		$taxrate = $taxrates->getPrimaryTaxrate();

		if($this->getRequest()->isPost()) {
			$data = array();
			$data['parentid'] = $params['parentid'];
			$data[$params['type'].'setid'] = $params['setid'];
			$data['itemid'] = 0;
			$data['sku'] = '';
			$data['title'] = '';
			$data['image'] = '';
			$data['description'] = '';
			$data['price'] = 0;
			$data['taxrate'] = $taxrate['rate'];
			$data['quantity'] = 1;
			$data['total'] = 0;
			$data['currency'] = $parent['currency'];
			$data['uom'] = '';
			$data['deliverystatus'] = 'deliveryIsWaiting';
			$data['supplierorderstatus'] = 'supplierNotOrdered';
			$data['ordering'] = $this->_helper->Ordering->getLatestOrdering($params['parent'], $params['type'], $params['parentid'], $params['setid']) + 1;
			if(isset($option)) {
				$data['masterid'] = $params['masterid'];
				$data['sku'] = $option['sku'];
				$data['itemid'] = $option['itemid'];
				$data['title'] = $option['title'];
				$data['description'] = $option['description'];
				$data['price'] = $option['price'];
				$data['ordering'] = $this->_helper->Ordering->getLatestOrdering($params['parent'], $params['type'], $params['parentid'], $params['setid'], $params['masterid']) + 1;
			}
			$positionDb = new $positionClass();
			$positionDb->addPosition($data);
		}
	}

	public function editAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		$params = $this->_getAllParams();
		$locale = Zend_Registry::get('Zend_Locale');

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethods = $shippingmethodDb->getShippingmethods();

		//Define belonging classes
		$formClass = 'Campaigns_Form_'.ucfirst($params['parent'].$params['type']);
		$modelClass = 'Campaigns_Model_DbTable_'.ucfirst($params['parent'].$params['type']);

		$form = new $formClass();
		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['type'], $params['parentid'], 0));
		$form->shippingmethod->addMultiOptions($shippingmethods);

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
					} else {
						$data['deliverydate'] = NULL;
					}
				}
				if(isset($data['deliveryorderdate'])) {
					if(Zend_Date::isDate($data['deliveryorderdate'])) {
						$deliveryorderdate = new Zend_Date($data['deliveryorderdate'], Zend_Date::DATES, 'de');
						$data['deliveryorderdate'] = $deliveryorderdate->get('yyyy-MM-dd');
					} else {
						$data['deliveryorderdate'] = NULL;
					}
				}
				if(isset($data['purchaseorderdate'])) {
					if(Zend_Date::isDate($data['purchaseorderdate'])) {
						$purchaseorderdate = new Zend_Date($data['purchaseorderdate'], Zend_Date::DATES, 'de');
						$data['purchaseorderdate'] = $purchaseorderdate->get('yyyy-MM-dd');
					} else {
						$data['purchaseorderdate'] = NULL;
					}
				}
				if(isset($data['suppliersalesorderdate'])) {
					if(Zend_Date::isDate($data['suppliersalesorderdate'])) {
						$suppliersalesorderdate = new Zend_Date($data['suppliersalesorderdate'], Zend_Date::DATES, 'de');
						$data['suppliersalesorderdate'] = $suppliersalesorderdate->get('yyyy-MM-dd');
					} else {
						$data['suppliersalesorderdate'] = NULL;
					}
				}
				if(isset($data['supplierinvoicedate'])) {
					if(Zend_Date::isDate($data['supplierinvoicedate'])) {
						$supplierinvoicedate = new Zend_Date($data['supplierinvoicedate'], Zend_Date::DATES, 'de');
						$data['supplierinvoicedate'] = $supplierinvoicedate->get('yyyy-MM-dd');
					} else {
						$data['supplierinvoicedate'] = NULL;
					}
				}
				if(isset($data['supplierpaymentdate'])) {
					if(Zend_Date::isDate($data['supplierpaymentdate'])) {
						$supplierpaymentdate = new Zend_Date($data['supplierpaymentdate'], Zend_Date::DATES, 'de');
						$data['supplierpaymentdate'] = $supplierpaymentdate->get('yyyy-MM-dd');
					} else {
						$data['supplierpaymentdate'] = NULL;
					}
				}

				$position = new $modelClass();
				$position->updatePosition($params['id'], $data);
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
		$params = $this->_getAllParams();

		if($request->isPost()) {
			header('Content-type: application/json');
			$positionClass = 'Campaigns_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
			$positionDb = new $positionClass();
			$data = $positionDb->getPosition($params['id']);
			$this->_helper->Ordering->pushOrdering($data['ordering'], $params['parent'], $params['type'], $data['parentid'], $data[$params['type'].'setid'], $data['masterid']);

			//Get child positions
			$positions = $positionDb->getPositions($data['parentid'], $data[$params['type'].'setid'], $data['id']);

			//Create new position
			$data['ordering'] += 1;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			unset($data['id']);
			$id = $positionDb->addPosition($data);

			//Create child positions
			if($positions && count($positions)) {
				foreach($positions as $position) {
					$data = $position->toArray();
					$data['masterid'] = $id;
					$data['modified'] = NULL;
					$data['modifiedby'] = 0;
					unset($data['id']);
					$positionDb->addPosition($data);
				}
			}

			//Calculate
			//$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
			echo Zend_Json::encode(true);
		}
	}

	public function sortAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		$params = $this->_getAllParams();

		if($request->isPost()) {
			$data = $request->getPost();
			if(!isset($params['masterid'])) $params['masterid'] = 0;
			$this->_helper->Ordering->sortOrdering($data['id'], $params['parent'], $params['type'], $params['parentid'], $params['setid'], $params['masterid'], $data['ordering']);
		}
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		$params = $this->_getAllParams();

		if($request->isPost()) {
			header('Content-type: application/json');
			$data = $request->getPost();
			if($data['delete'] == 'Yes') {
				if(!is_array($data['id'])) {
					$data['id'] = array($data['id']);
				}
				$positionClass = 'Campaigns_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
				$positionDb = new $positionClass();
				$positionDb->deletePositions($data['id']);

				//Delete child positions
				$positions = $positionDb->getPositions($params['parentid'], $params['setid'], $data['id']);
				if($positions && count($positions)) {
					foreach($positions as $position) {
						$positionDb->deletePositions($position->id);
					}
				}

				//Reorder and calculate
				if(!isset($params['masterid'])) $params['masterid'] = 0;
				$this->_helper->Ordering->setOrdering($params['parent'], $params['type'], $params['parentid'], $params['setid'], $params['masterid']);
				//$calculations = $this->_helper->Calculate($data['parentid'], $this->_date, $this->_user['id']);
				echo Zend_Json::encode(true);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$params = $this->_getAllParams();
		$locale = Zend_Registry::get('Zend_Locale');

		$formClass = 'Campaigns_Form_'.ucfirst($params['parent'].$params['type']);
		$form = new $formClass();

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get shipping methods
		$shippingmethodDb = new Application_Model_DbTable_Shippingmethod();
		$shippingmethods = $shippingmethodDb->getShippingmethods();

		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['type'], $params['parentid'], 0));
		$form->shippingmethod->addMultiOptions($shippingmethods);

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}
}
