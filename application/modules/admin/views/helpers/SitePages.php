<?php

class Zend_View_Helper_SitePages extends Zend_View_Helper_Abstract
{
	public function SitePages(array $config = [])
	{
		$shopId = (int)($config['shopid'] ?? 0);

		if ($shopId <= 0) {
			return '';
		}

		$db = new Admin_Model_DbTable_Page();
		$items = $db->getPagesByShopId($shopId);

		foreach ($items as &$item) {
			$item['shoptitle'] = (string)($config['shoptitle'] ?? '');
		}
		unset($item);

		$toolbarInline = $this->view->toolbarInline ?? null;

		if (!$toolbarInline && class_exists('Admin_Form_ToolbarInline')) {
			$toolbarInline = new Admin_Form_ToolbarInline();
		}

		$list = new Admin_Model_List_Pages();
		$list->configure([
			'id' => 'site-pages',
			'items' => $items,
			'view' => $this->view,
			'module' => 'admin',
			'controller' => 'page',
			'toolbarInline' => $toolbarInline,
			'context' => [
				'user' => $this->view->user,
				'action' => 'index',
				'shopid' => $shopId,
			],
		]);

		ob_start();
		?>
		<div class="dw-child-list" data-list-id="site-pages" data-controller="page" data-shopid="<?php echo $this->view->escape($shopId); ?>">
			<div class="dw-child-list-toolbar">
				<a class="dw-btn" href="<?php echo $this->view->url([
					'module' => 'admin',
					'controller' => 'page',
					'action' => 'add',
					'shopid' => $shopId,
				], null, true); ?>">
					<?php echo $this->view->translate('ADMIN_NEW_PAGE'); ?>
				</a>
			</div>

			<?php echo $list->render(); ?>
		</div>
		<?php

		return ob_get_clean();
	}
}
