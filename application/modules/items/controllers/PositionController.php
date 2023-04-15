<?php

class Items_PositionController extends Zend_Controller_Action
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
		$parentClass = 'Items_Model_DbTable_'.ucfirst($params['parent']);
		$positionClass = 'Items_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
		$positionSetClass = 'Items_Model_DbTable_'.ucfirst($params['parent'].$params['type'].'set');

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

		$sets = array();
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
		foreach($positionSets as $positionSet) {
			$forms = array();
			foreach($positions as $position) {
				if($positionSet->id == $position->{$params['type'].'setid'}) {
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

					$formClass = 'Items_Form_'.ucfirst($params['parent'].$params['type']);
					$form = new $formClass();
					$forms[$position->id] = $form->populate($position->toArray());
					$forms[$position->id]->uom->addMultiOptions($uoms);
					if($position->uom) {
						$uom = array_search($position->uom, $uoms);
						if($uom) $forms[$position->id]->uom->setValue($uom);
					}
					$forms[$position->id]->priceruleaction->addMultiOptions($priceruleactions);
					$forms[$position->id]->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['type'], $params['parentid'], $positionSet->id));
					$forms[$position->id]->taxrate->setValue(array_search($position->taxrate, $taxrates));
					foreach($taxrates as $id => $value)
						$forms[$position->id]->taxrate->addMultiOption($id, Zend_Locale_Format::toNumber($value,array('precision' => 1,'locale' => $locale)).' %');
				}
			}
			$sets[$positionSet->id]['forms'] = $forms;
			$sets[$positionSet->id]['title'] = $positionSet->title;
		}

		// Set grand total with currency symbol
		$currencyHelper->setCurrency($currency, $parent['currency'], 'USE_SYMBOL');
		if(isset($parent['subtotal'])) $parent['subtotal'] = $currency->toCurrency($parent['subtotal']);
		if(isset($parent['total'])) $parent['total'] = $currency->toCurrency($parent['total']);
		foreach($taxes as $rate => $data) {
			$taxes[$rate]['value'] = $currency->toCurrency($data['value']);
		}
		$parent['taxes'] = $taxes;

		$this->view->sets = $sets;
		$this->view->parent = $parent;
		$this->view->toolbar = new Items_Form_ToolbarPositions();
		$this->view->toolbarPositions = new Items_Form_ToolbarPositions();
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
				$parentClass = 'Items_Model_DbTable_'.ucfirst($params['parent']);
				$positionClass = 'Items_Model_DbTable_'.ucfirst($params['parent'].$params['type']);

				//Get parent data
				$parentDb = new $parentClass();
				$parentMethod = 'get'.$params['parent'];
				$parent = $parentDb->$parentMethod($params['parentid']);

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
				$positionid = $positionsDb->addPosition($data);

				//Apply price rules
				$pricerules = $this->_helper->PriceRule->getPriceRules($item, $parent['contactid']);
				$this->_helper->PriceRule->applyPriceRules('sales', $params['parent'].$params['type'], $pricerules, $positionid);
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
		$parentClass = 'Items_Model_DbTable_'.ucfirst($params['parent']);
		$positionClass = 'Items_Model_DbTable_'.ucfirst($params['parent'].$params['type']);

		//Get parent data
		$parentDb = new $parentClass();
		$parentMethod = 'get'.$params['parent'];
		$parent = $parentDb->$parentMethod($params['parentid']);

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
		$formClass = 'Items_Form_'.ucfirst($params['parent'].$params['type']);
		$modelClass = 'Items_Model_DbTable_'.ucfirst($params['parent'].$params['type']);

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
			$positionClass = 'Items_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
			$positionDb = new $positionClass();
			$data = $positionDb->getPosition($params['id']);
			$this->_helper->Ordering->pushOrdering($data['ordering'], $params['parent'], $params['type'], $data['parentid'], $data[$params['type'].'setid']);
			$data['ordering'] += 1;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			unset($data['id']);
			$positionDb->addPosition($data);
			echo Zend_Json::encode($data);
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
			$this->_helper->Ordering->sortOrdering($data['id'], $params['parent'], $params['type'], $params['parentid'], $params['setid'], $data['ordering']);
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
				$positionClass = 'Items_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
				$positionDb = new $positionClass();
				$positionDb->deletePositions($data['id']);

				//Reorder
				$this->_helper->Ordering->setOrdering($params['parent'], $params['type'], $params['parentid'], $params['setid']);
				echo Zend_Json::encode($data);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$params = $this->_getAllParams();
		$locale = Zend_Registry::get('Zend_Locale');

		$formClass = 'Items_Form_'.ucfirst($params['parent'].$params['type']);
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
