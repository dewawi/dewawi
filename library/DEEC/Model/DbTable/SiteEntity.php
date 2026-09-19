<?php

abstract class DEEC_Model_DbTable_SiteEntity extends DEEC_Model_DbTable_Entity
{
	protected ?DEEC_Site_Context $_siteContext = null;
	protected ?string $siteField = 'shopid';

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
