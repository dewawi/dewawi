<?php

class Application_Controller_Action_Helper_Position extends Zend_Controller_Action_Helper_Abstract
{
	public function copyPositions($positions, $parentid, $module, $controller, $created)
	{
		//Set target module and controller if parameter is array
		if(is_array($module)) list($sourceModule, $targetModule) = $module;
		else $sourceModule = $targetModule = $module;
		if(is_array($controller)) list($sourceController, $targetController) = $controller;
		else $sourceController = $targetController = $controller;

		//Define belonging classes
		$positionClass = ucfirst($targetModule).'_Model_DbTable_'.ucfirst($targetController.'pos');
		$positionSetClass = ucfirst($targetModule).'_Model_DbTable_'.ucfirst($targetController.'posset');
		$positionsDb = new $positionClass();
		$positionSetsDb = new $positionSetClass();

		$positionIndex = array();
		$positionSetIndex = array();

		//Copy master positions
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			if(!$dataPosition['masterid']) {
				if($dataPosition['possetid']) {
					if(isset($positionSetIndex[$dataPosition['possetid']]) && $positionSetIndex[$dataPosition['possetid']]) {
						$dataPosition['possetid'] = $positionSetIndex[$dataPosition['possetid']];
					} else {
						$dataPositionSet['parentid'] = $parentid;
						$dataPositionSet['created'] = $created;
						$dataPositionSet['modified'] = NULL;
						$dataPositionSet['modifiedby'] = 0;
						unset($dataPositionSet['id']);
						$positionSetId = $positionSetsDb->addPositionSet($dataPositionSet);
						$positionSetIndex[$dataPosition['possetid']] = $positionSetId;
						$dataPosition['possetid'] = $positionSetId;
					}
				}
				if($targetController == 'process') {
					$positionData['deliverystatus'] = 'deliveryIsWaiting';
					$positionData['supplierorderstatus'] = 'supplierNotOrdered';
				}
				$dataPosition['parentid'] = $parentid;
				$dataPosition['created'] = $created;
				$dataPosition['modified'] = NULL;
				$dataPosition['modifiedby'] = 0;
				unset($dataPosition['id']);
				$positionId = $positionsDb->addPosition($dataPosition);
				$positionIndex[$position->id] = $positionId;

				//Copy price rules
				$priceRuleHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');
				$pricerules = $priceRuleHelper->getPriceRulePositions($sourceModule, $sourceController.'pos', $position->id);
				foreach($pricerules as $pricerule) {
					$dataPricerule = $pricerule;
					$dataPricerule['module'] = $targetModule;
					$dataPricerule['controller'] = $targetController.'pos';
					$dataPricerule['parentid'] = $positionId;
					$dataPricerule['created'] = $created;
					$dataPricerule['modified'] = NULL;
					$dataPricerule['modifiedby'] = 0;
					unset($dataPricerule['id']);
					$priceRuleDb = new Items_Model_DbTable_Pricerulepos();
					$priceRuleDb->addPosition($dataPricerule);
				}
			}
		}

		//Copy child positions
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			if($dataPosition['masterid']) {
				if($dataPosition['possetid']) {
					$dataPosition['possetid'] = $positionSetIndex[$dataPosition['possetid']];
				}
				if($targetController == 'process') {
					$positionData['deliverystatus'] = 'deliveryIsWaiting';
					$positionData['supplierorderstatus'] = 'supplierNotOrdered';
				}
				$dataPosition['masterid'] = $positionIndex[$dataPosition['masterid']];
				$dataPosition['parentid'] = $parentid;
				$dataPosition['created'] = $created;
				$dataPosition['modified'] = NULL;
				$dataPosition['modifiedby'] = 0;
				unset($dataPosition['id']);
				$positionId = $positionsDb->addPosition($dataPosition);
			}
		}
	}
}
