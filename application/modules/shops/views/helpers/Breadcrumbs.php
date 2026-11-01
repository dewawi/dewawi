<?php

require_once dirname(__FILE__) . '/SiteMetadata.php';

class Zend_View_Helper_Breadcrumbs extends Zend_View_Helper_Abstract
{
	public function Breadcrumbs(): string
	{
		$items = $this->getItems();
		if (count($items) < 2) return '';

		$html = '<section id="breadcrumb"><div class="container"><nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">';
		$last = count($items) - 1;

		foreach ($items as $index => $item) {
			$title = $this->view->escape($item['title']);

			if ($index === $last) {
				$html .= '<li class="breadcrumb-item active" aria-current="page">' . $title . '</li>';
			} elseif ($item['url'] !== null) {
				$html .= '<li class="breadcrumb-item"><a href="' . $this->view->escape($item['url']) . '">' . $title . '</a></li>';
			} else {
				$html .= '<li class="breadcrumb-item">' . $title . '</li>';
			}
		}

		$html .= '</ol></nav></div></section>';
		if (!($this->view->siteContext ?? null) instanceof DEEC_Site_Context) return $html;

		$list = [];
		$metadata = new Zend_View_Helper_SiteMetadata();
		$metadata->setView($this->view);
		$completeList = true;

		foreach ($items as $item) {
			$url = $item['url'] !== null ? $metadata->absoluteUrl($item['url']) : null;
			if ($url === null) {
				$completeList = false;
				break;
			}

			$list[] = [
				'@type' => 'ListItem',
				'position' => count($list) + 1,
				'name' => $item['title'],
				'item' => $url,
			];
		}

		if ($completeList && count($list) > 1) {
			$html .= $metadata->jsonLd([
				'@context' => 'https://schema.org',
				'@type' => 'BreadcrumbList',
				'itemListElement' => $list,
			]);
		}

		return $html;
	}

	public function getItems(): array
	{
		$items = [[
			'title' => (string)$this->view->translate('SHOPS_HOME'),
			'url' => (string)$this->view->url([], 'shop_home'),
		]];
		$controller = (string)($this->view->controller ?? '');

		if ($controller === 'category' && isset($this->view->category)) {
			foreach ($this->categoryParents($this->view->category) as $category) $items[] = $this->entityItem('category', $category);
			$items[] = $this->entityItem('category', $this->view->category);
		} elseif ($controller === 'item' && isset($this->view->item, $this->view->category)) {
			foreach ($this->categoryParents($this->view->category) as $category) $items[] = $this->entityItem('category', $category);
			$items[] = $this->entityItem('category', $this->view->category);
			$items[] = $this->entityItem('item', $this->view->item);
		} elseif ($controller === 'page' && isset($this->view->page)) {
			foreach ($this->pageParents($this->view->page) as $page) $items[] = $this->entityItem('page', $page);
			$items[] = $this->entityItem('page', $this->view->page);
		} elseif ($controller === 'tag' && isset($this->view->tag)) {
			$items[] = $this->entityItem('tag', $this->view->tag);
		} else {
			$items[] = [
				'title' => $this->controllerTitle($controller),
				'url' => $this->view->canonicalPath ?? null,
			];
		}

		return array_values(array_filter($items, static function(array $item): bool {
			return trim((string)$item['title']) !== '';
		}));
	}

	protected function entityItem(string $controller, $entity): array
	{
		return [
			'title' => trim((string)($entity['title'] ?? '')),
			'url' => $this->view->SlugUrl($controller, (int)($entity['id'] ?? 0)),
		];
	}

	protected function categoryParents($category): array
	{
		return $this->parents($category, is_array($this->view->categories ?? null) ? $this->view->categories : []);
	}

	protected function pageParents($page): array
	{
		$pages = [];
		foreach ((array)($this->view->pages ?? []) as $item) $pages[(int)$item['id']] = $item;

		return $this->parents($page, $pages);
	}

	protected function parents($entity, array $entities): array
	{
		$parents = [];
		$visited = [(int)($entity['id'] ?? 0) => true];
		$parentId = (int)($entity['parentid'] ?? 0);

		while ($parentId > 0 && isset($entities[$parentId]) && !isset($visited[$parentId])) {
			$parent = $entities[$parentId];
			array_unshift($parents, $parent);
			$visited[$parentId] = true;
			$parentId = (int)($parent['parentid'] ?? 0);
		}

		return $parents;
	}

	protected function controllerTitle(string $controller): string
	{
		switch ($controller) {
			case 'contact': return (string)$this->view->translate('SHOPS_CONTACT_FORM');
			case 'cart': return (string)$this->view->translate('SHOPS_SHOPPING_CART');
			case 'checkout': return (string)$this->view->translate('SHOPS_ORDER_FORM');
			case 'search': return (string)$this->view->translate('SHOPS_SEARCH');
		}

		return $controller;
	}
}
