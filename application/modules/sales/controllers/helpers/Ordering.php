<?php

class Application_Controller_Action_Helper_Ordering extends Zend_Controller_Action_Helper_Abstract
{
	public function setOrdering($parent, $type, $parentid, $setid, $masterid)
	{
		$i = 1;
		$positionClass = 'Sales_Model_DbTable_'.ucfirst($parent.$type);
		$positionsDb = new $positionClass();
		$positions = $positionsDb->getPositions($parentid, $setid, $masterid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new $positionClass();
				$positionsDb->sortPosition($position->id, $i);
			}
			++$i;
		}
	}

	public function sortOrdering($id, $parent, $type, $parentid, $setid, $masterid, $target)
	{
		$orderings = $this->getOrderingId($parent, $type, $parentid, $setid, $masterid);
		$currentOrdering = array_search($id, $orderings);
		$positionClass = 'Sales_Model_DbTable_'.ucfirst($parent.$type);
		$positionsDb = new $positionClass();
		if($target == 'down') {
			$positionsDb->sortPosition($id, $currentOrdering+1);
			$positionsDb->sortPosition($orderings[$currentOrdering+1], $currentOrdering);
		} elseif($target == 'up') {
			$positionsDb->sortPosition($id, $currentOrdering-1);
			$positionsDb->sortPosition($orderings[$currentOrdering-1], $currentOrdering);
		} elseif($target > 0) {
			if($target < $currentOrdering) {
				$positionsDb->sortPosition($id, $target);
				foreach($orderings as $ordering => $positionId) {
					if(($ordering < $currentOrdering) && ($ordering >= $target)) $positionsDb->sortPosition($positionId, $ordering+1);
				}
			} elseif($target > $currentOrdering) {
				$positionsDb->sortPosition($id, $target);
				foreach($orderings as $ordering => $positionId) {
					if(($ordering > $currentOrdering) && ($ordering <= $target)) $positionsDb->sortPosition($positionId, $ordering-1);
				}
			}
		}
		$this->setOrdering($parent, $type, $parentid, $setid, $masterid);
	}

	public function getOrdering($parent, $type, $parentid, $setid, $masterid = 0)
	{
		$i = 1;
		$positionClass = 'Sales_Model_DbTable_'.ucfirst($parent.$type);
		$positionsDb = new $positionClass();
		$positions = $positionsDb->getPositions($parentid, $setid, $masterid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->ordering;
			++$i;
		}
		return $orderings;
	}

	public function getOrderingId($parent, $type, $parentid, $setid, $masterid)
	{
		$i = 1;
		$positionClass = 'Sales_Model_DbTable_'.ucfirst($parent.$type);
		$positionsDb = new $positionClass();
		$positions = $positionsDb->getPositions($parentid, $setid, $masterid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	public function pushOrdering($origin, $parent, $type, $parentid, $setid, $masterid)
	{
		$positionClass = 'Sales_Model_DbTable_'.ucfirst($parent.$type);
		$positionsDb = new $positionClass();
		$orderings = $this->getOrderingId($parent, $type, $parentid, $setid, $masterid);
		foreach($orderings as $ordering => $positionId) {
			if($ordering > $origin) $positionsDb->updatePosition($positionId, array('ordering' => ($ordering+1)));
		}
	}

	public function getLatestOrdering($parent, $type, $parentid, $setid, $masterid = 0)
	{
		$ordering = $this->getOrdering($parent, $type, $parentid, $setid, $masterid);
		end($ordering);
		return key($ordering);
	}
}
