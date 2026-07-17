<?php

class DEEC_Permission
{
	private const ACTIONS = [
		'add' => [
			'add',
			'copy',
			'generate',
			'send',
			'upload',
		],
		'edit' => [
			'edit',
			'import',
			'lock',
			'unlock',
			'keepalive',
			'pin',
			'apply',
			'sort',
			'save',
			'cancel',
			'state',
		],
		'view' => [
			'view',
			'get',
			'index',
			'search',
			'select',
			'history',
			'suggest',
			'export',
			'preview',
			'download',
		],
		'delete' => [
			'delete',
		],
	];

	private array $user;

	private ?array $permissions = null;

	public function __construct(array $user)
	{
		$this->user = $user;
	}

	public function hasRoutePermission(
		Zend_Controller_Request_Abstract $request
	): bool {
		$module = $request->getModuleName();
		$controller = $this->resolveController($request);
		$action = $this->resolveAction($request->getActionName());

		return $this->hasActionPermission(
			$module,
			$controller,
			$action
		);
	}

	public function hasActionPermission(
		string $module,
		string $controller,
		string $action
	): bool {
		$permission = $this->getPermissionForAction($action);

		if ($permission === null) {
			return false;
		}

		return $this->hasPermission(
			$module,
			$controller,
			$permission
		);
	}

	public function hasPermission(
		string $module,
		string $controller,
		string $permission
	): bool {
		$controllerPermissions = $this->getControllerPermissions(
			$module,
			$controller
		);

		return in_array(
			$permission,
			$controllerPermissions,
			true
		);
	}

	public function hasElementPermission(
		string $module,
		string $controller,
		string $action
	): bool {
		$requiredPermission = $this->getPermissionForAction($action);

		if ($requiredPermission === null) {
			return true;
		}

		return $this->hasPermission(
			$module,
			$controller,
			$requiredPermission
		);
	}

	public function hasModule(string $module): bool
	{
		return array_key_exists($module, $this->getPermissions());
	}

	public function hasController(
		string $module,
		string $controller
	): bool {
		$permissions = $this->getPermissions();

		return isset($permissions[$module])
			&& array_key_exists(
				$controller,
				$permissions[$module]
			);
	}

	public function getControllerPermissions(
		string $module,
		string $controller
	): array {
		$permissions = $this->getPermissions();

		if (!isset($permissions[$module][$controller])) {
			return [];
		}

		if (!is_array($permissions[$module][$controller])) {
			return [];
		}

		return array_values(
			array_unique($permissions[$module][$controller])
		);
	}

	public function getPermissionForAction(
		string $action
	): ?string {
		$action = $this->resolveAction($action);

		foreach (self::ACTIONS as $permission => $actions) {
			if (in_array($action, $actions, true)) {
				return $permission;
			}
		}

		return null;
	}

	public function resolveController(
		Zend_Controller_Request_Abstract $request
	): string {
		$module = $request->getModuleName();
		$controller = $request->getControllerName();

		if (substr($controller, -3) === 'pos') {
			$controller = substr($controller, 0, -3);
		}

		if (
			$controller === 'position'
			|| $controller === 'positionset'
		) {
			$parent = (string)$request->getParam('parent', '');

			if ($parent !== '') {
				$controller = $parent;
			}
		}

		if ($module === 'contacts') {
			return 'contact';
		}

		if ($module === 'items') {
			return 'item';
		}

		return $controller;
	}

	public function resolveAction(string $action): string
	{
		if (strpos($action, 'generate') !== false) {
			return 'generate';
		}

		return $action;
	}

	private function getPermissions(): array
	{
		if ($this->permissions !== null) {
			return $this->permissions;
		}

		$permissionsDb = new Application_Model_DbTable_Permission();
		$row = $permissionsDb->getPermissions();

		$this->permissions = [];

		foreach ($row as $module => $value) {
			if (!is_string($value) || $value === '') {
				continue;
			}

			$decoded = json_decode($value, true);

			if (is_array($decoded)) {
				$this->permissions[$module] = $decoded;
			}
		}

		return $this->permissions;
	}
}
