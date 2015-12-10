<?php
/**
* Class inserts neccery code for initialize file manager elFinder	
*/
class Zend_View_Helper_TreeMenu extends Zend_View_Helper_Abstract{

	public function TreeMenu($elements) {
		//$this->view->headScript()->prependFile($this->view->baseUrl()."/js/simpletreemenu.js"); ?>
		<p><a href="javascript:ddtreemenu.flatten('treemenu', 'expand')"><?php echo $this->view->translate('CATEGORIES_EXPAND_ALL'); ?></a> | <a href="javascript:ddtreemenu.flatten('treemenu', 'contact')"><?php echo $this->view->translate('CATEGORIES_COLLAPSE_ALL'); ?></a></p>
		<?php echo $this->getElements($elements); ?>
		<script type="text/javascript">
			ddtreemenu.createTree("treemenu", true);
		</script>
	<?php
	}

	public function getElements($categories, $id = 0)
	{
		$i = 1;
		if(!$id) $categoryTree = '<ul id="treemenu" class="treeview">';
		else $categoryTree = '<ul>';
		$count = count($categories);
		foreach($categories as $catid => $category) {
			if($category['parent'] == $id) {
				$categoryTree .= '<li><a id="'.$category['id'].'" href="#">'.$category['title'].'</a>';
				if(isset($category['childs']) && !empty($category['childs'])) {
					$categoryTree .= $this->getElements($categories, $category['id']);
				}
				$categoryTree .= '</li>';
				++$i;
			}
		}
		$categoryTree .= '</ul>';
		return $categoryTree;
	}
}
