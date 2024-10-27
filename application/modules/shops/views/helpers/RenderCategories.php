<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_RenderCategories extends Zend_View_Helper_Abstract
{
	public function RenderCategories($categories, $parentid = 0) {
		$output = '';
		if (!empty($categories)) {
			$output .= '<div class="row">';
			foreach ($categories as $category) {
				if ($parentid == $category['parentid']) {
					$output .= $this->view->RenderCategory($category);
				}
			}
			$output .= '</div>';
		}
		return $output;
	}
}
