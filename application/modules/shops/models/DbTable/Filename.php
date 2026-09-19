<?php

class Shops_Model_DbTable_Filename extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'filename';
	protected ?string $siteField = null;
	protected ?string $deletedField = null;

	public function getFilename(string $type, string $language): string
	{
		$row = $this->fetchRow(
			$this->getPublicSelect()
				->where('language = ?', $language)
				->limit(1)
		);

		if (!$row) {
			throw new RuntimeException("Could not find filename for $type");
		}

		return $row->$type;
	}
}
