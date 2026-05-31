<?php

class Admin_Model_List_Permissions extends DEEC_List
{
	protected function buildColumns()
	{
		$columns = [
			[
				'name' => 'id',
				'label' => 'ADMIN_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
		];

		foreach ($this->getPermissionModules() as $module) {
			$columns[] = [
				'name' => $module,
				'label' => strtoupper($module),
				'type' => 'callback',
				'class' => 'dw-col-' . $module,
				'callback' => [$this, 'renderPermissionModule'],
			];
		}

		return $columns;
	}

	protected function getPermissionModules(): array
	{
		return [
			'contacts',
			'items',
			'processes',
			'purchases',
			'sales',
			'statistics',
		];
	}

	public function renderPermissionModule($permission, array $column, DEEC_List $list): string
	{
		$module = (string)$column['name'];
		$controllers = $this->getFieldValue($permission, $module, []);

		if (!is_array($controllers) || empty($controllers)) {
			return '';
		}

		$html = [];

		foreach ($controllers as $controller => $actions) {
			$html[] = '<input type="hidden" name="module" class="module" value="' . $this->escapeAttr($module) . '">';
			$html[] = '<input type="hidden" name="controller" class="controller" value="' . $this->escapeAttr($controller) . '">';
			$html[] = '<h4>' . $this->escape($controller) . '</h4>';

			foreach (['add', 'edit', 'view', 'delete'] as $action) {
				$html[] = $this->renderActionCheckbox($permission, $module, $controller, $actions, $action);
			}
		}

		return implode('', $html);
	}

	protected function renderActionCheckbox($permission, string $module, string $controller, array $actions, string $action): string
	{
		$id = (int)$this->getFieldValue($permission, 'id');
		$checked = in_array($action, $actions, true);
		$disabled = $this->hasPermission('admin') ? '' : ' disabled="disabled"';

		return '<label class="dw-permission-action">'
			. '<input type="checkbox"'
			. ' class="editable"'
			. ' name="' . $this->escapeAttr($action) . '"'
			. ' data-name="' . $this->escapeAttr($action) . '"'
			. ' data-module="admin"'
			. ' data-controller="permission"'
			. ' data-id="' . $id . '"'
			. ' data-element="' . $this->escapeAttr($action) . '"'
			. ' data-permission-module="' . $this->escapeAttr($module) . '"'
			. ' data-permission-controller="' . $this->escapeAttr($controller) . '"'
			. ' value="1"'
			. ($checked ? ' checked="checked"' : '')
			. $disabled
			. ' /> '
			. $this->escape($action)
			. '</label>';
	}
}
