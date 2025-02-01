<?php
/**
* Class inserts neccery code for Breadcrumbs	
*/
class Zend_View_Helper_Breadcrumbs extends Zend_View_Helper_Abstract{

	public function Breadcrumbs() {
		// Start the breadcrumb HTML
		$html = '<section id="breadcrumb" class="">
					<div class="container">
						<nav aria-label="breadcrumb">
							<ol class="breadcrumb mb-0">';

		// Add the Home link
		$html .= '<li class="breadcrumb-item">
					  <a href="' . $this->view->url([], 'shop') . '">' . $this->view->translate('SHOPS_HOME') . '</a>
				  </li>';

		// Check for different controllers and build the breadcrumbs dynamically
		if ($this->view->controller === 'category' && isset($this->view->category)) {
			// Get the current category and its parent categories
			$breadcrumbs = $this->CategoryBreadcrumbs($this->view->category, $this->view->categories);

			foreach ($breadcrumbs as $breadcrumb) {
				$html .= '<li class="breadcrumb-item">
							  <a href="' . $this->view->url(['id' => $breadcrumb['id']], 'category_' . $breadcrumb['id']) . '">'
							  . $this->view->escape($breadcrumb['title']) . '</a>
						  </li>';
			}
			$html .= '<li class="breadcrumb-item active" aria-current="page">'
				  . $this->view->escape($this->view->category['title']) . '</li>';

			// Get the current category and its parent categories
			$breadcrumbs = $this->CategoryBreadcrumbs($this->view->category, $this->view->categories);
		} elseif ($this->view->controller === 'item' && isset($this->view->item)) {
			// Get the current item and its parent categories
			$breadcrumbs = $this->CategoryBreadcrumbs($this->view->category, $this->view->categories);

			foreach ($breadcrumbs as $breadcrumb) {
				$html .= '<li class="breadcrumb-item">
							  <a href="' . $this->view->url(['id' => $breadcrumb['id']], 'category_' . $breadcrumb['id']) . '">'
							  . $this->view->escape($breadcrumb['title']) . '</a>
						  </li>';
			}
			$html .= '<li class="breadcrumb-item">
						  <a href="' . $this->view->url(['id' => $breadcrumb['id']], 'category_' . $this->view->category['id']) . '">'
						  . $this->view->escape($this->view->category['title']) . '</a>
					  </li>';
			$html .= '<li class="breadcrumb-item active" aria-current="page">'
				  . $this->view->escape($this->view->item['title']) . '</li>';

			// Get the current category and its parent categories
			$breadcrumbs = $this->CategoryBreadcrumbs($this->view->category, $this->view->categories);
		} elseif ($this->view->controller === 'tag' && isset($this->view->tag)) {
			$html .= '<li class="breadcrumb-item active" aria-current="page">'
				  . $this->view->escape($this->view->tag->title) . '</li>';
		} elseif ($this->view->controller === 'page' && isset($this->view->page)) {
			$html .= '<li class="breadcrumb-item active" aria-current="page">'
				  . $this->view->escape($this->view->page['title']) . '</li>';
		} elseif ($this->view->controller === 'contact') {
			$html .= '<li class="breadcrumb-item active" aria-current="page">'
				  . $this->view->translate('SHOPS_CONTACT_FORM') . '</li>';
		} elseif ($this->view->controller === 'cart') {
			$html .= '<li class="breadcrumb-item active" aria-current="page">'
				  . $this->view->translate('SHOPS_SHOPPING_CART') . '</li>';
		} elseif ($this->view->controller === 'checkout') {
			$html .= '<li class="breadcrumb-item active" aria-current="page">'
				  . $this->view->translate('SHOPS_ORDER_FORM') . '</li>';
		} else {
			$html .= '<li class="breadcrumb-item active" aria-current="page">'
				  . $this->view->escape($this->view->controller) . '</li>';
		}

		// Close the breadcrumb HTML
		$html .= '</ol>
						</nav>
					</div>
				  </section>';

		return $html;
	}

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
