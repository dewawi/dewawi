<?php

class Contacts_Model_DbTable_Inquiry extends DEEC_Model_DbTable_Entity
{
	public const STATUSES = [
		'new' => 'CONTACT_INQUIRIES_STATUS_NEW',
		'in_progress' => 'CONTACT_INQUIRIES_STATUS_IN_PROGRESS',
		'completed' => 'CONTACT_INQUIRIES_STATUS_COMPLETED',
	];

	protected $_name = 'shopinquirydata';
	protected ?string $orderingField = null;

	public function init()
	{
		parent::init();
		$this->setClientId($this->getShopClientId());
	}

	public function getDetail(int $id): ?array
	{
		$row = $this->getById($id);

		if (!$row) return null;

		$db = $this->getAdapter();
		$clientId = $this->getClientId();
		$shop = $db->fetchRow(
			$db->select()
				->from('shop', ['title', 'url'])
				->where('id = ?', (int)$row['shopid'])
				->where('clientid = ?', $clientId)
				->limit(1)
		);
		$form = null;

		if ((int)$row['formid'] > 0) {
			$form = $db->fetchRow(
				$db->select()
					->from('shopinquiryform', ['title', 'fields'])
					->where('id = ?', (int)$row['formid'])
					->where('shopid = ?', (int)$row['shopid'])
					->where('clientid = ?', $clientId)
					->limit(1)
			);
		}

		return array_merge($row, [
			'website_title' => (string)($shop['title'] ?? ''),
			'website_url' => (string)($shop['url'] ?? ''),
			'form_title' => (string)($form['title'] ?? ''),
			'form_fields' => (string)($form['fields'] ?? ''),
		]);
	}

	protected function prepareUpdateData(array $data): array
	{
		if (isset($data['status']) && !isset(self::STATUSES[$data['status']])) {
			throw new InvalidArgumentException('Invalid inquiry status');
		}

		return parent::prepareUpdateData($data);
	}

	private function getShopClientId(): int
	{
		$client = Zend_Registry::get('Client');
		$clientId = !empty($client['parentid'])
			? (int)($client['modules']['shops'] ?? 0)
			: (int)($client['id'] ?? 0);

		if ($clientId <= 0) throw new RuntimeException('Shop client context is missing.');

		return $clientId;
	}
}
