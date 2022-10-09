<?php

class Application_Controller_Action_Helper_Ordering extends Zend_Controller_Action_Helper_Abstract
{
	public function setOrdering($parentid, $setid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($parentid, $setid);
		foreach($positions as $position) {
			if($position->ordering != $i) {
				if(!isset($positionsDb)) $positionsDb = new Sales_Model_DbTable_Creditnotepos();
				$positionsDb->sortPosition($position->id, $i);
			}
			++$i;
		}
	}

	public function sortOrdering($id, $parentid, $setid, $target)
	{
		$orderings = $this->getOrderingId($parentid, $setid);
		$currentOrdering = array_search($id, $orderings); 
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
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
		$this->setOrdering($parentid, $setid);
	}

	public function getOrdering($parentid, $setid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($parentid, $setid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->ordering;
			++$i;
		}
		return $orderings;
	}

	public function getOrderingId($parentid, $setid)
	{
		$i = 1;
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$positions = $positionsDb->getPositions($parentid, $setid);
		$orderings = array();
		foreach($positions as $position) {
			$orderings[$i] = $position->id;
			++$i;
		}
		return $orderings;
	}

	public function pushOrdering($origin, $parentid, $setid)
	{
		$positionsDb = new Sales_Model_DbTable_Creditnotepos();
		$orderings = $this->getOrderingId($parentid, $setid);
		foreach($orderings as $ordering => $positionId) {
			if($ordering > $origin) $positionsDb->updatePosition($positionId, array('ordering' => ($ordering+1)));
		}
	}

	public function getLatestOrdering($parentid, $setid)
	{
		$ordering = $this->getOrdering($parentid, $setid);
		end($ordering);
		return key($ordering);
	}
}
