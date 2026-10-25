<?php

class Sales_PositionsetController extends DEEC_Controller_Action
{
	public function addAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$params = $this->_getAllParams();

		//Define belonging classes
		$parentClass = 'Sales_Model_DbTable_'.ucfirst($params['parent']);
		$positionClass = 'Sales_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
		$positionSetClass = 'Sales_Model_DbTable_'.ucfirst($params['parent'].$params['type'].'set');

		//Get parent data
		$parentDb = new $parentClass();
		$parent = $parentDb->getById($params['parentid']);

		if($this->getRequest()->isPost()) {
			$adapter = $this->getParentDb($params)->getAdapter();
			$adapter->beginTransaction();
			try {
				$this->lockPositionParent($params);
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
					$data['ordering'] = $this->_helper->OrderingSet->getLatestOrdering($params['parent'], $params['type'], $params['parentid']) + 1;
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
					$data['ordering'] = $this->_helper->OrderingSet->getLatestOrdering($params['parent'], $params['type'], $params['parentid']) + 1;
					$positionSetDb = new $positionSetClass();
					$positionSetId = $positionSetDb->addPositionSet($data);

					//Move all positions to the new set
					$positionData = array($params['type'].'setid' => $positionSetId);
					foreach($positions as $position) {
						$positionsDb->updatePosition($position->id, $positionData);
					}
				}
				$adapter->commit();
			} catch (Throwable $exception) {
				$adapter->rollBack();
				throw $exception;
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
		$formClass = 'Sales_Form_'.ucfirst($params['parent'].$params['type']);
		$modelClass = 'Sales_Model_DbTable_'.ucfirst($params['parent'].$params['type'].'set');

		$form = new $formClass();
		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['type'], $params['parentid'], $params['id']));
		$form->taxrate->addMultiOptions($taxrates);

		if($request->isPost()) {
			$adapter = $this->getParentDb($params)->getAdapter();
			$adapter->beginTransaction();
			try {
				$this->lockPositionParent($params);
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
						$calculations = $this->_helper->Calculate($params['parentid'], 'sales', $params['parent']);
						echo Zend_Json::encode($calculations['locale']);
					}
					echo Zend_Json::encode($position->getPositionSet($params['id']));
				} else {
					throw new Exception('Form is invalid');
				}
				$adapter->commit();
			} catch (Throwable $exception) {
				$adapter->rollBack();
				throw $exception;
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

			$parentClass = 'Sales_Model_DbTable_'.ucfirst($params['parent']);
			$parentDb = new $parentClass();
			$adapter = $parentDb->getAdapter();
			$adapter->beginTransaction();
			try {
				$parent = $parentDb->getByIdForUpdate((int)$params['parentid']);
				if (!$parent || DEEC_Permission::isDocumentReadonly('sales', $params['parent'], $parent)
					|| in_array((int)($parent['state'] ?? 0), [105, 106], true)) {
					throw new RuntimeException('Position parent is not editable');
				}
				//Define belonging classes
				$positionClass = 'Sales_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
				$positionSetClass = 'Sales_Model_DbTable_'.ucfirst($params['parent'].$params['type'].'set');

				//Copy position set
				$positionSetDb = new $positionSetClass();
				$data = $positionSetDb->getPositionSet($params['id']);
				$this->_helper->OrderingSet->pushOrdering($data['ordering'], $params['parent'], $params['type'], $data['parentid']);
				$data['ordering'] += 1;
				$data['modified'] = NULL;
				$data['modifiedby'] = 0;
				unset($data['id']);
				$id = $positionSetDb->addPositionSet($data);

				//Get positions and copy
				$positionDb = new $positionClass();
				$positions = $positionDb->getPositions($params['parentid'], $params['id']);
				$calculations = $this->_helper->Position->copyPositions($positions, $params['parentid'], 'sales', $params['parent'], $this->_date, (int)$id);
				$adapter->commit();
			} catch (Throwable $exception) {
				$adapter->rollBack();
				throw $exception;
			}
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
			$adapter = $this->getParentDb($params)->getAdapter();
			$adapter->beginTransaction();
			try {
				$this->lockPositionParent($params);
				$data = $request->getPost();
				$this->_helper->OrderingSet->sortOrdering($data['id'], $params['parent'], $params['type'], $params['parentid'], $data['ordering']);
				$adapter->commit();
			} catch (Throwable $exception) {
				$adapter->rollBack();
				throw $exception;
			}
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
				$parentClass = 'Sales_Model_DbTable_'.ucfirst($params['parent']);
				$parentDb = new $parentClass();
				$adapter = $parentDb->getAdapter();
				$adapter->beginTransaction();
				try {
					$parent = $parentDb->getByIdForUpdate((int)$params['parentid']);
					if (!$parent || DEEC_Permission::isDocumentReadonly('sales', $params['parent'], $parent)
						|| in_array((int)($parent['state'] ?? 0), [105, 106], true)) {
						throw new RuntimeException('Position parent is not editable');
					}
					//Define belonging classes
					$positionClass = 'Sales_Model_DbTable_'.ucfirst($params['parent'].$params['type']);
					$positionSetClass = 'Sales_Model_DbTable_'.ucfirst($params['parent'].$params['type'].'set');

					//Get positions and delete
					$positionDb = new $positionClass();
					$positions = $positionDb->getPositions($data['parentid'], $data['id']);
					foreach($positions as $position) {
						$positionDb->deletePosition($position->id, false);
					}

					//Delete position set itself
					$positionSetDb = new $positionSetClass();
					$positionSetDb->deletePositionSet($data['id']);

					//Reorder and calculate
					$this->_helper->OrderingSet->setOrdering($params['parent'], $params['type'], $data['parentid'], $data['id']);
					$calculations = $this->_helper->Calculate($data['parentid'], 'sales', $params['parent']);
					$adapter->commit();
				} catch (Throwable $exception) {
					$adapter->rollBack();
					throw $exception;
				}
				echo Zend_Json::encode($calculations['locale']);
			}
		}
	}

	protected function getParentDb(array $params): DEEC_Model_DbTable_Entity
	{
		$class = 'Sales_Model_DbTable_' . ucfirst($params['parent']);
		return new $class();
	}

	protected function lockPositionParent(array $params): void
	{
		$parent = $this->getParentDb($params)->getByIdForUpdate((int)$params['parentid']);
		if (!$parent || DEEC_Permission::isDocumentReadonly('sales', $params['parent'], $parent)) {
			throw new RuntimeException('Position parent is not editable');
		}
	}

	public function validateAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$params = $this->_getAllParams();
		$locale = Zend_Registry::get('Zend_Locale');

		$formClass = 'Sales_Form_'.ucfirst($params['parent'].$params['type']);
		$form = new $formClass();

		//Get uoms
		$uomDb = new Application_Model_DbTable_Uom();
		$uoms = $uomDb->getUoms();

		//Get tax rates
		$taxrateDb = new Application_Model_DbTable_Taxrate();
		$taxrates = $taxrateDb->getTaxrates();

		$form->uom->addMultiOptions($uoms);
		$form->ordering->addMultiOptions($this->_helper->Ordering->getOrdering($params['parent'], $params['type'], $params['parentid']));
		$form->taxrate->addMultiOptions($taxrates);

		$data = $this->getRequest()->getPost();
		$form->$data['element']->isValid($data[$data['element']]);

		$json = $form->getMessages();
		header('Content-type: application/json');
		echo Zend_Json::encode($json);
	}
}
