<?php

class Processes_ProcessposController extends Zend_Controller_Action
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

		$processid = $this->_getParam('processid', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		//Get process
		$processDb = new Processes_Model_DbTable_Process();
		$process = $processDb->getProcess($processid);

		//Get positions
		$positions = $this->getPositions($processid);

		//Get units of measurements
		$uoms = $this->_helper->Uom->getUoms();
		$uoms = array_combine($uoms, $uoms);

		//Get shipping methods
		$shippingmethods = $this->_helper->ShippingMethod->getShippingMethods($this->_user['clientid']);

		$forms = array();
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$position->ordering] = $position->ordering;
		}
		foreach($positions as $position) {
			$position->price =  $this->_currency->toCurrency($position->price);
			$position->supplierinvoicetotal =  $this->_currency->toCurrency($position->supplierinvoicetotal);
			$position->quantity = Zend_Locale_Format::toNumber($position->quantity,array('precision' => 2,'locale' => $locale));

			$form = new Processes_Form_Processpos();
			$forms[$position->id] = $form->populate($position->toArray());
			$forms[$position->id]->uom->addMultiOptions($uoms);
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

	public function selectAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$positions = new Processes_Model_DbTable_Processpos();
		$this->view->positions = $positions->fetchAll();
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
				$data['taxrate'] = $item['taxid'] ? $this->_helper->TaxRate->getTaxRate($item['taxid']) : 0;
				$data['quantity'] = 1;
				$data['total'] = $data['price']*$data['quantity'];
				$data['uom'] = $item['uomid'] ? $this->_helper->Uom->getUom($item['uomid']) : '';
				$data['ordering'] = $this->getLatestOrdering($processid) + 1;
				$data['created'] = $this->_date;
				$data['createdby'] = $this->_user['id'];
				$data['clientid'] = $this->_user['clientid'];
				$position = new Sales_Model_DbTable_Processpos();
				$position->addPosition($data);

				//Calculate
				//$calculations = $this->_helper->Calculate($processid, $this->_currency, $this->_date, $this->_user['id']);
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

		$processid = (int)$this->_getParam('id', 0);

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
			$data['ordering'] = $this->getLatestOrdering($processid) + 1;
			$data['created'] = $this->_date;
			$data['createdby'] = $this->_user['id'];
			$data['clientid'] = $this->_user['clientid'];
			$position = new Sales_Model_DbTable_Processpos();
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

		$form = new Processes_Form_Processpos();
		$form->uom->addMultiOptions($this->_helper->Uom->getUoms());
		$form->shippingmethod->addMultiOptions($this->_helper->ShippingMethod->getShippingMethods($this->_user['clientid']));
		$form->ordering->addMultiOptions($this->getOrdering($processid));

		if($request->isPost()) {
		    header('Content-type: application/json');
			$data = $request->getPost();
			$element = key($data);
			if(isset($form->$element) && $form->isValidPartial($data)) {
				$data['modified'] = $this->_date;
				$data['modifiedby'] = $this->_user['id'];
				if(($element == 'price') || ($element == 'quantity') || ($element == 'supplierinvoicetotal'))
					$data[$element] = Zend_Locale_Format::getNumber($data[$element],array('precision' => 2,'locale' => $locale));

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
			$data['created'] = $this->_date;
			$data['createdby'] = $this->_user['id'];
			$data['modified'] = '0000-00-00';
			$data['modifiedby'] = 0;
			unset($data['id']);
			$position->addPosition($data);

			//Calculate
			//$calculations = $this->_helper->Calculate($data['processid'], $this->_currency, $this->_date, $this->_user['id']);
	        //echo Zend_Json::encode($calculations['locale']);
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
				//$calculations = $this->_helper->Calculate($data['processid'], $this->_currency, $this->_date, $this->_user['id']);
	            //echo Zend_Json::encode($calculations['locale']);
			}
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Processes_Form_Processpos();

		$form->uom->addMultiOptions($this->_helper->Uom->getUoms());
		$form->ordering->addMultiOptions($this->getOrdering($processid));
		$form->shippingmethod->addMultiOptions($this->_helper->ShippingMethod->getShippingMethods($this->_user['clientid']));

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}

	protected function getPositions($processid)
	{
		$positionsDb = new Processes_Model_DbTable_Processpos();
		$positions = $positionsDb->fetchAll(
			$positionsDb->select()
				->where('processid = ?', $processid)
				//->where('clientid = ?', $this->_user['clientid'])
				->order('ordering')
		);
		return $positions;
	}

	protected function setOrdering($processid)
	{
		$i = 1;
		$positions = $this->getPositions($processid);
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
		$positions = $this->getPositions($processid);
		$i = 1;
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
