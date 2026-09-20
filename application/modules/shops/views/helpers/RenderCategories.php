<?php

class Zend_View_Helper_RenderCategories extends Zend_View_Helper_Abstract
{
	public function RenderCategories(array $categories, int $parentId = 0): string
	{
		if (!$categories) return '';

		$html = '<div class="row">';

		foreach ($categories as $category) {
			if ((int)$category['parentid'] !== $parentId) continue;

			$html .= $this->view->RenderCategory($category);
		}

		$html .= '</div>';

		return $html;
	}
}
