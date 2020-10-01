<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Actions extends Zend_View_Helper_Abstract{

	public function Actions() { ?>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'quote', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_QUOTE') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'salesorder', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_SALES_ORDER') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'invoice', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_INVOICE') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'deliveryorder', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_DELIVERY_ORDER') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'creditnote', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_CREDIT_NOTE') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'reminder', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_REMINDER') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'quoterequest', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_QUOTE_REQUEST') ?></a></p>
		<p><a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'purchaseorder', 'action'=>'add', 'contactid'=>$this->view->id));?>"><?php echo $this->view->translate('ACTIONS_CREATE_PURCHASE_ORDER') ?></a></p>
		<?php
	}
}
