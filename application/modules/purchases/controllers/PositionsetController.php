<?php

class Purchases_PositionsetController extends Zend_Controller_Action
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

	public function addAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$params = $this->_getAllParams();

		//Define belonging classes
		$parentClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent']);
		$positionClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].'pos');
		$positionSetClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].'posset');

		//Get parent data
		$parentDb = new $parentClass();
		$parentMethod = 'get'.$params['parent'];
		$parent = $parentDb->$parentMethod($params['parentid']);

		if($this->getRequest()->isPost()) {
			//Get existing position sets
			$positionSetDb = new $positionSetClass();
			$positionSets = $positionSetDb->getPositionSets($params['parentid']);

			//Check if there are position sets already
			if(count($positionSets)) {
				$data = array();
				$data['parentid'] = $params['parentid'];
				$data['title'] = '';
				$data['image'] = '';
				$data['description'] = '';
				$data['ordering'] = $this->_helper->OrderingSet->getLatestOrdering($params['parent'], $params['parentid']) + 1;
				$positionSetDb = new $positionSetClass();
				$positionSetDb->addPositionSet($data);
			} else {
				//Get existing positions
				$positionsDb = new $positionClass();
				$positions = $positionsDb->getPositions($params['parentid']);

				//Create a new position set
				$data = array();
				$data['parentid'] = $params['parentid'];
				$data['title'] = '';
				$data['image'] = '';
				$data['description'] = '';
				$data['ordering'] = $this->_helper->OrderingSet->getLatestOrdering($params['parent'], $params['parentid']) + 1;
				$positionSetDb = new $positionSetClass();
				$positionSetId = $positionSetDb->addPositionSet($data);

				//Move all positions to the new set
				$positionData = array('possetid' => $positionSetId);
				foreach($positions as $position) {
					$positionsDb->updatePosition($position->id, $positionData);
				}
			}
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
		$formClass = 'Purchases_Form_'.ucfirst($params['parent'].'pos');
		$modelClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].'posset');

		$form = new $formClass();
		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['parentid'], $params['id']));
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
				$position->updatePositionSet($params['id'], $data);

				if(($element == 'price') || ($element == 'quantity') || ($element == 'taxrate') || ($element == 'priceruleamount') || ($element == 'priceruleaction')) {
					$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
					echo Zend_Json::encode($calculations['locale']);
				}
				echo Zend_Json::encode($position->getPositionSet($params['id']));
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
			$positionClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].'pos');
			$positionDb = new $positionClass();
			$data = $positionDb->getPosition($params['id']);
			$this->_helper->Ordering->pushOrdering($data['ordering'], $params['parent'], $data['parentid']);
			$data['ordering'] += 1;
			$data['modified'] = NULL;
			$data['modifiedby'] = 0;
			unset($data['id']);
			$positionDb->addPosition($data);

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
			$this->_helper->OrderingSet->sortOrdering($data['id'], $params['parent'], $params['parentid'], $data['ordering']);
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
				$positionClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].'pos');
				$positionSetClass = 'Purchases_Model_DbTable_'.ucfirst($params['parent'].'posset');

				$creditnote = new $positionSetClass();
				$creditnote->deleteCreditnote($id);

				$positionDb = new $positionClass();
				$positionDb->getPositions($data['id']);

				//Reorder and calculate
				$this->_helper->Ordering->setOrdering($params['parent'], $params['parentid']);
				$calculations = $this->_helper->Calculate($params['parentid'], $this->_date, $this->_user['id']);
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

		$formClass = 'Purchases_Form_'.ucfirst($params['parent'].'pos');
		$form = new $formClass();

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['parentid']));
		$form->taxrate->addMultiOptions($taxrates);

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}
}
