<?php

class Application_Controller_Action_Helper_Calculate extends Zend_Controller_Action_Helper_Abstract
{
	public function direct($id, $currency, $date, $user, $taxfree = null) {
		$request = $this->getRequest();
		$module = $request->getParam('module', null);
		$controller = $request->getParam('controller', null);
		if(substr($controller, -3) == 'pos') $controller = substr($controller, 0, -3);
		$class = ucfirst($module).'_Model_DbTable_'.ucfirst($controller);
		$classPos = $class.'pos';
		$function = 'get'.ucfirst($controller);
		if(class_exists($class) || class_exists($classPos)) {
			//Get object
			$objectDb = new $class();
			$object = $objectDb->$function($id);

			//Get positions
			$positionsDb = new $classPos();
			$positions = $positionsDb->getPositions($id);

			$calculations = array();
			$calculations['row'] = array();
			$calculations['locale'] = array();
			$calculations['row']['subtotal'] = 0;
			$calculations['row']['taxes'] = array();
			foreach($positions as $position) {
				$calculations['row'][$position->id]['total'] = $position->price*$position->quantity;
				$calculations['row']['subtotal'] += $calculations['row'][$position->id]['total'];

				if(isset($calculations['row']['taxes']['total'])) $calculations['row']['taxes']['total'] += $calculations['row'][$position->id]['total']*$position->taxrate/100;
                else $calculations['row']['taxes']['total'] = $calculations['row'][$position->id]['total']*$position->taxrate/100;
				if(isset($calculations['row']['taxes'][$position->taxrate])) $calculations['row']['taxes'][$position->taxrate] += $calculations['row'][$position->id]['total']*$position->taxrate/100;
                else $calculations['row']['taxes'][$position->taxrate] = $calculations['row'][$position->id]['total']*$position->taxrate/100;

				$calculations['locale'][$position->id]['price'] = $currency->toCurrency($position->price);
				$calculations['locale'][$position->id]['total'] = $currency->toCurrency($calculations['row'][$position->id]['total']);

			    $objectPosDb = new $classPos();
				$objectPosDb->updatePosition($position->id, array('total' => ($position->price*$position->quantity*(1+$position->taxrate/100))));
			}

			if($taxfree === null) $taxfree = $object['taxfree'];
			if($taxfree) $calculations['row']['taxes']['total'] = 0;

            if(!isset($calculations['row']['taxes']['total'])) $calculations['row']['taxes']['total'] = 0;
			$calculations['row']['total'] = $calculations['row']['subtotal'] + $calculations['row']['taxes']['total'];

			$objectDb->updateTotal($id, $calculations['row']['subtotal'], $calculations['row']['taxes']['total'], $calculations['row']['total'], $date, $user);

			$calculations['locale']['subtotal'] = $currency->toCurrency($calculations['row']['subtotal']);
			$calculations['locale']['total'] = $currency->toCurrency($calculations['row']['subtotal']+$calculations['row']['taxes']['total']);
                foreach($calculations['row']['taxes'] as $key => $value)
                    $calculations['locale']['taxes'][$key] = $currency->toCurrency($value);
//print_r($calculations);
			return $calculations;
		}
	}
}
