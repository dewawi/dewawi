<?php

class Sales_QuoteposController extends Zend_Controller_Action
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

		$quoteid = $this->_getParam('quoteid', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		//Get quote
		$quoteDb = new Sales_Model_DbTable_Quote();
		$quote = $quoteDb->getQuote($quoteid);

		//Get positions
		$positions = $this->getPositions($quoteid);

		//Get units of measurements
		$uoms = $this->_helper->Uom->getUoms();
		$uoms = array_combine($uoms, $uoms);

		//Get tax rates
		$taxRates = $this->_helper->TaxRate->getTaxRates($locale);

		$forms = array();
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$position->ordering] = $position->ordering;
		}
		foreach($positions as $position) {
			$position->total =  $this->_currency->toCurrency($position->price*$position->quantity);
			$position->price =  $this->_currency->toCurrency($position->price);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));

			$form = new Sales_Form_Quotepos();
			$forms[$position->id] = $form->populate($position->toArray());
			$forms[$position->id]->uom->addMultiOptions($uoms);
			$forms[$position->id]->taxrate->addMultiOptions($taxRates);
			$forms[$position->id]->ordering->addMultiOptions($orderings);
		}

		$quote['subtotal'] = $this->_currency->toCurrency($quote['subtotal']);
		$quote['taxes'] = $this->_currency->toCurrency($quote['taxes']);
		$quote['total'] = $this->_currency->toCurrency($quote['total']);

		$this->view->forms = $forms;
		$this->view->quote = $quote;
		$this->view->toolbar = new Sales_Form_ToolbarPositions();
	}

	public function selectAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$positions = new Sales_Model_DbTable_Quotepos();
		$this->view->positions = $positions->fetchAll();
	}

	public function applyAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$request = $this->getRequest();
		$locale = Zend_Registry::get('Zend_Locale');
		$itemid = $this->_getParam('itemid', 0);
		$quoteid = $this->_getParam('quoteid', 0);

		$form = new Items_Form_Item();

		if($request->isPost()) {
		    header('Content-type: application/json');
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			$data = array();
			if($itemid && $quoteid) {
				$item = new Items_Model_DbTable_Item();
				$item = $item->getItem($itemid);
				$data['quoteid'] = $quoteid;
				$data['itemid'] = $itemid;
				$data['sku'] = $item['sku'];
				$data['title'] = $item['title'];
				$data['image'] = $item['image'];
				$data['description'] = $item['description'];
				$data['price'] = $item['price'];
				$data['taxrate'] = $item['taxid'] ? $this->_helper->TaxRate->getTaxRate($item['taxid']) : 0;
				$data['quantity'] = 1;
				$data['total'] = $data['price']*$data['quantity'];
				$data['uom'] = $item['uomid'] ? $this->_helper->Uom->getUom($item['uomid']) : '';
				$data['ordering'] = $this->getLatestOrdering($quoteid) + 1;
				$data['created'] = $this->_date;
				$data['createdby'] = $this->_user['id'];
				$data['clientid'] = $this->_user['clientid'];
				$position = new Sales_Model_DbTable_Quotepos();
				$position->addPosition($data);

				//Calculate
				$calculations = $this->_helper->Calculate($quoteid, $this->_currency, $this->_date, $this->_user['id']);
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

		$quoteid = $this->_getParam('quoteid', 0);

		if($this->getRequest()->isPost()) {
			$data = array();
			$data['quoteid'] = $quoteid;
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
			$data['ordering'] = $this->getLatestOrdering($quoteid) + 1;
			$data['created'] = $this->_date;
			$data['createdby'] = $this->_user['id'];
			$data['clientid'] = $this->_user['clientid'];
			$position = new Sales_Model_DbTable_Quotepos();
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
		$quoteid = $this->_getParam('quoteid', 0);

		$form = new Sales_Form_Quotepos();
		$form->uom->addMultiOptions($this->_helper->Uom->getUoms());
		$form->ordering->addMultiOptions($this->getOrdering($quoteid));
		$form->taxrate->addMultiOptions($this->_helper->TaxRate->getTaxRates($locale));

		if($request->isPost()) {
		    header('Content-type: application/json');
			$data = $request->getPost();
			$element = key($data);
			if(isset($form->$element) && $form->isValidPartial($data)) {
				$data['modified'] = $this->_date;
				$data['modifiedby'] = $this->_user['id'];
				if(($element == 'price') || ($element == 'quantity'))
					$data[$element] = Zend_Locale_Format::getNumber($data[$element],array('precision' => 2,'locale' => $locale));

				$positionDb = new Sales_Model_DbTable_Quotepos();
				$positionDb->updatePosition($id, $data);

				if(($element == 'price') || ($element == 'quantity') || ($element == 'taxrate')) {
					$calculations = $this->_helper->Calculate($quoteid, $this->_currency, $this->_date, $this->_user['id']);
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
			$position = new Sales_Model_DbTable_Quotepos();
			$data = $position->getPosition($id);
			$orderings = $this->getOrdering($data['quoteid']);
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
			$calculations = $this->_helper->Calculate($data['quoteid'], $this->_currency, $this->_date, $this->_user['id']);
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
			$orderings = $this->getOrdering($data['quoteid']);
			$currentOrdering = array_search($data['id'], $orderings); 
			$position = new Sales_Model_DbTable_Quotepos();
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
			$this->setOrdering($data['quoteid']);
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
				$positionDb = new Sales_Model_DbTable_Quotepos();
				$positionDb->deletePositions($data['id']);

				//Reorder and calculate
				$this->setOrdering($data['quoteid']);
				$calculations = $this->_helper->Calculate($data['quoteid'], $this->_currency, $this->_date, $this->_user['id']);
	            echo Zend_Json::encode($calculations['locale']);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$locale = Zend_Registry::get('Zend_Locale');

		$form = new Sales_Form_Quotepos();

		$form->uom->addMultiOptions($this->_helper->Uom->getUoms());
		$form->ordering->addMultiOptions($this->getOrdering($quoteid));
		$form->taxrate->addMultiOptions($this->_helper->TaxRate->getTaxRates($locale));

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function getPositions($quoteid)
	{
		$positionsDb = new Sales_Model_DbTable_Quotepos();
		$positions = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('quoteid = ?', $quoteid)
				->where('clientid = ?', $this->_user['clientid'])
				->order('ordering')
		);
		return $positions;
	}

	protected function setOrdering($quoteid)
	{
		$i = 1;
		$positions = $this->getPositions($quoteid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new Sales_Model_DbTable_Quotepos();
				$positionsDb->sortPosition($position->id, $i);
			}
			++$i;
		}
	}

	protected function getOrdering($quoteid)
	{
		$positions = $this->getPositions($quoteid);
		$i = 1;
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	protected function getLatestOrdering($quoteid)
	{
		$ordering = $this->getOrdering($quoteid);
		end($ordering);
		return key($ordering);
	}
}
