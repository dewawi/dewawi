<?php

class Zend_View_Helper_HistoryToolbar extends Zend_View_Helper_Abstract
{
	public function historyToolbar($state, ?string $type = null, array $opts = []): string
	{
		$actions = $this->resolveActions((string)$state, $type, $opts);

		if (empty($actions)) {
			return '';
		}

		$html = '';

		foreach ($actions as $action) {
			$action = trim((string)$action);
			if ($action === '') {
				continue;
			}

			$html .= '<button type="button" class="'
				. htmlspecialchars($action, ENT_QUOTES, 'UTF-8')
				. ' nolabel"></button>';
		}

		return $html;
	}

	protected function resolveActions(string $state, ?string $type = null, array $opts = []): array
	{
		switch ((string)$type) {
			case 'process':
				if (!empty($opts['completed']) || !empty($opts['cancelled'])) {
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
