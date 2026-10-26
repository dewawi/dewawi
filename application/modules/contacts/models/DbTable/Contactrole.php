<?php

class Contacts_Model_DbTable_Contactrole extends DEEC_Model_DbTable_Entity
{
	public const ROLE_LABELS = [
		'customer' => 'CONTACTS_ROLE_CUSTOMER',
		'supplier' => 'CONTACTS_ROLE_SUPPLIER',
		'prospect' => 'CONTACTS_ROLE_PROSPECT',
		'partner' => 'CONTACTS_ROLE_PARTNER',
	];

	protected $_name = 'contactrole';
	protected ?string $orderingField = null;

	public static function getRoleOptions(): array
	{
		return self::ROLE_LABELS;
	}

	public static function normalizeRoleCodes($roles): array
	{
		if (is_string($roles)) {
			$roles = $roles === '' ? [] : [$roles];
		}

		if (!is_array($roles)) {
			throw new InvalidArgumentException('Invalid contact roles.');
		}

		$normalized = [];

		foreach ($roles as $role) {
			if (!is_string($role) && !is_int($role)) {
				throw new InvalidArgumentException('Invalid contact role.');
			}

			$role = trim((string)$role);

			if ($role === '') {
				continue;
			}

			if (!isset(self::ROLE_LABELS[$role])) {
				throw new InvalidArgumentException('Invalid contact role.');
			}

			$normalized[$role] = true;
		}

		return array_values(array_intersect(array_keys(self::ROLE_LABELS), array_keys($normalized)));
	}

	public function getForContact(int $contactId): array
	{
		$roles = $this->getForContacts([$contactId]);
		return $roles[$contactId] ?? [];
	}

	public function getForContacts(array $contactIds): array
	{
		$contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds), static function(int $id): bool {
			return $id > 0;
		})));

		if (!$contactIds) {
			return [];
		}

		$rows = $this->fetchAll(
			$this->select()
				->where('parentid IN (?)', $contactIds)
				->where('clientid = ?', $this->getClientId())
				->where('deleted = ?', 0)
				->order('id ASC')
		);

		$grouped = [];

		foreach ($rows as $row) {
			$parentId = (int)$row->parentid;
			$role = (string)$row->role;

			if (isset(self::ROLE_LABELS[$role])) {
				$grouped[$parentId][$role] = true;
			}
		}

		foreach ($grouped as $parentId => $roles) {
			$grouped[$parentId] = array_values(array_intersect(array_keys(self::ROLE_LABELS), array_keys($roles)));
		}

		return $grouped;
	}

	public function syncForContact(int $contactId, $roles): array
	{
		if ($contactId <= 0) {
			throw new InvalidArgumentException('Invalid contact.');
		}

		$roles = self::normalizeRoleCodes($roles);
		$wanted = array_fill_keys($roles, true);
		$rows = $this->fetchAll(
			$this->select()
				->where('parentid = ?', $contactId)
				->where('clientid = ?', $this->getClientId())
				->order('id ASC')
				->forUpdate(true)
		);
		$existing = [];

		foreach ($rows as $row) {
			$role = (string)$row->role;

			if (!isset(self::ROLE_LABELS[$role]) || isset($existing[$role])) {
				continue;
			}

			$existing[$role] = $row->toArray();
		}

		foreach ($existing as $role => $row) {
			$shouldBeActive = isset($wanted[$role]);
			$isActive = (int)$row['deleted'] === 0;

			if ($shouldBeActive === $isActive) {
				continue;
			}

			$this->update([
				'deleted' => $shouldBeActive ? 0 : 1,
				'modified' => $this->_date,
				'modifiedby' => $this->getUserId(),
			], [
				$this->getAdapter()->quoteInto('id = ?', (int)$row['id']),
				$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
			]);
		}

		foreach ($roles as $role) {
			if (!isset($existing[$role])) {
				$this->create([
					'parentid' => $contactId,
					'role' => $role,
				]);
			}
		}

		return $roles;
	}
}
