<?php

class Zend_View_Helper_PageByType extends Zend_View_Helper_Abstract
{
	public function PageByType($type, $pages = null)
	{
		if ($pages === null && isset($this->view->pages)) {
			$pages = $this->view->pages;
		}

		if (!$pages) {
			return null;
		}

		foreach ($pages as $page) {
			$page = is_object($page) ? $page->toArray() : $page;

			if (($page['type'] ?? null) === $type) {
				return $page;
			}
		}

		return null;
	}
}
