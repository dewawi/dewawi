<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Actions extends Zend_View_Helper_Abstract{

	public function Actions() {
		if($this->view->controller == "quote") : ?>
			<h4>Belege generieren</h4>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatesalesorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_SALES_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generateinvoice'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_INVOICE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatedeliveryorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_DELIVERY_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatequoterequest'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_QUOTE_REQUEST') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatepurchaseorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_PURCHASE_ORDER') ?></a></p>
		<?php elseif($this->view->controller == "salesorder") : ?>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatequote'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_QUOTE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generateinvoice'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_INVOICE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatedeliveryorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_DELIVERY_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatequoterequest'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_QUOTE_REQUEST') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatepurchaseorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_PURCHASE_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generateprocess'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_PROCESS') ?></a></p>
		<?php elseif($this->view->controller == "invoice") : ?>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatequote'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_QUOTE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatesalesorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_SALES_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatedeliveryorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_DELIVERY_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatecreditnote'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_CREDIT_NOTE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatequoterequest'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_QUOTE_REQUEST') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatepurchaseorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_PURCHASE_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generateprocess'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_PROCESS') ?></a></p>
		<?php elseif($this->view->controller == "deliveryorder") : ?>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatesalesorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_SALES_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generateinvoice'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_INVOICE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatequoterequest'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_QUOTE_REQUEST') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatepurchaseorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_PURCHASE_ORDER') ?></a></p>
		<?php elseif($this->view->controller == "creditnote") : ?>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatesalesorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_SALES_ORDER') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generateinvoice'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_INVOICE') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatequoterequest'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_QUOTE_REQUEST') ?></a></p>
			<p><a href="<?php echo $this->view->url(array('module'=>$this->view->module, 'controller'=>$this->view->controller, 'action'=>'generatepurchaseorder'));?>"><?php echo $this->view->translate('ACTIONS_GENERATE_PURCHASE_ORDER') ?></a></p>
		<?php endif;
	}
}
