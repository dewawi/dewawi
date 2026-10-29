<?php

class Contacts_Model_DbTable_Shop extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'shop';
	protected ?string $orderingField = null;

	public function init()
	{
		parent::init();
		$client = Zend_Registry::get('Client');
		$clientId = !empty($client['parentid'])
			? (int)($client['modules']['shops'] ?? 0)
			: (int)($client['id'] ?? 0);

		if ($clientId <= 0) throw new RuntimeException('Shop client context is missing.');

		$this->setClientId($clientId);
	}

	public function getSelectOptions(): array
	{
		$rows = $this->fetchAll(
			$this->select()
				->where('clientid = ?', $this->getClientId())
				->where('deleted = ?', 0)
				->order('title ASC')
				->order('url ASC')
		);
		$options = [];

		foreach ($rows as $row) {
			$label = trim((string)$row->title) ?: trim((string)$row->url);
			$options[(string)$row->id] = $label !== '' ? $label : (string)$row->id;
		}

		return $options;
	}
}
