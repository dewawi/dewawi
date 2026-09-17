<?php

class Zend_View_Helper_Pageblocks extends Zend_View_Helper_Abstract
{
	public function Pageblocks(array $config = [])
	{
		$pageId = (int)($config['pageid'] ?? 0);

		if ($pageId <= 0) {
			return '<div class="dw-empty">'.$this->view->translate('ADMIN_SAVE_PAGE_FIRST').'</div>';
		}

		$db = new Application_Model_DbTable_Pageblock();
		$blocks = $db->getBlocksByPageId($pageId, false, 0);

		foreach ($blocks as &$block) {
			$definition = DEEC_Site_Block::getDefinition((string)$block['type']);
			$data = json_decode((string)($block['data'] ?? ''), true);

			$block['typelabel'] = $this->view->translate($definition['label']);
			$block['title'] = is_array($data) ? (string)($data['title'] ?? '') : '';
		}
		unset($block);

		$toolbarInline = $this->view->toolbarInline ?? null;

		if (!$toolbarInline && class_exists('Admin_Form_ToolbarInline')) {
			$toolbarInline = new Admin_Form_ToolbarInline();
		}

		$list = new Admin_Model_List_Pageblocks();
		$list->configure([
			'id' => 'pageblocks',
			'items' => $blocks,
			'view' => $this->view,
			'module' => 'admin',
			'controller' => 'pageblock',
			'toolbarInline' => $toolbarInline,
			'context' => [
				'user' => $this->view->user,
				'action' => 'index',
				'pageid' => $pageId,
			],
		]);

		ob_start();
		?>
		<div class="dw-child-list" data-list-id="pageblocks" data-controller="pageblock" data-pageid="<?php echo $this->view->escape($pageId); ?>">
			<div class="dw-child-list-toolbar">
				<span><?php echo $this->view->translate('ADMIN_ADD_PAGEBLOCK'); ?>:</span>

				<?php foreach (DEEC_Site_Block::getTypeOptions() as $type => $label): ?>
					<a class="dw-btn" href="<?php echo $this->view->url([
						'module' => 'admin',
						'controller' => 'pageblock',
						'action' => 'add',
						'pageid' => $pageId,
						'type' => $type,
					], null, true); ?>">
						<?php echo $this->view->translate($label); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php echo $list->render(); ?>
		</div>
		<?php

		return ob_get_clean();
	}
}
