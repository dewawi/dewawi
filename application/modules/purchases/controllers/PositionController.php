<?php

class Purchases_PositionController extends Zend_Controller_Action
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

		$params = $this->_getAllParams();
		$locale = Zend_Registry::get('Zend_Locale');

		//Define belonging classes
		$parentClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent']);
		$positionClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
		$positionSetClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].$params['type'].'set');

		//Get parent data
		$parentDb = new $parentClass();
		$parentMethod = 'get'.$params['parent'];
		$parent = $parentDb->$parentMethod($params['parentid']);

		//Get positions
		$positionsDb = new $positionClass();
		$positions = $positionsDb->getPositions($params['parentid']);

		//Get position sets
		$positionSetDb = new $positionSetClass();
		$positionSets = $positionSetDb->getPositionSets($params['parentid']);

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
		$currencyHelper = $this->_helper->Currency;
		$currency = $currencyHelper->getCurrency();

		$taxes = array();
		if(isset($parent['taxfree']) && $parent['taxfree']) {
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
		if(!count($positionSets)) {
			$positionSets = array();
			$object = new stdClass();
			$object->id = 0;
			$object->title = '';
			$object->ordering = 0;
			$positionSets[0] = $object;
		}
		$sets = array();
		foreach($positionSets as $positionSet) {
			$sets[$positionSet->id]['title'] = $positionSet->title;
		}
		$forms = array();
		$childs = array();
		$options = array();
		$optionsDb = new Items_Model_DbTable_Itemopt();
		foreach($positions as $position) {
			if(isset($parent['taxfree']) && !$parent['taxfree'] && array_search($position->taxrate, $taxrates))
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

			// Set position total with currency symbol
			$currencyHelper->setCurrency($currency, $position->currency, 'USE_SYMBOL');
			$position->total = $currency->toCurrency($price*$position->quantity);

			// Set editable values without currency symbol
			$currencyHelper->setCurrency($currency, $position->currency, 'NO_SYMBOL');
			$position->price = $currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));
			$position->priceruleamount = $currency->toCurrency($position->priceruleamount);

			$formClass = 'Purchases_Form_'.ucfirst($params['parent'].$params['type']);
			$form = new $formClass();
			$form->populate($position->toArray());
			$form->uom->addMultiOptions($uoms);
			if($position->uom) {
				$uom = array_search($position->uom, $uoms);
				if($uom) $form->uom->setValue($uom);
			}
			$form->priceruleaction->addMultiOptions($priceruleactions);
			$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['type'], $params['parentid'], $position->{$params['type'].'setid'}));
			$form->taxrate->setValue(array_search($position->taxrate, $taxrates));
			foreach($taxrates as $id => $value)
				$form->taxrate->addMultiOption($id, Zend_Locale_Format::toNumber($value,array('precision' => 1,'locale' => $locale)).' %');

			if($position->masterid) {
				$childs[$position->masterid][] = $form;
			} else {
				$forms[$position->{$params['type'].'setid'}][$position->id]['form'] = $form;
			}

			//Get options
			if($position->itemid) {
				$options[$position->id] = $optionsDb->getPositions($position->itemid);
			}
		}

		// Set grand total with currency symbol
		$currencyHelper->setCurrency($currency, $parent['currency'], 'USE_SYMBOL');
		if(isset($parent['subtotal'])) $parent['subtotal'] = $currency->toCurrency($parent['subtotal']);
		if(isset($parent['total'])) $parent['total'] = $currency->toCurrency($parent['total']);
		foreach($taxes as $rate => $data) {
			$taxes[$rate]['value'] = $currency->toCurrency($data['value']);
		}
		$parent['taxes'] = $taxes;
		$parent['type'] = $params['parent'];

		$this->view->sets = $sets;
		$this->view->forms = $forms;
		$this->view->childs = $childs;
		$this->view->parent = $parent;
		$this->view->options = $options;
		$this->view->toolbar = new Sales_Form_ToolbarPositions();
		$this->view->toolbarPositions = new Sales_Form_ToolbarPositions();
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
				//Get item
				$item = new Items_Model_DbTable_Item();
				$item = $item->getItem($params['itemid']);

				//Define belonging classes
				$parentClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent']);
				$positionClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].$params['type']);

				//Get parent data
				$parentDb = new $parentClass();
				$parentMethod = 'get'.$params['parent'];
				$parent = $parentDb->$parentMethod($params['parentid']);

				//Check price rules
				$data = $this->_helper->PriceRule($parent['contactid'], $item, $data, $this->_helper);

				//Check currency
				if($parent['currency'] == $item['currency']) {
					$data['price'] = $item['price'];
				} else {
					$data['price'] = $this->_helper->Currency($item['currency'], $parent['currency'], $item['price'], $this->_helper);
				}
				$data['currency'] = $parent['currency'];

				$data['parentid'] = $params['parentid'];
				$data[$params['type'].'setid'] = $params['setid'];
				$data['itemid'] = $params['itemid'];
				$data['sku'] = $item['sku'];
				$data['title'] = $item['title'];
				//$data['image'] = $item['image'];
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
				$data['ordering'] = $this->_helper->Ordering->getLatestOrdering($params['parent'], $params['type'], $params['parentid'], $params['setid']) + 1;

				$positionsDb = new $positionClass();
				$positionsDb->addPosition($data);

				//Calculate
				$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
				echo Zend_Json::encode($calculations['locale']);
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
		$parentClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent']);
		$positionClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].$params['type']);

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
			$data['ordering'] = $this->_helper->Ordering->getLatestOrdering($params['parent'], $params['type'], $params['parentid'], $params['setid']) + 1;
			if(isset($option)) {
				$data['masterid'] = $params['masterid'];
				$data['sku'] = $option['sku'];
				$data['itemid'] = $option['itemid'];
				$data['title'] = $option['title'];
				$data['description'] = $option['description'];
				$data['price'] = $option['price'];
				$data['uom'] = $option['uom'];
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

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		//Define belonging classes
		$formClass = 'Purchases_Form_'.ucfirst($params['parent'].$params['type']);
		$modelClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].$params['type']);

		$form = new $formClass();
		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['type'], $params['parentid'], 0));
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

				$position = new $modelClass();
				$position->updatePosition($params['id'], $data);

				if(($element == 'price') || ($element == 'quantity') || ($element == 'taxrate') || ($element == 'priceruleamount') || ($element == 'priceruleaction')) {
					$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
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
		$params = $this->_getAllParams();

		if($request->isPost()) {
			header('Content-type: application/json');
			$positionClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
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
			$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
			echo Zend_Json::encode($calculations['locale']);
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
				$positionClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
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
				$calculations = $this->_helper->Calculate($data['parentid'], $this->_date, $this->_user['id']);
				echo Zend_Json::encode($calculations['locale']);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$params = $this->_getAllParams();
		$locale = Zend_Registry::get('Zend_Locale');

		$formClass = 'Purchases_Form_'.ucfirst($params['parent'].$params['type']);
		$form = new $formClass();

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['type'], $params['parentid'], 0));
		$form->taxrate->addMultiOptions($taxrates);

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}
}
