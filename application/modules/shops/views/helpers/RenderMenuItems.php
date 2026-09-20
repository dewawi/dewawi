<?php

class Zend_View_Helper_RenderMenuItems extends Zend_View_Helper_Abstract
{
	public function RenderMenuItems($items, int $parentId = 0, string $mode = 'default'): string
	{
		$items = $this->normalizeItems($items);

		if ($mode === 'mega' && $parentId === 0) return $this->renderMega($items);

		return $this->renderTree($items, $parentId);
	}

	protected function renderMega(array $items): string
	{
		$html = '';

		foreach ($this->getChildren($items, 0) as $item) {
			$children = $this->getChildren($items, (int)$item['id']);
			$url = $this->resolveUrl($item);

			if (!$children) {
				if ($url === '') continue;

				$html .= '<li class="nav-item">';
				$html .= '<a class="nav-link" href="'.$this->view->escape($url).'">'.$this->view->escape($item['title']).'</a>';
				$html .= '</li>';
				continue;
			}

			$html .= '<li class="nav-item dw-mega-nav-item">';
			$html .= '<button class="nav-link dw-mega-toggle" type="button" data-mega-toggle aria-expanded="false">'.$this->view->escape($item['title']).'</button>';
			$html .= $this->renderMegaPanel($items, $children);
			$html .= '</li>';
		}

		return $html;
	}

	protected function renderMegaPanel(array $items, array $children): string
	{
		$groups = [];
		$links = [];
		$actions = [];

		foreach ($children as $item) {
			$variant = (string)($item['variant'] ?? 'default');

			if ($variant === 'action') {
				$actions[] = $item;
			} elseif ($this->getChildren($items, (int)$item['id'])) {
				$groups[] = $item;
			} else {
				$links[] = $item;
			}
		}

		$html = '<div class="dw-mega-menu"><div class="container"><div class="dw-mega-menu__grid">';

		foreach ($groups as $item) {
			$url = $this->resolveUrl($item);

			$html .= '<div class="dw-mega-menu__column">';

			if ($url !== '') {
				$html .= '<a class="dw-mega-menu__heading" href="'.$this->view->escape($url).'">'.$this->view->escape($item['title']).'</a>';
			} else {
				$html .= '<div class="dw-mega-menu__heading">'.$this->view->escape($item['title']).'</div>';
			}

			$html .= $this->renderMegaLinks($items, (int)$item['id']);
			$html .= '</div>';
		}

		if ($links) {
			$html .= '<div class="dw-mega-menu__flat">';

			foreach ($links as $item) {
				$url = $this->resolveUrl($item);

				if ($url === '') continue;

				$html .= '<a href="'.$this->view->escape($url).'">'.$this->view->escape($item['title']).'</a>';
			}

			$html .= '</div>';
		}

		$html .= '</div>';

		if ($actions) {
			$html .= '<div class="dw-mega-menu__actions">';

			foreach ($actions as $item) {
				$url = $this->resolveUrl($item);

				if ($url === '') continue;

				$html .= '<a href="'.$this->view->escape($url).'">'.$this->view->escape($item['title']).'</a>';
			}

			$html .= '</div>';
		}

		$html .= '</div></div>';

		return $html;
	}

	protected function renderMegaLinks(array $items, int $parentId): string
	{
		$html = '';

		foreach ($this->getChildren($items, $parentId) as $item) {
			$url = $this->resolveUrl($item);
			$children = $this->getChildren($items, (int)$item['id']);

			if ($url === '' && !$children) continue;

			$html .= '<li>';

			if ($url !== '') {
				$html .= '<a href="'.$this->view->escape($url).'">'.$this->view->escape($item['title']).'</a>';
			} else {
				$html .= '<span>'.$this->view->escape($item['title']).'</span>';
			}

			if ($children) $html .= $this->renderMegaLinks($items, (int)$item['id']);

			$html .= '</li>';
		}

		return $html !== '' ? '<ul class="dw-mega-menu__links">'.$html.'</ul>' : '';
	}

	protected function renderTree(array $items, int $parentId): string
	{
		$html = '';

		foreach ($this->getChildren($items, $parentId) as $item) {
			$children = $this->renderTree($items, (int)$item['id']);
			$url = $this->resolveUrl($item);

			if ($url === '' && $children === '') continue;

			$variant = in_array(($item['variant'] ?? ''), ['action', 'highlight'], true) ? (string)$item['variant'] : 'default';
			$html .= '<li class="nav-item nav-item--'.$variant.'">';

			if ($url !== '') {
				$html .= '<a class="nav-link" href="'.$this->view->escape($url).'">'.$this->view->escape($item['title']).'</a>';
			} else {
				$html .= '<span class="nav-link">'.$this->view->escape($item['title']).'</span>';
			}

			if ($children !== '') $html .= '<ul class="submenu">'.$children.'</ul>';

			$html .= '</li>';
		}

		return $html;
	}

	protected function resolveUrl(array $item): string
	{
		if ((int)($item['categoryid'] ?? 0) > 0) return (string)$this->view->SlugUrl('category', (int)$item['categoryid']);
		if ((int)($item['pageid'] ?? 0) > 0) return (string)$this->view->SlugUrl('page', (int)$item['pageid']);

		return '';
	}

	protected function getChildren(array $items, int $parentId): array
	{
		return array_values(array_filter($items, static function ($item) use ($parentId) {
			return (int)($item['parentid'] ?? 0) === $parentId;
		}));
	}

	protected function normalizeItems($items): array
	{
		$result = [];

		foreach ($items as $item) {
			$result[] = is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
		}

		return $result;
	}
}
