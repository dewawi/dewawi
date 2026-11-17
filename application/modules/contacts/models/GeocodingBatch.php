<?php

class Contacts_Model_GeocodingBatch extends Contacts_Model_Map
{
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

	public function candidates(int $limit = 28506): array
	{
		if ($limit < 1 || $limit > 28506) throw new InvalidArgumentException('Invalid batch size');
		$select = $this->selection()->where('address_id IS NOT NULL')
			->where('latitude IS NULL OR longitude IS NULL OR latitude NOT BETWEEN -90 AND 90 OR longitude NOT BETWEEN -180 AND 180')
			->order('created DESC')->order('id DESC')->limit($limit);
		return $select->getAdapter()->fetchAll($select);
	}

	public function current(int $contactId): ?array
	{
		$select = $this->selection()->where('id = ?', $contactId);
		return $select->getAdapter()->fetchRow($select) ?: null;
	}
}
