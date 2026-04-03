<?php
/**
 * Class inserts necessary code for Menu
 */
class Zend_View_Helper_MainMenu extends Zend_View_Helper_Abstract
{
	public function MainMenu()
	{
		$menu = $this->getMenuConfig();

		//echo $this->renderMobileDrawer($menu);
		echo $this->renderDesktopTopbar($menu);
	}

	protected function getMenuConfig(): array
	{
		return [
			[
				'type' => 'link',
				'label' => 'CONTROL_PANEL',
				'module' => 'index',
				'controller' => 'index',
				'action' => 'index',
				'active' => function () {
					return $this->isActive('index', 'index');
				},
			],
			[
				'type' => 'group',
				'label' => 'MENU_CONTACTS',
				'active' => function () {
					return in_array($this->view->controller, ['contact', 'email', 'download'], true);
				},
				'children' => [
					[
						'label' => 'MENU_CONTACTS',
						'module' => 'contacts',
						'controller' => 'contact',
						'action' => 'index',
					],
					[
						'label' => 'MENU_EMAILS',
						'module' => 'contacts',
						'controller' => 'email',
						'action' => 'index',
					],
					[
						'label' => 'MENU_DOWNLOADS',
						'module' => 'contacts',
						'controller' => 'download',
						'action' => 'index',
					],
				],
			],
			[
				'type' => 'group',
				'label' => 'MENU_ITEMS',
				'active' => function () {
					return in_array($this->view->controller, ['item', 'attribute', 'option', 'itemlist', 'inventory', 'ledger'], true);
				},
				'children' => [
					[
						'label' => 'MENU_ITEMS',
						'module' => 'items',
						'controller' => 'item',
						'action' => 'index',
					],
					[
						'label' => 'MENU_ITEM_LISTS',
						'module' => 'items',
						'controller' => 'itemlist',
						'action' => 'index',
					],
					[
						'label' => 'MENU_INVENTORY',
						'module' => 'items',
						'controller' => 'inventory',
						'action' => 'index',
					],
					[
						'label' => 'MENU_LEDGER',
						'module' => 'items',
						'controller' => 'ledger',
						'action' => 'index',
					],
				],
			],
			[
				'type' => 'link',
				'label' => 'MENU_TASKS',
				'module' => 'tasks',
				'controller' => 'task',
				'action' => 'index',
				'active' => function () {
					return $this->isActive('tasks', 'task');
				},
			],
			[
				'type' => 'link',
				'label' => 'MENU_PROCESSES',
				'module' => 'processes',
				'controller' => 'process',
				'action' => 'index',
				'active' => function () {
					return $this->isActive('processes', 'process');
				},
			],
			[
				'type' => 'group',
				'label' => 'MENU_SALES',
				'active' => function () {
					return in_array($this->view->controller, ['quote', 'salesorder', 'deliveryorder'], true);
				},
				'children' => [
					[
						'label' => 'MENU_QUOTES',
						'module' => 'sales',
						'controller' => 'quote',
						'action' => 'index',
					],
					[
						'label' => 'MENU_SALES_ORDERS',
						'module' => 'sales',
						'controller' => 'salesorder',
						'action' => 'index',
					],
					[
						'label' => 'MENU_DELIVERY_ORDERS',
						'module' => 'sales',
						'controller' => 'deliveryorder',
						'action' => 'index',
					],
				],
			],
			[
				'type' => 'group',
				'label' => 'MENU_PURCHASES',
				'active' => function () {
					return in_array($this->view->controller, ['quoterequest', 'purchaseorder'], true);
				},
				'children' => [
					[
						'label' => 'MENU_QUOTE_REQUESTS',
						'module' => 'purchases',
						'controller' => 'quoterequest',
						'action' => 'index',
					],
					[
						'label' => 'MENU_PURCHASE_ORDERS',
						'module' => 'purchases',
						'controller' => 'purchaseorder',
						'action' => 'index',
					],
				],
			],
			[
				'type' => 'group',
				'label' => 'MENU_INVOICES',
				'title' => 'Abrechnung',
				'active' => function () {
					return in_array($this->view->controller, ['invoice', 'creditnote', 'reminder'], true);
				},
				'children' => [
					[
						'label' => 'MENU_INVOICES',
						'module' => 'sales',
						'controller' => 'invoice',
						'action' => 'index',
					],
					[
						'label' => 'MENU_CREDIT_NOTES',
						'module' => 'sales',
						'controller' => 'creditnote',
						'action' => 'index',
					],
					[
						'label' => 'MENU_REMINDERS',
						'module' => 'sales',
						'controller' => 'reminder',
						'action' => 'index',
					],
				],
			],
			[
				'type' => 'link',
				'label' => 'MENU_PRICE_RULES',
				'module' => 'items',
				'controller' => 'pricerule',
				'action' => 'index',
				'active' => function () {
					return $this->isActive('items', 'pricerule');
				},
			],
			[
				'type' => 'group',
				'label' => 'MENU_STATISTICS',
				'active' => function () {
					return $this->view->module === 'statistics';
				},
				'children' => [
					[
						'label' => 'STATISTICS_TURNOVER',
						'module' => 'statistics',
						'controller' => 'turnover',
						'action' => 'index',
					],
					[
						'label' => 'STATISTICS_CUSTOMER',
						'module' => 'statistics',
						'controller' => 'customer',
						'action' => 'index',
					],
					[
						'label' => 'STATISTICS_QUOTE',
						'module' => 'statistics',
						'controller' => 'quote',
						'action' => 'index',
					],
				],
			],
		];
	}

	protected function isActive(string $module, string $controller): bool
	{
		return ($this->view->module === $module && $this->view->controller === $controller);
	}

	protected function url(array $item): string
	{
		return $this->view->url([
			'module' => $item['module'],
			'controller' => $item['controller'],
			'action' => $item['action'] ?? 'index',
			'id' => null,
		]);
	}

	protected function t(string $key): string
	{
		return $this->view->translate($key);
	}

	protected function getItemTitle(array $item): string
	{
		if (!empty($item['title'])) {
			return (string)$item['title'];
		}

		return $this->t((string)$item['label']);
	}

	protected function isItemActive(array $item): bool
	{
		if (isset($item['active']) && is_callable($item['active'])) {
			return (bool)call_user_func($item['active']);
		}

		if (($item['type'] ?? '') === 'link') {
			return $this->isActive((string)$item['module'], (string)$item['controller']);
		}

		return false;
	}

	protected function renderMobileDrawer(array $menu): string
	{
		$html = '';
		$html .= '<div class="dw-mobile-nav">';
		$html .= '<input id="doc-drawer-checkbox" class="drawer" value="On" type="checkbox">';
		$html .= '<label for="doc-drawer-checkbox" class="dw-mobile-nav__toggle dw-btn">Menü</label>';
		$html .= '<div class="dw-drawer-backdrop"></div>';
		$html .= '<nav id="nav-drawer" class="dw-drawer" aria-label="Mobile navigation">';
		$html .= '<div class="dw-drawer__header">';
		$html .= '<h3 class="dw-drawer__title">Menü</h3>';
		$html .= '<label for="doc-drawer-checkbox" class="dw-drawer__close" aria-label="Menü schließen">&times;</label>';
		$html .= '</div>';
		$html .= '<div class="dw-drawer__body">';

		foreach ($menu as $item) {
			if (($item['type'] ?? '') === 'link') {
				$html .= '<a class="dw-drawer__link'.($this->isItemActive($item) ? ' is-active' : '').'" href="'.$this->url($item).'">';
				$html .= htmlspecialchars($this->getItemTitle($item));
				$html .= '</a>';
				continue;
			}

			$html .= '<div class="dw-drawer__section">';
			$html .= '<div class="dw-drawer__section-title'.($this->isItemActive($item) ? ' is-active' : '').'">';
			$html .= htmlspecialchars($this->getItemTitle($item));
			$html .= '</div>';

			foreach (($item['children'] ?? []) as $child) {
				$isActive = $this->isActive((string)$child['module'], (string)$child['controller']);
				$html .= '<a class="dw-drawer__sublink'.($isActive ? ' is-active' : '').'" href="'.$this->url($child).'">';
				$html .= htmlspecialchars($this->t((string)$child['label']));
				$html .= '</a>';
			}

			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</nav>';
		$html .= '</div>';

		return $html;
	}

	protected function renderDesktopTopbar(array $menu): string
	{
		$html = '';
		$html .= '<div class="dw-topbar-wrap">';
		$html .= '<header class="dw-topbar" aria-label="Main navigation">';
		$html .= '<nav class="dw-topbar__nav">';

		foreach ($menu as $item) {
			if (($item['type'] ?? '') === 'link') {
				$html .= '<a class="dw-nav-link'.($this->isItemActive($item) ? ' is-active' : '').'" href="'.$this->url($item).'">';
				$html .= htmlspecialchars($this->getItemTitle($item));
				$html .= '</a>';
				continue;
			}

			$html .= '<div class="dw-nav-item">';
			$html .= '<span class="dw-nav-link'.($this->isItemActive($item) ? ' is-active' : '').'">';
			$html .= htmlspecialchars($this->getItemTitle($item));
			$html .= '</span>';
			$html .= '<div class="dw-nav-dropdown">';

			foreach (($item['children'] ?? []) as $child) {
				$isActive = $this->isActive((string)$child['module'], (string)$child['controller']);
				$html .= '<a class="dw-nav-dropdown__link'.($isActive ? ' is-active' : '').'" href="'.$this->url($child).'">';
				$html .= htmlspecialchars($this->t((string)$child['label']));
				$html .= '</a>';
			}

			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</nav>';
		$html .= '</header>';
		$html .= '</div>';

		return $html;
	}
}
