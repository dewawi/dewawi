<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Actions extends Zend_View_Helper_Abstract{

	public function Actions() {
		if($this->view->controller == "quoterequest") : ?>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generate', 'target'=>'salesorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_SALES_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generate', 'target'=>'invoice'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_INVOICE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generate', 'target'=>'purchaseorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_PURCHASE_ORDER') ?></a></p>
		<?php elseif($this->view->controller == "purchaseorder") : ?>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generate', 'target'=>'salesorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_SALES_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generate', 'target'=>'invoice'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_INVOICE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generate', 'target'=>'quoterequest'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_QUOTE_REQUEST') ?></a></p>
		<?php endif;
	}
}
