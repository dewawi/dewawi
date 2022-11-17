<?php

class Application_Controller_Action_Helper_Position extends Zend_Controller_Action_Helper_Abstract
{
	public function copyPositions($positions, $parentid, $module, $target, $created)
	{
		//Define belonging classes
		$positionClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target.'pos');
		$positionSetClass = ucfirst($module).'_Model_DbTable_'.ucfirst($target.'posset');
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
				if($target == 'process') {
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
			}
		}

		//Copy child positions
		foreach($positions as $position) {
			$dataPosition = $position->toArray();
			if($dataPosition['masterid']) {
				if($dataPosition['possetid']) {
					$dataPosition['possetid'] = $positionSetIndex[$dataPosition['possetid']];
				}
				if($target == 'process') {
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
