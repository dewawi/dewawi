<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_RenderCategory extends Zend_View_Helper_Abstract
{
	public function RenderCategory($category) {
		$output = '';
		$output .= '<div class="col-md-3 mb-3 px-2 d-flex align-items-stretch">';
		$output .= '	<div class="card">';
		$output .= '		<a href="' . $this->view->url([], 'category_' . $category['id']) . '">';
		if (isset($this->view->images['categories'][$category['id']][0])) {
			$categoryImage = $this->view->images['categories'][$category['id']][0]->url;
			$output .= '			<a href="' . $this->view->url([], 'category_' . $category['id']) . '">';
			$output .= '				<img src="' . $this->view->baseUrl() . '/media/category/' . $categoryImage . '" class="card-img-top" alt="Category Image">';
			$output .= '			</a>';
		}
		$output .= '		</a>';
		if (isset($this->view->images['categories'][$category['id']][0])) {
			if ($this->view->images['categories'][$category['id']][0]->title || $category['subtitle']) {
				$subtitle = $this->view->images['categories'][$category['id']][0]->title ? $this->view->images['categories'][$category['id']][0]->title : $category['subtitle'];
				$output .= '		<div class="category-text">';
				$output .= '			<h3 class="text-right text-white">' . $subtitle . '</h3>';
				$output .= '		</div>';
			}
		}
		$output .= '		<div class="card-body px-3">';
		$output .= '			<a href="' . $this->view->url([], 'category_' . $category['id']) . '">';
		$output .= '				<h5 class="card-title">' . $category['title'] . '</h5>';
		$output .= '			</a>';
		if ($category['subtitle']) {
			$output .= '			<h6 class="card-title">' . $category['subtitle'] . '</h6>';
		}
		$output .= '			<p class="card-text">' . $category['minidescription'] . '</p>';
		$output .= '		</div>';
		$output .= '	</div>';
		$output .= '</div>';
		return $output;
	}
}
