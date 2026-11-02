<?php

class Shops_Model_DbTable_Email extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'email';
	protected ?string $siteField = null;

	public function findContactIdByEmail(string $email): int
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('module = ?', 'contacts')
				->where('controller = ?', 'contact')
				->where('email = ?', trim($email))
				->limit(1)
		);

		return $row ? (int)$row['parentid'] : 0;
	}

	public function findContactIdsByEmail(string $email): array
	{
		$ids = [];
		foreach ($this->fetchAll(
			$this->getPublicSelect()
				->where('module = ?', 'contacts')
				->where('controller = ?', 'contact')
				->where('email = ?', trim($email))
				->order('parentid ASC')
		) as $row) {
			$ids[(int)$row['parentid']] = (int)$row['parentid'];
		}

		return array_values($ids);
	}

	public function addEmail(array $data): int
	{
		return $this->create($data);
	}
}
