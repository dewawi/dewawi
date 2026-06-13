<?php

class Zend_View_Helper_Menuitems extends Zend_View_Helper_Abstract
{
	public function Menuitems(array $config = [])
	{
		$menuId = (int)($config['menuid'] ?? 0);

		if ($menuId <= 0) {
			return '<div class="dw-empty">Menü zuerst speichern.</div>';
		}

		$menuitemDb = new Admin_Model_DbTable_Menuitem();
		$items = $menuitemDb->getItemsByMenuId($menuId);

		$list = new Admin_Model_List_Menuitems();
		$list->configure([
			'items' => $items,
			'view' => $this->view,
			'module' => 'admin',
			'controller' => 'menuitem',
			'context' => [
				'menuid' => $menuId,
			],
		]);

		ob_start();
		?>
		<div class="dw-child-list"
			 data-controller="menuitem"
			 data-menuid="<?php echo $this->view->escape($menuId); ?>">

			<div class="dw-child-list-toolbar">
				<a class="dw-btn"
				   href="<?php echo $this->view->url([
					   'module' => 'admin',
					   'controller' => 'menuitem',
					   'action' => 'add',
					   'menuid' => $menuId,
				   ], null, true); ?>">
					<?php echo $this->view->translate('ADMIN_ADD_MENUITEM'); ?>
				</a>
			</div>

			<?php echo $list->render(); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
