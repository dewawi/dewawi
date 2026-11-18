<?php

class Contacts_Model_GeocodingBatch extends Contacts_Model_Map
{
	public const MAX_PAGE_SIZE = 50000;
	private const MISSING_OR_INVALID = 'latitude IS NULL OR longitude IS NULL OR latitude NOT BETWEEN -90 AND 90 OR longitude NOT BETWEEN -180 AND 180';
	public function authorize(): void
	{
		parent::authorize();
		$user = Zend_Registry::get('User');
		$identity = Zend_Auth::getInstance()->getIdentity();
		if ((int)$user['clientid'] !== 100 || (int)($identity->clientid ?? 0) !== 100 || (int)($identity->id ?? 0) !== (int)$user['id'] || $this->getClientId(['clientModule' => 'contacts']) !== 100) throw new RuntimeException('Batch is limited to contact owner 100', 403);
	}

	private function selection(): Zend_Db_Select
	{
		$options = ['catid' => ['all' => 'all'], 'tagid' => ['0' => '0'], 'country' => ['0' => '0'], 'role' => ['all' => 'all']];
		return $this->locations(self::FILTERS, $options, ['address_id' => 'id', 'latitude', 'longitude'], ['id', 'created']);
	}

	private function candidatesSelect(?string $after): Zend_Db_Select
	{
		$select = $this->selection()->where('address_id IS NOT NULL')->where(self::MISSING_OR_INVALID);
		if ($after !== null) {
			$cursor = self::decodeCursor($after);
			$db = $select->getAdapter();
			if ($cursor['created'] === null) $select->where('created IS NULL')->where('id < ?', $cursor['id']);
			else $select->where('created IS NULL OR '.$db->quoteInto('created < ?', $cursor['created']).' OR ('.$db->quoteInto('created = ?', $cursor['created']).' AND '.$db->quoteInto('id < ?', $cursor['id']).')');
		}
		return $select;
	}

	public function candidates(int $limit = 1000, ?string $after = null): array
	{
		if ($limit < 1 || $limit > self::MAX_PAGE_SIZE) throw new InvalidArgumentException('Invalid selection page size');
		$select = $this->candidatesSelect($after)->order('created DESC')->order('id DESC')->limit($limit);
		return $select->getAdapter()->fetchAll($select);
	}

	public function page(int $limit, ?string $after = null): array
	{
		$rows = $this->candidates($limit, $after);
		$cursor = $rows ? self::encodeCursor(end($rows)) : null;
		$older = $cursor === null ? 0 : $this->candidateCount($cursor);
		$select = $this->selection()->reset(Zend_Db_Select::COLUMNS)->columns([
			'contacts' => new Zend_Db_Expr('COUNT(*)'),
			'missing_coordinates' => new Zend_Db_Expr('COALESCE(SUM(latitude IS NULL OR longitude IS NULL), 0)'),
			'invalid_coordinates' => new Zend_Db_Expr('COALESCE(SUM(latitude IS NOT NULL AND longitude IS NOT NULL AND ('.self::MISSING_OR_INVALID.')), 0)'),
			'without_billing_address' => new Zend_Db_Expr('COALESCE(SUM(address_id IS NULL), 0)'),
		]);
		$population = array_map('intval', $select->getAdapter()->fetchRow($select));
		$population['candidates'] = $population['missing_coordinates'] + $population['invalid_coordinates'] - $population['without_billing_address'];
		$snapshot = array_map(static fn($row) => [(int)$row['id'], $row['created'], (int)$row['address_id']], $rows);
		return ['rows' => $rows, 'population' => $population, 'selection_page' => ['after' => $after, 'page_size' => $limit, 'selected' => count($rows), 'remaining_older' => $older, 'next_after' => $older ? $cursor : null, 'snapshot_digest' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR))]];
	}

	private function candidateCount(?string $after): int
	{
		$select = $this->candidatesSelect($after)->reset(Zend_Db_Select::COLUMNS)->columns(['count' => new Zend_Db_Expr('COUNT(*)')]);
		return (int)$select->getAdapter()->fetchOne($select);
	}

	public static function encodeCursor(array $row): string
	{
		return rtrim(strtr(base64_encode(json_encode(['version' => 1, 'clientid' => 100, 'created' => $row['created'], 'id' => (int)$row['id']], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
	}

	public static function decodeCursor(string $token): array
	{
		if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $token)) throw new InvalidArgumentException('Invalid selection cursor');
		$cursor = json_decode((string)base64_decode(strtr($token, '-_', '+/'), true), true, 8, JSON_THROW_ON_ERROR);
		if (!is_array($cursor) || count($cursor) !== 4 || ($cursor['version'] ?? 0) !== 1 || ($cursor['clientid'] ?? 0) !== 100 || !is_int($cursor['id'] ?? null) || $cursor['id'] < 1 || !array_key_exists('created', $cursor) || ($cursor['created'] !== null && (!is_string($cursor['created']) || !preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $cursor['created'])))) throw new InvalidArgumentException('Invalid selection cursor');
		if ($cursor['created'] !== null && $cursor['created'] !== '0000-00-00 00:00:00') {
			$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $cursor['created']);
			if (!$date || $date->format('Y-m-d H:i:s') !== $cursor['created']) throw new InvalidArgumentException('Invalid selection date');
		}
		return $cursor;
	}

	public function current(int $contactId): ?array
	{
		$select = $this->selection()->where('id = ?', $contactId);
		return $select->getAdapter()->fetchRow($select) ?: null;
	}
}
