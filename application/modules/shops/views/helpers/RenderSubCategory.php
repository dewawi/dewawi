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
			$submenuId = 'dw-category-submenu-' . (int)$subcategory['id'];

			$html .= '<li'.($children !== '' ? ' class="dw-submenu-item"' : '').'>';
			$html .= '<a href="' . $this->view->escape($url) . '">' . $this->view->escape((string)$subcategory['title']) . '</a>';

			if ($children !== '') {
				$label = sprintf($this->view->translate('SHOPS_SUBMENU_TOGGLE'), (string)$subcategory['title']);
				$html .= '<button class="dw-submenu-toggle" type="button" aria-expanded="false" aria-controls="'.$submenuId.'"><span aria-hidden="true">&#9656;</span><span class="sr-only">'.$this->view->escape($label).'</span></button>';
				$html .= '<ul class="submenu" id="'.$submenuId.'" hidden>' . $children . '</ul>';
			}

			$html .= '</li>';
		}

		return $html;
	}
}
