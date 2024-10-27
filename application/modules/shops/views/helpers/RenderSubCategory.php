<?php

class Zend_View_Helper_RenderSubCategory extends Zend_View_Helper_Abstract
{
	public function RenderSubCategory($categories, $parentId = 0)
	{
		$output = '';

		// Get the router instance from the FrontController
		$router = Zend_Controller_Front::getInstance()->getRouter();

		foreach ($categories as $subcategory) {
			if ($subcategory['parentid'] == $parentId) {
				// Construct the route name
				$routeName = 'category_' . $subcategory['id'];

				// Check if the route exists
				if ($router->hasRoute($routeName)) {
					$output .= '<li>';
					$output .= '<a href="' . $this->view->url([], $routeName) . '">' . $subcategory['title'] . '</a>';

					// Recursively render subcategories if this category has children
					$childCategories = $this->RenderSubCategory($categories, $subcategory['id']);
					if (!empty($childCategories)) {
						$output .= '<ul class="submenu">';
						$output .= $childCategories;
						$output .= '</ul>';
					}

					$output .= '</li>';
				}
			}
		}

		return $output;
	}
}
