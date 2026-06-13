<?php

class Zend_View_Helper_Menuitems extends Zend_View_Helper_Abstract
{
	public function Menuitems(array $config = [])
	{
		$menuId = (int)($config['menuid'] ?? 0);

		if ($menuId <= 0) {
			return '<div class="dw-empty">Menü zuerst speichern.</div>';
		}

		$db = new Admin_Model_DbTable_Menuitem();
		$items = $db->getItemsByMenuId($menuId);

		$toolbarInline = $this->view->toolbarInline ?? null;

		if (!$toolbarInline && class_exists('Admin_Form_ToolbarInline')) {
			$toolbarInline = new Admin_Form_ToolbarInline();
		}

		$list = new Admin_Model_List_Menuitems();
		$list->configure([
			'id' => 'menuitems',
			'items' => $items,
			'view' => $this->view,
			'module' => 'admin',
			'controller' => 'menuitem',
			'toolbarInline' => $toolbarInline,
			'context' => [
				'user' => $this->view->user,
				'action' => 'index',
				'menuid' => $menuId,
			],
		]);

		ob_start();
		?>
		<div class="dw-child-list"
			 data-list-id="menuitems"
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
