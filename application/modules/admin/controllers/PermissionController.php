<?php

class Admin_PermissionController extends DEEC_Controller_AdminAction
{
	protected function beforeEditSave(array $values, array $row): array
	{
		if (count($values) !== 1) {
			throw new InvalidArgumentException(
				'Invalid permission request'
			);
		}

		$field = (string)array_key_first($values);
		$enabled = !empty($values[$field]);

		$parts = explode('__', $field, 3);

		if (count($parts) !== 3) {
			throw new InvalidArgumentException(
				'Invalid permission field'
			);
		}

		[$module, $controller, $permission] = $parts;

		if (
			!in_array($module, $this->getPermissionModules(), true)
			|| $controller === ''
			|| !in_array(
				$permission,
				['add', 'edit', 'view', 'delete'],
				true
			)
		) {
			throw new InvalidArgumentException(
				'Invalid permission request'
			);
		}

		$modulePermissions = json_decode(
			(string)($row[$module] ?? ''),
			true
		);

		if (!is_array($modulePermissions)) {
			$modulePermissions = [];
		}

		$controllerPermissions =
			$modulePermissions[$controller] ?? [];

		if (!is_array($controllerPermissions)) {
			$controllerPermissions = [];
		}

		if ($enabled) {
			$controllerPermissions[] = $permission;

			$controllerPermissions = array_values(
				array_unique($controllerPermissions)
			);
		} else {
			$controllerPermissions = array_values(
				array_diff(
					$controllerPermissions,
					[$permission]
				)
			);
		}

		$modulePermissions[$controller] =
			$controllerPermissions;

		return [
			$module => json_encode($modulePermissions),
		];
	}

	protected function getPermissionModules(): array
	{
		return [
			'default',
			'calendar',
			'contacts',
			'items',
			'processes',
			'purchases',
			'sales',
			'statistics',
		];
	}
}
