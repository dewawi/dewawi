<?php

class Contacts_Model_DbTable_Email extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'email';

	protected function prepareCreateData(array $data): array
	{
		return parent::prepareCreateData($this->normalizeEmailData($data));
	}

	protected function prepareUpdateData(array $data): array
	{
		return parent::prepareUpdateData($this->normalizeEmailData($data));
	}

	protected function normalizeEmailData(array $data): array
	{
		if(!array_key_exists('email', $data)) return $data;

		$data['email'] = DEEC_Filter::email((string)$data['email']);

		if($data['email'] !== null && !DEEC_Filter::isValidEmail($data['email'])) {
			throw new InvalidArgumentException('Invalid email address');
		}

		return $data;
	}

	public function getEmail($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if(!$row) return false;
		return $row->toArray();
	}

	public function suppressByEmail(string $email, string $reason): int
	{
		$email = strtolower(trim($email));
		$reason = strtolower(trim($reason));

		if($email === '') return 0;

		if(!in_array($reason, ['manual', 'unsubscribe', 'bounce', 'complaint'], true)) {
			throw new InvalidArgumentException('Invalid email suppression reason');
		}

		$data = $this->prepareUpdateData([
			'suppressed' => 1,
			'suppressionreason' => $reason,
			'suppresseddate' => $this->_date,
		]);

		$where = [
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
			$this->getAdapter()->quoteInto('LOWER(TRIM(email)) = ?', $email),
		];

		return $this->update($data, $where);
	}

	public function unsuppressByEmail(string $email): int
	{
		$email = strtolower(trim($email));

		if($email === '') return 0;

		$data = $this->prepareUpdateData([
			'suppressed' => 0,
			'suppressionreason' => null,
			'suppresseddate' => null,
		]);

		$where = [
			$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
			$this->getAdapter()->quoteInto('LOWER(TRIM(email)) = ?', $email),
		];

		return $this->update($data, $where);
	}
}
