<?php

class Shops_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	public function getOptions($itemId)
	{
		$itemOptionsDb = new Shops_Model_DbTable_Itemopt();
		$itemOptions = $itemOptionsDb->getPositions($itemId);

		$itemOptionSetsDb = new Shops_Model_DbTable_Itemoptset();
		$itemOptionSets = $itemOptionSetsDb->getPositionSets($itemId);

		$sets = array();

		if (empty($itemOptionSets)) {
			// If no item option sets are found, add a default empty item option set
			$sets[] = array(
				'title' => '',
				'options' => array()
			);
		} else {
			// Loop through item option sets
			foreach ($itemOptionSets as $itemOptionSet) {
				if($itemOptionSet->title) {
					$options = array();

					// Loop through item options and add them to the options array if they belong to the current item option set
					foreach ($itemOptions as $itemOption) {
						if ($itemOptionSet->id == $itemOption->optsetid) {
							$options[$itemOption->id] = $itemOption;
						}
					}

					// Add the item option set with its associated options to the sets array
					$sets[] = array(
						'title' => $itemOptionSet->title,
						'options' => $options
					);
				}
			}
		}

		return $sets;
	}
}
