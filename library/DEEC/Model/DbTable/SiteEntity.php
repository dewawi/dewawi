<?php

abstract class DEEC_Model_DbTable_SiteEntity extends DEEC_Model_DbTable_Entity
{
	protected ?DEEC_Site_Context $_siteContext = null;
	protected ?string $siteField = 'shopid';
	protected ?string $publicField = null;

	public function init()
	{
		parent::init();

		if (!Zend_Registry::isRegistered('SiteContext')) {
			throw new RuntimeException('Site context is missing');
		}

		$this->_siteContext = Zend_Registry::get('SiteContext');
		$this->setClientId($this->_siteContext->getClientId());
	}

	public function getSiteId(): int
	{
		return $this->_siteContext->getSiteId();
	}

	protected function getPublicSelect(): Zend_Db_Table_Select
	{
		$select = $this->select();

		foreach ($this->getAccessWhere() as $where) {
			$select->where($where);
		}

		if ($this->deletedField !== null) {
			$select->where($this->deletedField . ' = ?', 0);
		}

		if ($this->publicField !== null) {
			$select->where($this->publicField . ' = ?', 1);
		}

		return $select;
	}

	public function getPublicById(int $id): ?array
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('id = ?', $id)
				->limit(1)
		);

		return $row ? $row->toArray() : null;
	}

	protected function applyPublicScope(Zend_Db_Table_Select $select): void
	{
		if (in_array('activated', $this->info(self::COLS), true)) {
			$select->where('activated = ?', 1);
		}
	}

	protected function getAccessWhere(): array
	{
		$where = parent::getAccessWhere();

		if ($this->siteField !== null) {
			$where[] = $this->getAdapter()->quoteInto(
				$this->siteField . ' = ?',
				$this->getSiteId()
			);
		}

		return $where;
	}

	protected function prepareCreateData(array $data): array
	{
		$data = parent::prepareCreateData($data);

		if ($this->siteField !== null && !array_key_exists($this->siteField, $data)) {
			$data[$this->siteField] = $this->getSiteId();
		}

		return $data;
	}
}
