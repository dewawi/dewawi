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
		$this->updateById($id, [
			'quoteid' => $quoteId,
			'quotedate' => $this->_date,
			'filename' => $filename,
			'state' => 105,
		]);
	}
}
