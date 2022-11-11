<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_AdminMenu extends Zend_View_Helper_Abstract
{
	public function AdminMenu() { ?>
		<ul>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'index', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_OVERVIEW'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'client', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_CLIENTS'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'country', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_COUNTRIES'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'upload', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_UPLOAD'); ?></a></li>
		</ul>
		<h4><?php echo $this->view->translate('ADMIN_ITEMS'); ?></h4>
		<ul>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'category', 'action'=>'index', 'type'=>'item', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_CATEGORIES'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'taxrate', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_TAX_RATES'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'uom', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_UOMS'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'manufacturer', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_MANUFACTURERS'); ?></a></li>
		</ul>
		<h4><?php echo $this->view->translate('ADMIN_CONTACTS'); ?></h4>
		<ul>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'category', 'action'=>'index', 'type'=>'contact', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_CATEGORIES'); ?></a></li>
		</ul>
		<h4><?php echo $this->view->translate('ADMIN_PAYMENT'); ?></h4>
		<ul>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'paymentmethod', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_PAYMENT_METHODS'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'currency', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_CURRENCIES'); ?></a></li>
		</ul>
		<h4><?php echo $this->view->translate('ADMIN_DELIVERY'); ?></h4>
		<ul>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'shippingmethod', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_SHIPPING_METHODS'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'deliverytime', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_DELIVERY_TIMES'); ?></a></li>
		</ul>
		<h4><?php echo $this->view->translate('ADMIN_USERS'); ?></h4>
		<ul>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'user', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_USERS'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'permission', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_PERMISSIONS'); ?></a></li>
		</ul>
		<h4><?php echo $this->view->translate('ADMIN_DESIGN'); ?></h4>
		<ul>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'template', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_TEMPLATES'); ?></a></li>
			<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'footer', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_FOOTERS'); ?></a></li>
		</ul>
		<?php if($this->view->user['id'] == 100) : ?>
			<h4><?php echo $this->view->translate('ADMIN_SYSTEM'); ?></h4>
			<ul>
				<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'info', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_INFO'); ?></a></li>
				<li><a href="<?php echo $this->view->url(array('module'=>'admin', 'controller'=>'module', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('ADMIN_MODULES'); ?></a></li>
			</ul>
		<?php endif; ?>
		<br>
		<br>
	<?php }
}
