<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Actions extends Zend_View_Helper_Abstract{

	public function Actions() { ?>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'quote', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_QUOTE') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'salesorder', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_SALES_ORDER') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'invoice', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_INVOICE') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'purchaseorder', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_PURCHASE_ORDER') ?></a></p>
		<?php
	}
}
