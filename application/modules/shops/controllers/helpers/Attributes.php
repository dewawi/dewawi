<?php

class Shops_Controller_Action_Helper_Attributes extends Zend_Controller_Action_Helper_Abstract
{
    public function getAttributes($itemId)
    {
        $itemAttributesDb = new Shops_Model_DbTable_Itematr();
        $itemAttributes = $itemAttributesDb->itemAttributes($itemId);

        $itemAttributeSetsDb = new Shops_Model_DbTable_Itematrset();
        $itemAttributeSets = $itemAttributeSetsDb->itemAttributeSets($itemId);

        $sets = array();

        if (empty($itemAttributeSets)) {
            // If no item attribute sets are found, add a default empty item attribute set
            $sets[] = array(
                'title' => '',
                'attributes' => array()
            );
        } else {
            // Loop through item attribute sets
            foreach ($itemAttributeSets as $itemAttributeSet) {
				if($itemAttributeSet->title) {
		            $attributes = array();

		            // Loop through item attributes and add them to the attributes array if they belong to the current item attribute set
		            foreach ($itemAttributes as $itemAttribute) {
		                if ($itemAttributeSet->id == $itemAttribute->atrsetid) {
		                    $attributes[$itemAttribute->id] = $itemAttribute;
		                }
		            }

		            // Add the item attribute set with its associated attributes to the sets array
		            $sets[] = array(
		                'title' => $itemAttributeSet->title,
		                'attributes' => $attributes
		            );
		        }
            }
        }

        return $sets;
    }
}
