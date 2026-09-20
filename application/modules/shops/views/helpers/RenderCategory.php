<?php

class Zend_View_Helper_RenderCategory extends Zend_View_Helper_Abstract
{
	public function RenderCategory(array $category): string
	{
		$url = (string)$this->view->SlugUrl('category', (int)$category['id']);
		$title = (string)($category['title'] ?? '');
		$subtitle = (string)($category['subtitle'] ?? '');
		$description = (string)($category['minidescription'] ?? '');
		$media = $this->view->images['categories'][$category['id']] ?? [];
		$image = null;

		foreach ($media as $item) {
			if (($item['type'] ?? '') === 'image') {
				$image = $item;
				break;
			}
		}

		$html = '<div class="col-md-3 mb-3 px-2 d-flex align-items-stretch">';
		$html .= '<div class="card">';

		if ($image) {
			$imageUrl = $this->view->baseUrl() . '/media/category/' . (string)$image['url'];
			$html .= '<a href="' . $this->view->escape($url) . '">';
			$html .= '<img src="' . $this->view->escape($imageUrl) . '" class="card-img-top" alt="' . $this->view->escape($title) . '">';
			$html .= '</a>';

			$imageTitle = trim((string)($image['title'] ?? ''));
			$imageSubtitle = $imageTitle !== '' ? $imageTitle : $subtitle;

			if ($imageSubtitle !== '') {
				$html .= '<div class="category-text">';
				$html .= '<h3 class="text-right text-white">' . $this->view->escape($imageSubtitle) . '</h3>';
				$html .= '</div>';
			}
		}

		$html .= '<div class="card-body px-3">';
		$html .= '<a href="' . $this->view->escape($url) . '"><h5 class="card-title">' . $this->view->escape($title) . '</h5></a>';

		if ($subtitle !== '') {
			$html .= '<h6 class="card-title">' . $this->view->escape($subtitle) . '</h6>';
		}

		if ($description !== '') {
			$html .= '<div class="card-text">' . $description . '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}
}
