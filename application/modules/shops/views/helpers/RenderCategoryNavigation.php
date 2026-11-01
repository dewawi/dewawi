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
			$submenuId = 'dw-category-submenu-' . (int)$category['id'];

			$html .= '<li class="nav-item'.($children !== '' ? ' dw-submenu-item' : '').'">';
			$html .= '<a class="nav-link" href="' . $this->view->escape($url) . '">' . $this->view->escape((string)$category['title']) . '</a>';
			if ($children !== '') {
				$html .= $this->submenuToggle($submenuId, (string)$category['title']);
				$html .= '<ul class="submenu" id="'.$submenuId.'" hidden>' . $children . '</ul>';
			}
			$html .= '</li>';
		}

		if (!$otherCategories) return $html;

		$otherHtml = '';

		foreach ($otherCategories as $category) {
			$url = $this->view->SlugUrl('category', (int)$category['id']);
			if (!$url) continue;

			$children = $this->view->RenderSubCategory($categories, (int)$category['id']);
			$submenuId = 'dw-category-submenu-' . (int)$category['id'];

			$otherHtml .= '<li class="right-edge'.($children !== '' ? ' dw-submenu-item' : '').'">';
			$otherHtml .= '<a href="' . $this->view->escape($url) . '">' . $this->view->escape((string)$category['title']) . '</a>';
			if ($children !== '') {
				$otherHtml .= $this->submenuToggle($submenuId, (string)$category['title']);
				$otherHtml .= '<ul class="submenu" id="'.$submenuId.'" hidden>' . $children . '</ul>';
			}
			$otherHtml .= '</li>';
		}

		if ($otherHtml === '') return $html;

		$html .= '<li class="nav-item dw-submenu-item">';
		$html .= '<button class="nav-link dw-submenu-toggle dw-submenu-toggle--root" type="button" aria-expanded="false" aria-controls="dw-category-submenu-more">' . $this->view->escape($this->view->translate('SHOPS_OTHER_CATEGORIES')) . '</button>';
		$html .= '<ul class="submenu" id="dw-category-submenu-more" hidden>' . $otherHtml . '</ul>';
		$html .= '</li>';

		return $html;
	}

	protected function submenuToggle(string $submenuId, string $title): string
	{
		$label = sprintf($this->view->translate('SHOPS_SUBMENU_TOGGLE'), $title);
		return '<button class="dw-submenu-toggle" type="button" aria-expanded="false" aria-controls="'.$submenuId.'"><span aria-hidden="true">&#9662;</span><span class="sr-only">'.$this->view->escape($label).'</span></button>';
	}
}
