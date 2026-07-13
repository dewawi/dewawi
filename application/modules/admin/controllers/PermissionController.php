<?php

class Admin_PermissionController extends DEEC_Controller_AdminAction
{
	protected function beforeEditSave(array $values, array $row): array {
		$request = $this->getRequest();

		$module = (string)$request->getPost('module', '');
		$controller = (string)$request->getPost('controller', '');
		$permission = (string)$request->getPost('element', '');

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

		$enabled = !empty($values[$permission]);

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
			'contacts',
			'items',
			'processes',
			'purchases',
			'sales',
			'statistics',
		];
	}
}
