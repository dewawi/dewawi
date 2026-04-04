<?php

class Purchases_PositionController extends DEEC_Controller_Action
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

		//Calculate
		$this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);

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
		$pricerules = array();
		$pricerulemaster = array();
		$optionsDb = new Items_Model_DbTable_Itemopt();
		foreach($positions as $position) {
			//Get price rules and properties
			if(!$position->masterid) {
				$pricerules[$position->id] = $this->_helper->PriceRule->getPriceRulePositions('purchases', $params['parent'].$params['type'], $position->id);
				$pricerulemaster[$position->id] = $position->pricerulemaster;
			}
		}
		foreach($positions as $position) {
			//Use price rules
			if($position->masterid && $pricerulemaster[$position->masterid] && isset($pricerules[$position->masterid])) {
				$price = $this->_helper->PriceRule->usePriceRules($pricerules[$position->masterid], $position->price);
			} elseif(!$position->masterid && isset($pricerules[$position->id])) {
				$price = $this->_helper->PriceRule->usePriceRules($pricerules[$position->id], $position->price);
			} else {
				$price = $position->price;
			}

			if(isset($parent['taxfree']) && !$parent['taxfree'] && array_search($position->taxrate, $taxrates))
				$taxes[$position->taxrate]['value'] += ($price*$position->quantity*$position->taxrate/100);

			// Set position total with currency symbol
			$currencyHelper->setCurrency($currency, $position->currency, 'USE_SYMBOL');
			$position->total = $currency->toCurrency($price*$position->quantity);

			// Set editable values without currency symbol
			$currencyHelper->setCurrency($currency, $position->currency, 'NO_SYMBOL');
			$position->price = $currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));

			$formClass = 'Purchases_Form_'.ucfirst($params['parent'].$params['type']);
			$form = $this->buildPositionForm(
				$formClass,
				$position,
				$uoms,
				$taxrates,
				$this->_helper->Ordering->getOrdering(
					$params['parent'],
					$params['type'],
					$params['parentid'],
					$position->{$params['type'] . 'setid'}
				),
				$locale
			);

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

		foreach($positions as $position) {
			// Set price rules without currency symbol
			if(isset($pricerules[$position->id])) {
				foreach($pricerules[$position->id] as $key => $value) {
					$pricerules[$position->id][$key]['amount'] = $value['amount'] = $currency->toCurrency($value['amount']);
				}
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
		$this->view->pricerules = $pricerules;
		$this->view->toolbar = new Purchases_Form_ToolbarPositions();
		$this->view->toolbarPositions = new Purchases_Form_ToolbarPositions();
	}

	public function applyAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$request = $this->getRequest();
		$params = $this->_getAllParams();

		if ($request->isPost()) {
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();

			if ($params['itemid'] && $params['parentid']) {
				$itemDb = new Items_Model_DbTable_Item();
				$item = $itemDb->getItem($params['itemid']);

				$parentClass = 'Purchases_Model_DbTable_' . ucfirst($params['parent']);
				$positionClass = 'Purchases_Model_DbTable_' . ucfirst($params['parent'] . $params['type']);

				$parentDb = new $parentClass();
				$parentMethod = 'get' . $params['parent'];
				$parent = $parentDb->$parentMethod($params['parentid']);

				if ($parent['currency'] == $item['currency']) {
					$price = $item['price'];
				} else {
					$price = $this->_helper->Currency($item['currency'], $parent['currency'], $item['price'], $this->_helper);
				}

				$taxrate = 0;
				if ($item['taxid']) {
					$taxrateDb = new Application_Model_DbTable_Taxrate();
					$taxrateRow = $taxrateDb->getTaxrate($item['taxid']);
					$taxrate = $taxrateRow['rate'];
				}

				$uom = '';
				if ($item['uomid']) {
					$uomDb = new Application_Model_DbTable_Uom();
					$uomRow = $uomDb->getUom($item['uomid']);
					$uom = $uomRow['title'];
				}

				$data = [
					'parentid' => $params['parentid'],
					$params['type'] . 'setid' => $params['setid'],
					'itemid' => $params['itemid'],
					'sku' => $item['sku'],
					'title' => $item['title'],
					'description' => $item['description'],
					'price' => $price,
					'taxrate' => $taxrate,
					'quantity' => 1,
					'total' => $price,
					'currency' => $parent['currency'],
					'uom' => $uom,
					'ordering' => $this->_helper->Ordering->getLatestOrdering(
						$params['parent'],
						$params['type'],
						$params['parentid'],
						$params['setid']
					) + 1,
				];

				$positionDb = new $positionClass();
				$positionid = $positionDb->addPosition($data);

				$pricerules = $this->_helper->PriceRule->getPriceRules($item, $parent['contactid']);
				$this->_helper->PriceRule->applyPriceRules('purchases', $params['parent'] . $params['type'], $pricerules, $positionid);

				$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
				return $this->_helper->json($calculations['locale']);
			}

			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}
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

		$formClass = 'Purchases_Form_' . ucfirst($params['parent'] . $params['type']);
		$modelClass = 'Purchases_Model_DbTable_' . ucfirst($params['parent'] . $params['type']);

		/** @var DEEC_Form $form */
		$form = $this->buildPositionFormForRequest($formClass, $params, $locale);

		// Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		// Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		if ($request->isPost()) {
			$post = (array)$request->getPost();

			if (!$form->isValidPartial($post)) {
				return $this->_helper->json([
					'ok' => false,
					'errors' => $this->toErrorMessages($form->getErrors(), $form),
				]);
			}

			$values = $form->getFilteredValuesPartial($post);
			$element = key($post);

			if (($element === 'taxrate') && isset($values['taxrate']) && $values['taxrate'] != 0) {
				$values['taxrate'] = $taxrates[$values['taxrate']] ?? 0;
			}

			if (($element === 'price') || ($element === 'quantity') || ($element === 'priceruleamount')) {
				if (isset($values[$element])) {
					$values[$element] = Zend_Locale_Format::getNumber($post[$element], [
						'precision' => 2,
						'locale' => $locale,
					]);
				}
			}

			if (($element === 'uom') && isset($values['uom']) && $values['uom'] != 0) {
				$values['uom'] = $uoms[$values['uom']] ?? '';
			}

			$positionDb = new $modelClass();

			try {
				$positionDb->updatePosition($params['id'], $values);
			} catch (Exception $e) {
				return $this->_helper->json([
					'ok' => false,
					'message' => 'save_failed',
				]);
			}

			if (($element === 'price') || ($element === 'quantity') || ($element === 'taxrate') || ($element === 'pricerulemaster')) {
				$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
				return $this->_helper->json($calculations['locale']);
			}

			return $this->_helper->json([
				'ok' => true,
				'id' => (int)$params['id'],
				'values' => $values,
			]);
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

			//Copy price rules
			$priceRuleHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');
			$pricerules = $priceRuleHelper->getPriceRulePositions('purchases', $params['parent'].$params['type'], $params['id']);
			foreach($pricerules as $pricerule) {
				$dataPricerule = $pricerule;
				$dataPricerule['parentid'] = $id;
				$dataPricerule['created'] = $this->_date;
				$dataPricerule['modified'] = NULL;
				$dataPricerule['modifiedby'] = 0;
				unset($dataPricerule['id']);
				$priceRuleDb = new Items_Model_DbTable_Pricerulepos();
				$priceRuleDb->addPosition($dataPricerule);
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

	protected function buildPositionForm($formClass, $position, array $uoms, array $taxrates, array $orderingOptions, $locale)
	{
		$form = new $formClass();
		$form->setValues($position->toArray());

		$form->addOptions('uom', $uoms, 'replace');

		if ($position->uom) {
			$uomId = array_search($position->uom, $uoms, true);
			if ($uomId !== false) {
				$form->setValue('uom', $uomId);
			}
		}

		$form->addOptions('ordering', $orderingOptions, 'replace');

		$taxrateOptions = [];
		foreach ($taxrates as $taxrateId => $value) {
			$taxrateOptions[$taxrateId] = Zend_Locale_Format::toNumber($value, [
				'precision' => 1,
				'locale' => $locale
			]) . ' %';
		}

		$form->addOptions('taxrate', $taxrateOptions, 'replace');

		$taxrateId = array_search($position->taxrate, $taxrates, true);
		if ($taxrateId !== false) {
			$form->setValue('taxrate', $taxrateId);
		}

		return $form;
	}

	protected function buildPositionFormForRequest(string $formClass, array $params, $locale): DEEC_Form
	{
		$form = new $formClass();

		// Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		// Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		// UOM options
		$form->addOptions('uom', $uoms, 'replace');

		// Ordering options
		$form->addOptions(
			'ordering',
			$this->_helper->Ordering->getOrdering(
				$params['parent'],
				$params['type'],
				$params['parentid'],
				0
			),
			'replace'
		);

		// Tax rate options
		$taxrateOptions = [];
		foreach ($taxrates as $taxrateId => $value) {
			$taxrateOptions[$taxrateId] = Zend_Locale_Format::toNumber($value, [
				'precision' => 1,
				'locale' => $locale,
			]) . ' %';
		}

		$form->addOptions('taxrate', $taxrateOptions, 'replace');

		return $form;
	}
}
