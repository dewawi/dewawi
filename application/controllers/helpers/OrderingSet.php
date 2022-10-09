<?php

class Application_Controller_Action_Helper_OrderingSet extends Zend_Controller_Action_Helper_Abstract
{
	public function setOrdering($parentid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Creditnoteposset();
		$positions = $positionsDb->getPositionSets($parentid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new Sales_Model_DbTable_Creditnoteposset();
				$positionsDb->sortPositionSet($position->id, $i);
			}
			++$i;
		}
	}

	public function sortOrdering($id, $parentid, $target)
	{
		$orderings = $this->getOrderingId($parentid);
		$currentOrdering = array_search($id, $orderings); 
		$positionsDb = new Sales_Model_DbTable_Creditnoteposset();
		if($target == 'down') {
			$positionsDb->sortPositionSet($id, $currentOrdering+1);
			$positionsDb->sortPositionSet($orderings[$currentOrdering+1], $currentOrdering);
		} elseif($target == 'up') {
			$positionsDb->sortPositionSet($id, $currentOrdering-1);
			$positionsDb->sortPositionSet($orderings[$currentOrdering-1], $currentOrdering);
		} elseif($target > 0) {
			if($target < $currentOrdering) {
				$positionsDb->sortPositionSet($id, $target);
				foreach($orderings as $ordering => $positionId) {
					if(($ordering < $currentOrdering) && ($ordering >= $target)) $positionsDb->sortPositionSet($positionId, $ordering+1);
				}
			} elseif($target > $currentOrdering) {
				$positionsDb->sortPositionSet($id, $target);
				foreach($orderings as $ordering => $positionId) {
					if(($ordering > $currentOrdering) && ($ordering <= $target)) $positionsDb->sortPositionSet($positionId, $ordering-1);
				}
			}
		}
		$this->setOrdering($parentid);
	}

	public function getOrdering($parentid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Creditnoteposset();
		$positions = $positionsDb->getPositionSets($parentid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->ordering;
			++$i;
		}
		return $orderings;
	}

	public function getOrderingId($parentid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Creditnoteposset();
		$positions = $positionsDb->getPositionSets($parentid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	public function pushOrdering($origin, $parentid)
	{
		$positionsDb = new Sales_Model_DbTable_Creditnoteposset();
		$orderings = $this->getOrderingId($parentid);
		foreach($orderings as $ordering => $positionId) {
			if($ordering > $origin) $positionsDb->updatePositionSet($positionId, array('ordering' => ($ordering+1)));
		}
	}

	public function getLatestOrdering($parentid)
	{
		$ordering = $this->getOrdering($parentid);
		end($ordering);
		return key($ordering);
	}
}
