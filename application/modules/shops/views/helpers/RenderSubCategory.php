<?php

class Zend_View_Helper_RenderSubCategory extends Zend_View_Helper_Abstract
{
	public function RenderSubCategory(array $categories, int $parentId = 0): string
	{
		$html = '';

		foreach ($categories as $subcategory) {
			if ((int)$subcategory['parentid'] !== $parentId) {
				continue;
			}

			$url = $this->view->SlugUrl('category', (int)$subcategory['id']);

			if (!$url) {
				continue;
			}

			$children = $this->RenderSubCategory($categories, (int)$subcategory['id']);

			$html .= '<li>';
			$html .= '<a href="' . $this->view->escape($url) . '">' . $this->view->escape((string)$subcategory['title']) . '</a>';

			if ($children !== '') {
				$html .= '<ul class="submenu">' . $children . '</ul>';
			}

			$html .= '</li>';
		}

		return $html;
	}
}
