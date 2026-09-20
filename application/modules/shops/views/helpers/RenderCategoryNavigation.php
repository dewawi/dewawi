<?php

class Zend_View_Helper_RenderCategoryNavigation extends Zend_View_Helper_Abstract
{
	public function RenderCategoryNavigation(array $categories, int $limit = 5): string
	{
		if (!$categories) return '';

		$mainCategories = array_values(array_filter($categories, static function($category) {
			return (int)$category['parentid'] === 0;
		}));

		$firstCategories = array_slice($mainCategories, 0, $limit);
		$otherCategories = array_slice($mainCategories, $limit);
		$html = '';

		foreach ($firstCategories as $category) {
			$url = $this->view->SlugUrl('category', (int)$category['id']);
			if (!$url) continue;

			$children = $this->view->RenderSubCategory($categories, (int)$category['id']);

			$html .= '<li class="nav-item">';
			$html .= '<a class="nav-link" href="' . $this->view->escape($url) . '">' . $this->view->escape((string)$category['title']) . '</a>';
			if ($children !== '') $html .= '<ul class="submenu">' . $children . '</ul>';
			$html .= '</li>';
		}

		if (!$otherCategories) return $html;

		$otherHtml = '';

		foreach ($otherCategories as $category) {
			$url = $this->view->SlugUrl('category', (int)$category['id']);
			if (!$url) continue;

			$children = $this->view->RenderSubCategory($categories, (int)$category['id']);

			$otherHtml .= '<li class="right-edge">';
			$otherHtml .= '<a href="' . $this->view->escape($url) . '">' . $this->view->escape((string)$category['title']) . '</a>';
			if ($children !== '') $otherHtml .= '<ul class="submenu">' . $children . '</ul>';
			$otherHtml .= '</li>';
		}

		if ($otherHtml === '') return $html;

		$html .= '<li class="nav-item">';
		$html .= '<a class="nav-link" href="#">' . $this->view->escape($this->view->translate('SHOPS_OTHER_CATEGORIES')) . '</a>';
		$html .= '<ul class="submenu">' . $otherHtml . '</ul>';
		$html .= '</li>';

		return $html;
	}
}
