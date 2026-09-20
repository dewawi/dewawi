<?php

class Zend_View_Helper_RenderMenuItems extends Zend_View_Helper_Abstract
{
	public function RenderMenuItems($items, int $parentId = 0): string
	{
		if ($items instanceof Zend_Db_Table_Rowset_Abstract) {
			$items = iterator_to_array($items, false);
		}

		$html = '';

		foreach ($items as $item) {
			if ((int)$item->parentid !== $parentId) {
				continue;
			}

			$children = $this->RenderMenuItems($items, (int)$item->id);

			$url = '';

			if ((int)$item->categoryid > 0) {
				$url = (string)$this->view->SlugUrl('category', (int)$item->categoryid);
			} elseif ((int)$item->pageid > 0) {
				$url = (string)$this->view->SlugUrl('page', (int)$item->pageid);
			}

			if ($url === '' && $children === '') {
				continue;
			}

			$html .= '<li class="nav-item">';
			$html .= '<a class="nav-link" href="' . $this->view->escape($url !== '' ? $url : '#') . '">' . $this->view->escape($item->title) . '</a>';

			if ($children !== '') {
				$html .= '<ul class="submenu">' . $children . '</ul>';
			}

			$html .= '</li>';
		}

		return $html;
	}
}
