<?php

class Zend_View_Helper_TreeMenu extends Zend_View_Helper_Abstract
{
	/**
	 * @param array $categories flat array: [id => ['id'=>..,'parentid'=>..,'title'=>..], ...]
	 * @param array $cfg optional config
	 * - id: ul id (default treemenu)
	 * - class: ul class (default treeview)
	 * - linkBase: href prefix if you want links (default '#')
	 * - linkParam: query param name for category id (default 'catid')
	 * - selectedId: mark active category (optional)
	 * - includeControls: show expand/collapse links (default true)
	 */
	public function TreeMenu(array $cfg = []): string
	{
		if (empty($categories = $this->view->categories)) {
			return '';
		}

		$menuId = $cfg['id'] ?? 'treemenu';
		$menuCls = $cfg['class'] ?? 'treeview';
		$controls = array_key_exists('includeControls', $cfg) ? (bool)$cfg['includeControls'] : true;

		// group once (O(n))
		$byParent = $this->groupByParent($categories);

		$html = '';

		if ($controls) {
			$html .= '<p>'
				. '<a href="javascript:ddtreemenu.flatten(\'' . $this->escJs($menuId) . '\', \'expand\')">'
				. $this->view->translate('CATEGORIES_EXPAND_ALL')
				. '</a> | '
				. '<a href="javascript:ddtreemenu.flatten(\'' . $this->escJs($menuId) . '\', \'contact\')">'
				. $this->view->translate('CATEGORIES_COLLAPSE_ALL')
				. '</a>'
				. '</p>';
		}

		$html .= $this->renderBranch($byParent, 0, [
			'menuId' => $menuId,
			'menuCls' => $menuCls,
			'linkBase' => $cfg['linkBase'] ?? '#',
			'linkParam' => $cfg['linkParam'] ?? 'catid',
			'selectedId' => isset($cfg['selectedId']) ? (int)$cfg['selectedId'] : null,
			'isRoot' => true,
		]);

		$html .= '<script type="text/javascript">ddtreemenu.createTree("'
			. $this->escJs($menuId)
			. '", true);</script>';

		return $html;
	}

	private function renderBranch(array $byParent, int $parentId, array $cfg): string
	{
		if (empty($byParent[$parentId])) {
			return '';
		}

		$isRoot = !empty($cfg['isRoot']);
		$ulId = $isRoot ? ' id="' . $this->escAttr($cfg['menuId']) . '"' : '';
		$ulCls = $isRoot ? ' class="' . $this->escAttr($cfg['menuCls']) . '"' : '';

		$html = '<ul' . $ulId . $ulCls . '>';

		foreach ($byParent[$parentId] as $id => $cat) {
			$title = $cat['title'] ?? '';
			$hasChildren = !empty($byParent[(int)$id]);

			$liClass = [];
			if ($cfg['selectedId'] !== null && (int)$id === (int)$cfg['selectedId']) {
				$liClass[] = 'active';
			}
			if ($hasChildren) {
				$liClass[] = 'has-children';
			}

			$liAttr = $liClass ? ' class="' . $this->escAttr(implode(' ', $liClass)) . '"' : '';

			// link (wenn du später echtes Filtern willst: linkBase + ?catid=ID)
			$href = (string)$cfg['linkBase'];
			if ($href !== '#' && $href !== '') {
				$sep = (strpos($href, '?') === false) ? '?' : '&';
				$href = $href . $sep . rawurlencode((string)$cfg['linkParam']) . '=' . rawurlencode((string)$id);
			}

			$html .= '<li' . $liAttr . '>'
				. '<a href="' . $this->escAttr($href) . '" data-id="' . $this->escAttr((string)$id) . '">'
				. $this->escHtml($title)
				. '</a>';

			if ($hasChildren) {
				$html .= $this->renderBranch($byParent, (int)$id, array_merge($cfg, ['isRoot' => false]));
			}

			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}

	private function groupByParent(array $categories): array
	{
		$byParent = [];

		foreach ($categories as $id => $cat) {
			$pid = (int)($cat['parentid'] ?? 0);
			if (!isset($byParent[$pid])) $byParent[$pid] = [];
			$byParent[$pid][$id] = $cat;
		}

		return $byParent;
	}

	private function escHtml(string $s): string
	{
		return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
	}

	private function escAttr(string $s): string
	{
		return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
	}

	private function escJs(string $s): string
	{
		// minimal js string escape for ids
		return str_replace(["\\", "\"", "'"], ["\\\\", "\\\"", "\\'"], $s);
	}
}
