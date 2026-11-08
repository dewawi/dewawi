<?php

class Shops_Model_DbTable_Inquirydata extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'shopinquirydata';
	protected ?string $orderingField = null;

	public function save(int $formId, string $token, array $data): int
	{
		return $this->saveOnce($formId, $token, $data)['id'];
	}

	public function saveOnce(int $formId, string $token, array $data): array
	{
		$token = strtolower(trim($token));

		if (!preg_match('/^[a-f0-9]{32,64}$/D', $token)) {
			throw new InvalidArgumentException('Invalid inquiry token');
		}

		$existing = $this->getByToken($token);

		if ($existing) {
			return ['id' => (int)$existing['id'], 'inserted' => false];
		}

		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === false) {
			throw new RuntimeException('Could not encode inquiry data');
		}

		try {
			$id = $this->create([
				'formid' => $formId,
				'token' => $token,
				'data' => $json,
				'status' => 'new',
				'modified' => $this->_date,
			]);

			return ['id' => $id, 'inserted' => true];
		} catch (Exception $e) {
			$existing = $this->getByToken($token);

			if ($existing) {
				return ['id' => (int)$existing['id'], 'inserted' => false];
			}

			throw $e;
		}
	}

	public function getByToken(string $token): ?array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('token = ?', $token)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	public function getByQuoteId(int $quoteId): ?array
	{
		if ($quoteId <= 0) return null;
		$rows = $this->fetchAll($this->getPublicSelect()->where('quoteid = ?', $quoteId)->limit(2));

		return count($rows) === 1 ? $rows->current()->toArray() : null;
	}

	public function linkQuoteIfEmpty(int $id, int $contactId, int $quoteId): bool
	{
		$where = $this->getEntityWhere($id);
		$where[] = '(quoteid IS NULL OR quoteid = 0)';

		return $this->update($this->prepareUpdateData([
			'contactid' => $contactId,
			'quoteid' => $quoteId,
		]), $where) === 1;
	}
}
