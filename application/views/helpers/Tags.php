<?php

class Zend_View_Helper_Tags extends Zend_View_Helper_Abstract
{
	public function Tags(string $module, string $controller): string
	{
		$tags = $this->view->tags ?? null;

		if (empty($tags)) {
			$tags = $this->loadTags($module, $controller);
		}

		if (empty($tags)) {
			return '';
		}

		$html = '<h3>Tags</h3>';

		foreach ($tags as $id => $title) {
			$url = $this->view->url([
				'module' => $module,
				'controller' => $controller,
				'action' => 'index',
				'tagid' => $id,
			], null, true);

			$html .= '<a href="' . $this->escape($url) . '">'
				. $this->escape($this->view->translate($title))
				. '</a> ';
		}

		return $html;
	}

	private function loadTags(string $module, string $controller): array
	{
		$class = ucfirst($module) . '_Model_Get';

		if (!class_exists($class)) {
			return [];
		}

		$get = new $class();

		if (!method_exists($get, 'tags')) {
			return [];
		}

		$tags = $get->tags($module, $controller);

		if (!is_array($tags)) {
			return [];
		}

		$this->view->tags = $tags;

		return $tags;
	}

	private function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}
