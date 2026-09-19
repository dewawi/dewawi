<?php

class Shops_Model_DbTable_Inquiryform extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'shopinquiryform';

	public function getInquiryform(int $id): ?array
	{
		return $this->getById($id);
	}
}
