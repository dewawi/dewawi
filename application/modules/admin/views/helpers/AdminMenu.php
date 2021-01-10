<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_AdminMenu extends Zend_View_Helper_Abstract
{
	public function AdminMenu() { ?>
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'index', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_OVERVIEW'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'client', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_CLIENTS'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'category', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_CATEGORIES'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'country', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_COUNTRIES'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'manufacturer', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_MANUFACTURER'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'paymentmethod', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_PAYMENT_METHODS'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'shippingmethod', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_SHIPPING_METHODS'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'taxrate', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_TAX_RATES'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'template', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_TEMPLATES'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'footer', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_FOOTERS'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'uom', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_UOMS'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'user', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_USERS'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'permission', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_PERMISSIONS'); ?></a> |
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'currency', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_CURRENCIES'); ?></a>
		<?php /*
		<a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'module', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_MODULES'); ?></a>
		*/ ?>
		<br>
		<br>
	<?php }
}
