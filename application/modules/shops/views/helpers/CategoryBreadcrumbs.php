<?php

class Zend_View_Helper_CategoryBreadcrumbs extends Zend_View_Helper_Abstract
{
	public function CategoryBreadcrumbs($category, $categories)
	{
		$breadcrumbs = [];

		// Loop to find parent categories
		while ($category && $category['parentid'] != 0) {
			$parentCategory = $this->findCategoryById($category['parentid'], $categories);
			if ($parentCategory) {
				array_unshift($breadcrumbs, $parentCategory);
				$category = $parentCategory;
			} else {
				break;
			}
		}

		return $breadcrumbs;
	}

	// Helper function to find a category by ID in the categories array
	private function findCategoryById($id, $categories)
	{
		foreach ($categories as $category) {
			if ($category['id'] == $id) {
				return $category;
			}
		}

		return null; // Return null if the category is not found
	}
}
