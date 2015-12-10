<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_MenuSettings extends Zend_View_Helper_Abstract{

	public function MenuSettings() { ?>
		<p>
			<a href="<?php echo $this->view->url(array('controller'=>'settings', 'action'=>'overview'), null, TRUE);?>"><?php echo $this->view->translate('SETTINGS_OVERVIEW') ?></a> -
			<a href="<?php echo $this->view->url(array('controller'=>'settings', 'action'=>'clients'), null, TRUE);?>"><?php echo $this->view->translate('SETTINGS_CLIENTS') ?></a> -
			<a href="<?php echo $this->view->url(array('controller'=>'settings', 'action'=>'countries'), null, TRUE);?>"><?php echo $this->view->translate('SETTINGS_COUNTRIES') ?></a> -
			<a href="<?php echo $this->view->url(array('controller'=>'settings', 'action'=>'manufacturers'), null, TRUE);?>"><?php echo $this->view->translate('SETTINGS_MANUFACTURERS') ?></a> -
			<a href="<?php echo $this->view->url(array('controller'=>'settings', 'action'=>'paymentmethods'), null, TRUE);?>"><?php echo $this->view->translate('SETTINGS_PAYMENT_METHODS') ?></a>-
			<a href="<?php echo $this->view->url(array('controller'=>'settings', 'action'=>'footers'), null, TRUE);?>"><?php echo $this->view->translate('SETTINGS_FOOTERS') ?></a>
		</p><?php
	}
}
