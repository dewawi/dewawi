<?php
/**
* Class inserts neccery code for Menu
*/
class Zend_View_Helper_MainMenu extends Zend_View_Helper_Abstract{

	public function MainMenu() { ?>
		<div class="row" id="doc-wrapper">
			<input id="doc-drawer-checkbox" class="drawer" value="On" type="checkbox">
			<nav class="col-md-4 col-lg-3" id="nav-drawer" style="position: fixed;">
				<h3>Menu</h3>
				<label for="doc-drawer-checkbox" class="button drawer-close"></label>
				<a href="<?php echo $this->view->url(array('module'=>'index', 'controller'=>'index', 'action'=>'index', 'id'=>null)); ?>">Übersicht</a>
				<a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'index', 'id'=>null)); ?>">Kontakte</a>
				<a href="<?php echo $this->view->url(array('module'=>'items', 'controller'=>'item', 'action'=>'index', 'id'=>null)); ?>">Artikel</a>
				<a href="<?php echo $this->view->url(array('module'=>'tasks', 'controller'=>'task', 'action'=>'index', 'id'=>null)); ?>">Aufgaben</a>
				<a href="<?php echo $this->view->url(array('module'=>'processes', 'controller'=>'process', 'action'=>'index', 'id'=>null)); ?>">Vorgänge</a>
				<span>Verkauf</span>
				<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'quote', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1">Angebote</a>
				<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'salesorder', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1">Auftragsbestätigungen</a>
				<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'deliveryorder', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1">Lieferscheine</a>
				<span>Einkauf</span>
				<a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'quoterequest', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1"><?php echo $this->view->translate('MENU_QUOTE_REQUESTS'); ?></a>
				<a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'purchaseorder', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1">Bestellungen</a>
				<span>Buchhaltung</span>
				<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'invoice', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1">Abrechnung</a>
				<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'creditnote', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1">Gutschriften</a>
				<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'reminder', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1">Mahnungen</a>
				<a href="<?php echo $this->view->url(array('module'=>'statistics', 'controller'=>'index', 'action'=>'index', 'id'=>null)); ?>">Statistiken</a>
			</nav>
		</div>

		<div class="row">
			<div class="col-sm-12 hidden-sm">
				<header>
					<a class="button" href="<?php echo $this->view->url(array('module'=>'index', 'controller'=>'index', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('CONTROL_PANEL'); ?></a>
					<div class="sub">
						<button<?php if(($this->view->controller == 'contact') || ($this->view->controller == 'email') || ($this->view->controller == 'download')) echo ' class="active"'; ?>><?php echo $this->view->translate('MENU_CONTACTS'); ?></button>
						<div class="sub1">
							<a class="button" href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_CONTACTS'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'email', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1"><?php echo $this->view->translate('MENU_EMAILS'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'download', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1"><?php echo $this->view->translate('MENU_DOWNLOADS'); ?></a>
						</div>
					</div>
					<div class="sub">
						<button<?php if(($this->view->controller == 'item' || $this->view->controller == 'attribute' || $this->view->controller == 'option') || ($this->view->controller == 'itemlist')) echo ' class="active"'; ?>><?php echo $this->view->translate('MENU_ITEMS'); ?></button>
						<div class="sub1">
							<a class="button" href="<?php echo $this->view->url(array('module'=>'items', 'controller'=>'item', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_ITEMS'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'items', 'controller'=>'itemlist', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1"><?php echo $this->view->translate('MENU_ITEM_LISTS'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'items', 'controller'=>'inventory', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1"><?php echo $this->view->translate('MENU_INVENTORY'); ?></a>
						</div>
					</div>
					<a class="button<?php if($this->view->controller == 'task') echo ' active'; ?>" href="<?php echo $this->view->url(array('module'=>'tasks', 'controller'=>'task', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_TASKS'); ?></a>
					<a class="button<?php if($this->view->controller == 'process') echo ' active'; ?>" href="<?php echo $this->view->url(array('module'=>'processes', 'controller'=>'process', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_PROCESSES'); ?></a>
					<div class="sub">
						<button<?php if(($this->view->controller == 'quote') || ($this->view->controller == 'salesorder') || ($this->view->controller == 'deliveryorder')) echo ' class="active"'; ?>><?php echo $this->view->translate('MENU_SALES'); ?></button>
						<div class="sub1">
							<a class="button" href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'quote', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_QUOTES'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'salesorder', 'action'=>'index', 'id'=>null)); ?>" class="sublink-1"><?php echo $this->view->translate('MENU_SALES_ORDERS'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'deliveryorder', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_DELIVERY_ORDERS'); ?></a>
						</div>
					</div>
					<div class="sub">
						<button<?php if(($this->view->controller == 'quoterequest') || ($this->view->controller == 'purchaseorder')) echo ' class="active"'; ?>><?php echo $this->view->translate('MENU_PURCHASES'); ?></button>
						<div class="sub1">
							<a class="button" href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'quoterequest', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_QUOTE_REQUESTS'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'purchaseorder', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_PURCHASE_ORDERS'); ?></a>
						</div>
					</div>
					<div class="sub">
						<button<?php if(($this->view->controller == 'invoice') || ($this->view->controller == 'creditnote') || ($this->view->controller == 'reminder')) echo ' class="active"'; ?>>Abrechnung</button>
						<div class="sub1">
							<a class="button" href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'invoice', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_INVOICES'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'creditnote', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_CREDIT_NOTES'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'reminder', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_REMINDERS'); ?></a>
						</div>
					</div>
					<a class="button<?php if($this->view->controller == 'pricerule') echo ' active'; ?>" href="<?php echo $this->view->url(array('module'=>'items', 'controller'=>'pricerule', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('MENU_PRICE_RULES'); ?></a>
					<div class="sub">
						<button<?php if($this->view->module == 'statistics') echo ' class="active"'; ?>><?php echo $this->view->translate('MENU_STATISTICS'); ?></button>
						<div class="sub1">
							<a class="button" href="<?php echo $this->view->url(array('module'=>'statistics', 'controller'=>'turnover', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('STATISTICS_TURNOVER'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'statistics', 'controller'=>'customer', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('STATISTICS_CUSTOMER'); ?></a>
							<a class="button" href="<?php echo $this->view->url(array('module'=>'statistics', 'controller'=>'quote', 'action'=>'index', 'id'=>null)); ?>"><?php echo $this->view->translate('STATISTICS_QUOTE'); ?></a>
						</div>
					</div>
				</header>
			</div>
		</div>
	<?php }
}
