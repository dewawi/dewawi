<?php

class Admin_UserController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'users',
			'list' => 'Admin_Model_List_Users',
			'entity' => Admin_Model_Entity_User::listConfig(),
		]);
	}

	protected function prepareEditRow(array $row): array
	{
		$row['password'] = '';

		return $row;
	}

	protected function buildEditViewModel(int $id, array $row): array
	{
		$permissionDb = new Admin_Model_DbTable_Permission();
		$permission = $permissionDb->getByUserId($id);

		return [
			'permission' => $permission
				? $this->decodePermissions($permission)
				: null,
			'permissionModules' => $this->getPermissionModules(),
		];
	}

	protected function getCreateData(): array
	{
		return [
			'username' => 'new-user-' . date('YmdHis'),
			'password' => password_hash(
				bin2hex(random_bytes(16)),
				PASSWORD_DEFAULT
			),
			'email' => '',
			//'language' => (string)($this->_user['language'] ?? 'de'),
			'activated' => 0,
		];
	}

	protected function afterCreate(int $id, array $data): void
	{
		$permissions = [
			'userid' => $id,
		];

		foreach ($this->getDefaultPermissions() as $module => $controllers) {
			$permissions[$module] = json_encode($controllers);
		}

		$permissionDb = new Admin_Model_DbTable_Permission();
		$permissionDb->create($permissions);
	}

	protected function beforeEditSave(array $values, array $row): array {
		if (array_key_exists('password', $values)) {
			$password = trim((string)$values['password']);

			if ($password === '') {
				unset($values['password']);
			} else {
				$values['password'] = password_hash(
					$password,
					PASSWORD_DEFAULT
				);
			}
		}

		return $values;
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$permissionDb = new Admin_Model_DbTable_Permission();
		$permission = $permissionDb->getByUserId($oldId);

		if (!$permission) {
			return;
		}

		$newPermissionId = $permissionDb->copyById(
			(int)$permission['id']
		);

		$permissionDb->updateById(
			$newPermissionId,
			[
				'userid' => $newId,
			]
		);
	}

	protected function canDeleteRow(array $row): bool
	{
		if ((int)$row['id'] === (int)$this->_user['id']) {
			$this->_flashMessenger->addMessage('MESSAGES_OWN_USER_CAN_NOT_BE_DELETED');
			return false;
		}

		return true;
	}

	protected function afterDelete(int $id, array $row): void
	{
		$permissionDb = new Admin_Model_DbTable_Permission();
		$permission = $permissionDb->getByUserId($id);

		if (!$permission) {
			return;
		}

		$permissionDb->deleteById(
			(int)$permission['id']
		);
	}

	private function getDefaultPermissions(): array
	{
		return [
			'default' => [
				'index' => ['view'],
				'comment' => ['add', 'edit', 'view', 'delete'],
			],
			'contacts' => [
				'contact' => ['add', 'edit', 'view', 'delete'],
				'email' => ['add', 'edit', 'view', 'delete'],
			],
			'items' => [
				'item' => ['add', 'edit', 'view', 'delete'],
				'ledger' => ['add', 'edit', 'view', 'delete'],
				'pricerule' => ['add', 'edit', 'view', 'delete'],
			],
			'processes' => [
				'process' => ['add', 'edit', 'view', 'delete'],
			],
			'purchases' => [
				'quoterequest' => ['add', 'edit', 'view', 'delete'],
				'purchaseorder' => ['add', 'edit', 'view', 'delete'],
			],
			'sales' => [
				'quote' => ['add', 'edit', 'view', 'delete'],
				'salesorder' => ['add', 'edit', 'view', 'delete'],
				'deliveryorder' => ['add', 'edit', 'view', 'delete'],
				'invoice' => ['add', 'edit', 'view', 'delete'],
				'creditnote' => ['add', 'edit', 'view', 'delete'],
				'reminder' => ['add', 'edit', 'view', 'delete'],
			],
			'statistics' => [
				'turnover' => ['view'],
				'customer' => ['view'],
				'quote' => ['view'],
			],
		];
	}

	private function decodePermissions(array $permission): array
	{
		foreach ($this->getPermissionModules() as $module) {
			$decoded = json_decode(
				(string)($permission[$module] ?? ''),
				true
			);

			$permission[$module] = is_array($decoded)
				? $decoded
				: [];
		}

		return $permission;
	}

	private function getPermissionModules(): array
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
