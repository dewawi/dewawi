<?php

class Zend_View_Helper_HistoryToolbar extends Zend_View_Helper_Abstract
{
	public function historyToolbar(
		$state,
		?string $controller = null,
		int $id = 0,
		string $module = '',
		bool $completed = false,
		bool $cancelled = false
	): string {
		$actions = $this->resolveActions(
			(string) $state,
			$controller,
			$completed,
			$cancelled
		);

		if (empty($actions)) {
			return '';
		}

		$controller = (string) $controller;

		$html = '';

		foreach ($actions as $action) {
			$action = trim((string) $action);
			if ($action === '') {
				continue;
			}

			$html .= '<button'
				. ' type="button"'
				. ' class="' . htmlspecialchars($action . ' nolabel js-' . $action, ENT_QUOTES, 'UTF-8') . '"'
				. ' data-id="' . htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') . '"'
				. ' data-module="' . htmlspecialchars($module, ENT_QUOTES, 'UTF-8') . '"'
				. ' data-controller="' . htmlspecialchars($controller, ENT_QUOTES, 'UTF-8') . '"'
				. '></button>';
		}

		return $html;
	}

	protected function resolveActions(
		string $state,
		?string $controller = null,
		bool $completed = false,
		bool $cancelled = false
	): array {
		switch ((string) $controller) {
			case 'process':
				if ($completed || $cancelled) {
					return ['view', 'copy'];
				}
				return ['edit', 'copy'];

			case 'quote':
			case 'salesorder':
			case 'invoice':
			case 'deliveryorder':
			case 'creditnote':
			case 'quoterequest':
			case 'purchaseorder':
			case 'reminder':
			default:
				if (in_array($state, ['105', '106'], true)) {
					return ['view', 'copy', 'pdf'];
				}
				return ['edit', 'copy', 'pdf'];
		}
	}
}
