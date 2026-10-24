<?php

class Shops_Model_DbTable_Quote extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'quote';
	protected ?string $siteField = null;
	protected ?string $orderingField = null;

	public function getQuote(int $id): array
	{
		$row = $this->getById($id);

		if (!$row) {
			throw new RuntimeException("Could not find quote $id");
		}

		return $row;
	}

	public function addQuote(array $data): int
	{
		return $this->create($data);
	}

	public function updateQuote(int $id, array $data): void
	{
		$this->updateById($id, $data);
	}

	public function saveQuote(int $id, int $quoteId, string $filename): void
	{
		$quote = $this->getQuote($id);
		if (!empty($quote['quoteid'])) return;

		$where = $this->getEntityWhere($id);
		$where[] = '(quoteid IS NULL OR quoteid = 0)';

		$this->update($this->prepareUpdateData([
			'quoteid' => $quoteId,
			'quotedate' => $quote['quotedate'] ?: date('Y-m-d'),
			'filename' => $filename,
			'state' => 105,
			'completed' => 1,
			'completeddate' => $this->_date,
			'completedby' => $this->getUserId(),
		]), $where);
	}
}
