<?php

class Application_Controller_Action_Helper_OrderingSet extends Zend_Controller_Action_Helper_Abstract
{
	public function setOrdering($parent, $type, $parentid)
	{
		$i = 1;
		$positionClass = 'Purchases_Model_DbTable_'.ucfirst($parent.$type.'set');
		$positionsDb = new $positionClass();
		$positions = $positionsDb->getPositionSets($parentid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new $positionClass();
				$positionsDb->sortPositionSet($position->id, $i);
			}
			++$i;
		}
	}

	public function sortOrdering($id, $parent, $type, $parentid, $target)
	{
		$orderings = $this->getOrderingId($parent, $type, $parentid);
		$currentOrdering = array_search($id, $orderings); 
		$positionClass = 'Purchases_Model_DbTable_'.ucfirst($parent.$type.'set');
		$positionsDb = new $positionClass();
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
		$this->setOrdering($parent, $type, $parentid);
	}

	public function getOrdering($parent, $type, $parentid)
	{
		$i = 1;
		$positionClass = 'Purchases_Model_DbTable_'.ucfirst($parent.$type.'set');
		$positionsDb = new $positionClass();
		$positions = $positionsDb->getPositionSets($parentid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->ordering;
			++$i;
		}
		return $orderings;
	}

	public function getOrderingId($parent, $type, $parentid)
	{
		$i = 1;
		$positionClass = 'Purchases_Model_DbTable_'.ucfirst($parent.$type.'set');
		$positionsDb = new $positionClass();
		$positions = $positionsDb->getPositionSets($parentid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	public function pushOrdering($origin, $parent, $type, $parentid)
	{
		$positionClass = 'Purchases_Model_DbTable_'.ucfirst($parent.$type.'set');
		$positionsDb = new $positionClass();
		$orderings = $this->getOrderingId($parent, $type, $parentid);
		foreach($orderings as $ordering => $positionId) {
			if($ordering > $origin) $positionsDb->updatePositionSet($positionId, array('ordering' => ($ordering+1)));
		}
	}

	public function getLatestOrdering($parent, $type, $parentid)
	{
		$ordering = $this->getOrdering($parent, $type, $parentid);
		end($ordering);
		return key($ordering);
	}
}
