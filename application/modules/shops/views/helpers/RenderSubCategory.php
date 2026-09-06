<?php

class Zend_View_Helper_RenderSubCategory extends Zend_View_Helper_Abstract
{
	public function RenderSubCategory($categories, $parentId = 0)
	{
		$output = '';

		foreach ($categories as $subcategory) {
			if ($subcategory['parentid'] == $parentId) {
				$url = $this->view->SlugUrl('category', $subcategory['id']);

				if (!$url) {
					continue;
				}

				$output .= '<li>';
				$output .= '<a href="' . $url . '">' . $subcategory['title'] . '</a>';

				$childCategories = $this->RenderSubCategory($categories, $subcategory['id']);
				if (!empty($childCategories)) {
					$output .= '<ul class="submenu">';
					$output .= $childCategories;
					$output .= '</ul>';
				}

				$output .= '</li>';
			}
		}

		return $output;
	}
}
